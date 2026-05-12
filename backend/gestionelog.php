<?php
/**
 * backend/GestioneLog.php
 *
 * Azioni disponibili (POST JSON):
 *   cerca_film   → ricerca su TMDB          { q }
 *   salva_log    → CREA un nuovo Log         { tmdb_id, data, voto, recensione, liked }
 *   modifica_log → AGGIORNA un Log esistente { log_id, data, voto, recensione, liked }
 *   elimina_log  → cancella un Log           { log_id }
 *
 * IMPORTANTE — `modifica_log` esegue solo UPDATE, MAI INSERT:
 *   - se manca log_id → errore
 *   - se il log non appartiene all'utente loggato → errore
 *   - altrimenti UPDATE Log SET ... WHERE ID = log_id
 */

declare(strict_types=1);
session_start();
require_once __DIR__ . '/../pages/Database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non autenticato']);
    exit;
}

$my_id  = (int)$_SESSION['user_id'];
$pdo    = getConnection();
$raw    = file_get_contents('php://input');
$data   = json_decode($raw ?: '{}', true) ?? [];
$action = $data['action'] ?? '';

const LOG_TMDB_KEY = "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJiY2U5ZWZmYjRjMDA5ZTNmNGZmZTM1N2QzOTlhMTk4NSIsIm5iZiI6MTc2OTc4NDc2MC43NzcsInN1YiI6IjY5N2NjNWI4MWVhOGNmMGRlZTI2YWVmNSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.cPNGTwdDeQD6TGYY2Shn-i3Y0i-OjMSey9KPpdrpBAE";

// ── Helpers ─────────────────────────────────

function ensureFilmExistsLog(PDO $pdo, int $tmdb_id): int|false
{
    $stmt = $pdo->prepare("SELECT ID FROM Film WHERE TMDB_ID = ? LIMIT 1");
    $stmt->execute([$tmdb_id]);
    $id = $stmt->fetchColumn();
    if ($id !== false) return (int)$id;

    $ch = curl_init("https://api.themoviedb.org/3/movie/{$tmdb_id}?language=it-IT");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . LOG_TMDB_KEY, 'accept: application/json'],
    ]);
    $f = json_decode(curl_exec($ch) ?: '{}', true) ?? [];
    curl_close($ch);
    if (empty($f['id'])) return false;

    $stmt = $pdo->prepare("
        INSERT INTO Film (TMDB_ID, Title, Original_Title, Original_Language,
                          Overview, Release_Date, Runtime, Poster_Path, Backdrop_Path,
                          Popularity, Vote_Average, Vote_Count)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $f['id'], $f['title'] ?? '', $f['original_title'] ?? null, $f['original_language'] ?? null,
        $f['overview'] ?? null,
        !empty($f['release_date']) ? $f['release_date'] : null,
        $f['runtime'] ?? null, $f['poster_path'] ?? null, $f['backdrop_path'] ?? null,
        $f['popularity'] ?? null, $f['vote_average'] ?? null, $f['vote_count'] ?? null,
    ]);
    return (int)$pdo->lastInsertId();
}

function upsertVisioneLog(PDO $pdo, int $uid, int $film_id, array $fields): void
{
    $allowed = ['Is_Watched','In_Watchlist','Liked','Rating','Is_Favourite'];
    $set = []; $vals = [];
    foreach ($fields as $col => $val) {
        if (!in_array($col, $allowed, true)) continue;
        $set[]  = "`{$col}` = ?";
        $vals[] = $val;
    }
    if (!$set) return;

    $sql  = "UPDATE Visione SET " . implode(', ', $set) . " WHERE IDUtente = ? AND IDFilm = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([...$vals, $uid, $film_id]);
    if ($stmt->rowCount() === 0) {
        $cols         = array_keys($fields);
        $colList      = 'IDUtente, IDFilm, ' . implode(', ', array_map(fn($c) => "`{$c}`", $cols));
        $placeholders = implode(', ', array_fill(0, count($cols) + 2, '?'));
        $pdo->prepare("INSERT INTO Visione ({$colList}) VALUES ({$placeholders})")
            ->execute([$uid, $film_id, ...array_values($fields)]);
    }
}

/** Normalizza il voto: null se vuoto, altrimenti arrotonda a 0.5 nel range 0.5–5.0 */
function normalizzaVoto($raw): ?float
{
    if ($raw === null || $raw === '' || $raw === false) return null;
    $v = (float)$raw;
    if ($v <= 0) return null;                   // 0 = nessun voto
    $v = round($v * 2) / 2;                     // arrotondamento a 0.5
    return max(0.5, min(5.0, $v));
}

