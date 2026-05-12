<?php
/**
 * backend/GestioneListe.php
 *
 * Azioni (POST JSON):
 *   crea_lista      { titolo, descrizione }
 *   modifica_lista  { lista_id, titolo, descrizione }
 *   elimina_lista   { lista_id }
 *   cerca_film      { q }
 *   aggiungi_film   { lista_id, tmdb_id }
 *   rimuovi_film    { lista_id, tmdb_id }
 *   riordina        { lista_id, ordine: [tmdb_id, ...] }
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

const LIST_TMDB_KEY = "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJiY2U5ZWZmYjRjMDA5ZTNmNGZmZTM1N2QzOTlhMTk4NSIsIm5iZiI6MTc2OTc4NDc2MC43NzcsInN1YiI6IjY5N2NjNWI4MWVhOGNmMGRlZTI2YWVmNSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.cPNGTwdDeQD6TGYY2Shn-i3Y0i-OjMSey9KPpdrpBAE";

// ── Helpers ─────────────────────────────────────────────────────

/** Verifica che la lista appartenga all'utente. Ritorna l'ID lista o false. */
function ownsList(PDO $pdo, int $my_id, int $lista_id): bool
{
    $s = $pdo->prepare("SELECT IDUtente FROM Lista WHERE IDLista = ? LIMIT 1");
    $s->execute([$lista_id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    return $row && (int)$row['IDUtente'] === $my_id;
}

/**
 * Assicura che il film esista nel DB.
 * Se non c'è, lo scarica da TMDB e lo inserisce completo.
 * Se TMDB non risponde, inserisce un record minimo con solo TMDB_ID e Title.
 * Ritorna il Film.ID interno.
 */
function ensureFilmExistsList(PDO $pdo, int $tmdb_id): int|false
{
    $s = $pdo->prepare("SELECT ID FROM Film WHERE TMDB_ID = ? LIMIT 1");
    $s->execute([$tmdb_id]);
    $id = $s->fetchColumn();
    if ($id !== false) return (int)$id;

    // Tenta di scaricare da TMDB
    $ch = curl_init("https://api.themoviedb.org/3/movie/{$tmdb_id}?language=it-IT");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . LIST_TMDB_KEY, 'accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $f = json_decode($resp ?: '{}', true) ?? [];

    if (!empty($f['id'])) {
        // Insert completo
        $s = $pdo->prepare("
            INSERT INTO Film (TMDB_ID, Title, Original_Title, Original_Language,
                              Overview, Release_Date, Runtime,
                              Poster_Path, Backdrop_Path,
                              Popularity, Vote_Average, Vote_Count)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $s->execute([
            $f['id'],
            $f['title']            ?? 'Film senza titolo',
            $f['original_title']   ?? null,
            $f['original_language']?? null,
            $f['overview']         ?? null,
            !empty($f['release_date']) ? $f['release_date'] : null,
            $f['runtime']          ?? null,
            $f['poster_path']      ?? null,
            $f['backdrop_path']    ?? null,
            $f['popularity']       ?? null,
            $f['vote_average']     ?? null,
            $f['vote_count']       ?? null,
        ]);
    } else {
        // Insert minimo (TMDB non raggiungibile)
        $s = $pdo->prepare("INSERT INTO Film (TMDB_ID, Title) VALUES (?, ?)");
        $s->execute([$tmdb_id, "Film #$tmdb_id"]);
    }
    return (int)$pdo->lastInsertId();
}

/** Prossima posizione disponibile per un film in una lista */
function nextPosition(PDO $pdo, int $lista_id): int
{
    $s = $pdo->prepare("SELECT COALESCE(MAX(Posizione),0)+1 FROM Lista_Film WHERE IDLista = ?");
    $s->execute([$lista_id]);
    return (int)$s->fetchColumn();
}

// ── Router ──────────────────────────────────────────────────────
try {
    switch ($action) {

        // ── Crea lista ──────────────────────────────────────────
        case 'crea_lista':
            $titolo = trim($data['titolo'] ?? '');
            $desc   = trim($data['descrizione'] ?? '');
            if (mb_strlen($titolo) < 1) {
                echo json_encode(['ok' => false, 'error' => 'Il titolo è obbligatorio']);
                break;
            }
            $titolo = mb_substr($titolo, 0, 255);
            $desc   = mb_substr($desc, 0, 255);

            $s = $pdo->prepare("INSERT INTO Lista (Titolo, Descrizione, IDUtente) VALUES (?,?,?)");
            $s->execute([$titolo, $desc ?: null, $my_id]);
            $new_id = (int)$pdo->lastInsertId();

            echo json_encode(['ok' => true, 'lista_id' => $new_id]);
            break;

        // ── Modifica lista ──────────────────────────────────────
        case 'modifica_lista':
            $lista_id = (int)($data['lista_id'] ?? 0);
            $titolo   = trim($data['titolo'] ?? '');
            $desc     = trim($data['descrizione'] ?? '');

            if (!$lista_id || !ownsList($pdo, $my_id, $lista_id)) {
                echo json_encode(['ok' => false, 'error' => 'Lista non trovata']); break;
            }
            if (mb_strlen($titolo) < 1) {
                echo json_encode(['ok' => false, 'error' => 'Il titolo è obbligatorio']); break;
            }
            $pdo->prepare("UPDATE Lista SET Titolo=?, Descrizione=? WHERE IDLista=?")
                ->execute([mb_substr($titolo,0,255), mb_substr($desc,0,255) ?: null, $lista_id]);

            echo json_encode(['ok' => true]);
            break;

        // ── Elimina lista ───────────────────────────────────────
        case 'elimina_lista':
            $lista_id = (int)($data['lista_id'] ?? 0);
            if (!$lista_id || !ownsList($pdo, $my_id, $lista_id)) {
                echo json_encode(['ok' => false, 'error' => 'Lista non trovata']); break;
            }
            $pdo->prepare("DELETE FROM Lista WHERE IDLista = ?")->execute([$lista_id]);
            echo json_encode(['ok' => true]);
            break;

        // ── Ricerca film (TMDB) ─────────────────────────────────
        case 'cerca_film':
            $q = trim($data['q'] ?? '');
            if (mb_strlen($q) < 2) { echo json_encode(['ok' => true, 'films' => []]); break; }

            $ch = curl_init("https://api.themoviedb.org/3/search/movie?query=" . urlencode($q) . "&language=it-IT");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . LIST_TMDB_KEY, 'accept: application/json'],
            ]);
            $res = json_decode(curl_exec($ch) ?: '{}', true) ?? [];
            curl_close($ch);

            $films = array_map(fn($f) => [
                'tmdb_id'     => $f['id'],
                'title'       => $f['title'],
                'year'        => !empty($f['release_date']) ? substr($f['release_date'], 0, 4) : '',
                'poster_path' => $f['poster_path'] ?? null,
            ], array_slice($res['results'] ?? [], 0, 8));

            echo json_encode(['ok' => true, 'films' => $films]);
            break;

        // ── Aggiungi film a lista ───────────────────────────────
        case 'aggiungi_film':
            $lista_id = (int)($data['lista_id'] ?? 0);
            $tmdb_id  = (int)($data['tmdb_id']  ?? 0);

            if (!$lista_id || !$tmdb_id) {
                echo json_encode(['ok' => false, 'error' => 'Parametri mancanti']); break;
            }
            if (!ownsList($pdo, $my_id, $lista_id)) {
                echo json_encode(['ok' => false, 'error' => 'Non autorizzato']); break;
            }

            $film_id = ensureFilmExistsList($pdo, $tmdb_id);
            if (!$film_id) {
                echo json_encode(['ok' => false, 'error' => 'Film non trovato']); break;
            }

            // Controlla se già presente
            $s = $pdo->prepare("SELECT 1 FROM Lista_Film WHERE IDLista=? AND IDFilm=? LIMIT 1");
            $s->execute([$lista_id, $film_id]);
            if ($s->fetchColumn()) {
                echo json_encode(['ok' => false, 'error' => 'Film già in lista']); break;
            }

            $pos = nextPosition($pdo, $lista_id);
            $pdo->prepare("INSERT INTO Lista_Film (IDLista, IDFilm, Posizione) VALUES (?,?,?)")
                ->execute([$lista_id, $film_id, $pos]);

            // Ritorna i dati del film appena inserito
            $s = $pdo->prepare("SELECT ID, TMDB_ID, Title, Poster_Path, Release_Date FROM Film WHERE ID=?");
            $s->execute([$film_id]);
            $film_row = $s->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'film' => $film_row, 'posizione' => $pos]);
            break;

        // ── Rimuovi film da lista ───────────────────────────────
        case 'rimuovi_film':
            $lista_id = (int)($data['lista_id'] ?? 0);
            $tmdb_id  = (int)($data['tmdb_id']  ?? 0);

            if (!ownsList($pdo, $my_id, $lista_id)) {
                echo json_encode(['ok' => false, 'error' => 'Non autorizzato']); break;
            }

            $s = $pdo->prepare("SELECT ID FROM Film WHERE TMDB_ID=? LIMIT 1");
            $s->execute([$tmdb_id]);
            $film_id = $s->fetchColumn();
            if (!$film_id) { echo json_encode(['ok' => false, 'error' => 'Film non trovato']); break; }

            $pdo->prepare("DELETE FROM Lista_Film WHERE IDLista=? AND IDFilm=?")
                ->execute([$lista_id, $film_id]);

            // Rinumera le posizioni rimanenti
            $s = $pdo->prepare("SELECT IDFilm FROM Lista_Film WHERE IDLista=? ORDER BY Posizione ASC");
            $s->execute([$lista_id]);
            $remaining = $s->fetchAll(PDO::FETCH_COLUMN);
            $upd = $pdo->prepare("UPDATE Lista_Film SET Posizione=? WHERE IDLista=? AND IDFilm=?");
            foreach ($remaining as $i => $fid) {
                $upd->execute([$i + 1, $lista_id, $fid]);
            }

            echo json_encode(['ok' => true]);
            break;

        // ── Riordina film ───────────────────────────────────────
        case 'riordina':
            $lista_id = (int)($data['lista_id'] ?? 0);
            $ordine   = $data['ordine'] ?? [];   // array di TMDB_ID nell'ordine voluto

            if (!$lista_id || !is_array($ordine)) {
                echo json_encode(['ok' => false, 'error' => 'Dati mancanti']); break;
            }
            if (!ownsList($pdo, $my_id, $lista_id)) {
                echo json_encode(['ok' => false, 'error' => 'Non autorizzato']); break;
            }

            // Mappa tmdb_id → film_id
            if (empty($ordine)) { echo json_encode(['ok' => true]); break; }

            $placeholders = implode(',', array_fill(0, count($ordine), '?'));
            $s = $pdo->prepare("SELECT ID, TMDB_ID FROM Film WHERE TMDB_ID IN ($placeholders)");
            $s->execute(array_map('intval', $ordine));
            $map = [];
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int)$row['TMDB_ID']] = (int)$row['ID'];
            }

            $upd = $pdo->prepare("UPDATE Lista_Film SET Posizione=? WHERE IDLista=? AND IDFilm=?");
            foreach ($ordine as $pos => $tmdb_id) {
                $fid = $map[(int)$tmdb_id] ?? null;
                if ($fid) $upd->execute([$pos + 1, $lista_id, $fid]);
            }

            echo json_encode(['ok' => true]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => "Azione '$action' non riconosciuta"]);
    }
} catch (PDOException $e) {
    error_log('[GestioneListe] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore database']);
}