// ── Router ──────────────────────────────────
try {
    switch ($action) {

        // ── Ricerca film (TMDB) ──────────────
        case 'cerca_film':
            $q = trim($data['q'] ?? '');
            if (mb_strlen($q) < 2) { echo json_encode(['ok'=>true,'films'=>[]]); break; }

            $ch = curl_init("https://api.themoviedb.org/3/search/movie?query=" . urlencode($q) . "&language=it-IT");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . LOG_TMDB_KEY, 'accept: application/json'],
            ]);
            $res = json_decode(curl_exec($ch) ?: '{}', true) ?? [];
            curl_close($ch);

            $films = array_map(fn($f) => [
                'id'          => $f['id'],
                'title'       => $f['title'],
                'year'        => !empty($f['release_date']) ? substr($f['release_date'], 0, 4) : '',
                'poster_path' => $f['poster_path'] ?? null,
            ], array_slice($res['results'] ?? [], 0, 8));

            echo json_encode(['ok' => true, 'films' => $films]);
            break;

        // ── Salva NUOVO log (INSERT) ─────────
        case 'salva_log':
            $tmdb_id    = (int)($data['tmdb_id']   ?? 0);
            $data_vis   = $data['data']             ?? date('Y-m-d');
            $voto       = normalizzaVoto($data['voto'] ?? null);
            $recensione = trim($data['recensione']  ?? '');
            $liked      = !empty($data['liked']);

            if (!$tmdb_id) { echo json_encode(['ok'=>false,'error'=>'Film non specificato']); break; }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_vis)) $data_vis = date('Y-m-d');

            $film_id = ensureFilmExistsLog($pdo, $tmdb_id);
            if (!$film_id) { echo json_encode(['ok'=>false,'error'=>'Film non trovato']); break; }

            $stmt = $pdo->prepare(
                "INSERT INTO Log (IDUtente, IDFilm, Data, Voto, Recensione) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$my_id, $film_id, $data_vis, $voto, $recensione ?: null]);
            $log_id = (int)$pdo->lastInsertId();

            $visioneData = ['Is_Watched' => 1, 'Liked' => (int)$liked];
            if ($voto !== null) $visioneData['Rating'] = $voto;
            upsertVisioneLog($pdo, $my_id, $film_id, $visioneData);

            echo json_encode(['ok' => true, 'log_id' => $log_id]);
            break;

        // ── MODIFICA log esistente (UPDATE) ──
        // *** FA SOLO UPDATE — NON CREA NUOVI RECORD ***
        case 'modifica_log':
            $log_id     = (int)($data['log_id'] ?? 0);
            $data_vis   = $data['data']         ?? date('Y-m-d');
            $voto       = normalizzaVoto($data['voto'] ?? null);
            $recensione = trim($data['recensione'] ?? '');
            $liked      = !empty($data['liked']);

            if (!$log_id) {
                echo json_encode(['ok'=>false,'error'=>'Log non specificato']);
                break;
            }

            // 1. Verifica che il log esista E appartenga all'utente loggato
            $stmt = $pdo->prepare(
                "SELECT IDFilm FROM Log WHERE ID = ? AND IDUtente = ? LIMIT 1"
            );
            $stmt->execute([$log_id, $my_id]);
            $film_id = $stmt->fetchColumn();
            if (!$film_id) {
                echo json_encode(['ok'=>false,'error'=>'Log non trovato o non autorizzato']);
                break;
            }

            // 2. Validazione data
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_vis)) $data_vis = date('Y-m-d');

            // 3. UPDATE — questa è l'unica operazione di scrittura su Log
            //    Nota: il WHERE include sia ID che IDUtente per doppia sicurezza.
            $stmt = $pdo->prepare(
                "UPDATE Log
                 SET Data = ?, Voto = ?, Recensione = ?
                 WHERE ID = ? AND IDUtente = ?"
            );
            $stmt->execute([
                $data_vis,
                $voto,
                $recensione !== '' ? $recensione : null,
                $log_id,
                $my_id,
            ]);

            // 4. Sincronizza Visione (Liked + Rating)
            $visioneData = ['Liked' => (int)$liked];
            if ($voto !== null) $visioneData['Rating'] = $voto;
            upsertVisioneLog($pdo, $my_id, (int)$film_id, $visioneData);

            echo json_encode(['ok' => true, 'log_id' => $log_id]);
            break;

        // ── Elimina log ──────────────────────
        case 'elimina_log':
            $log_id = (int)($data['log_id'] ?? 0);
            if (!$log_id) { echo json_encode(['ok'=>false,'error'=>'Log non specificato']); break; }

            $stmt = $pdo->prepare("SELECT IDFilm FROM Log WHERE ID = ? AND IDUtente = ? LIMIT 1");
            $stmt->execute([$log_id, $my_id]);
            $film_id = $stmt->fetchColumn();
            if (!$film_id) { echo json_encode(['ok'=>false,'error'=>'Log non trovato o non autorizzato']); break; }

            $pdo->prepare("DELETE FROM Log WHERE ID = ? AND IDUtente = ?")->execute([$log_id, $my_id]);

            // Se non esistono altri log per questo film, pulisce il rating da Visione
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Log WHERE IDUtente = ? AND IDFilm = ?");
            $stmt->execute([$my_id, $film_id]);
            if ((int)$stmt->fetchColumn() === 0) {
                $pdo->prepare("UPDATE Visione SET Rating = NULL WHERE IDUtente = ? AND IDFilm = ?")
                    ->execute([$my_id, $film_id]);
            }

            echo json_encode(['ok' => true]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => "Azione '{$action}' sconosciuta"]);
    }
} catch (PDOException $e) {
    error_log('[GestioneLog] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore database']);
}