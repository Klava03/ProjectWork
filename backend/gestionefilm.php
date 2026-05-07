<?php
/**
 * backend/GestioneFilm.php
 *
 * Endpoint AJAX per tutte le azioni su un film:
 *   toggle_visto      → Visione.Is_Watched
 *   toggle_watchlist  → Visione.In_Watchlist
 *   toggle_like       → Visione.Liked
 *   set_rating        → Visione.Rating  (implica Is_Watched = 1)
 *
 * Richiesta: POST JSON  { action, tmdb_id, [rating] }
 * Risposta:  JSON        { ok, stato, [error] }
 *
 * Nota: il file è nella cartella backend/ che è una cartella REALE
 * sul disco, quindi il .htaccess la servirà direttamente senza
 * passare per index.php (grazie alla regola !-f / !-d).
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../pages/Database.php';

header('Content-Type: application/json; charset=utf-8');

// ── Autenticazione ────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non autenticato']);
    exit;
}

$my_id  = (int)$_SESSION['user_id'];
$pdo    = getConnection();

// ── Input JSON ────────────────────────────────
$raw    = file_get_contents('php://input');
$data   = json_decode($raw ?: '{}', true) ?? [];

$action  = $data['action']  ?? '';
$tmdb_id = (int)($data['tmdb_id'] ?? 0);
$rating  = isset($data['rating']) ? (float)$data['rating'] : null;

if (!$tmdb_id || !$action) {
    echo json_encode(['ok' => false, 'error' => 'Parametri mancanti']);
    exit;
}

// ── API Key TMDB ──────────────────────────────
const TMDB_KEY = "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJiY2U5ZWZmYjRjMDA5ZTNmNGZmZTM1N2QzOTlhMTk4NSIsIm5iZiI6MTc2OTc4NDc2MC43NzcsInN1YiI6IjY5N2NjNWI4MWVhOGNmMGRlZTI2YWVmNSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.cPNGTwdDeQD6TGYY2Shn-i3Y0i-OjMSey9KPpdrpBAE";

// ═════════════════════════════════════════════
//  FUNZIONI
// ═════════════════════════════════════════════

/**
 * Assicura che il film esista nella tabella Film.
 * Se non c'è, lo scarica da TMDB e lo inserisce.
 * Restituisce l'ID interno del film (Film.ID).
 */
function ensureFilmExists(PDO $pdo, int $tmdb_id): int|false
{
    // 1. Cerca nel DB
    $stmt = $pdo->prepare("SELECT ID FROM Film WHERE TMDB_ID = ? LIMIT 1");
    $stmt->execute([$tmdb_id]);
    $id = $stmt->fetchColumn();
    if ($id !== false) return (int)$id;

    // 2. Non trovato → scarica da TMDB
    $ch = curl_init("https://api.themoviedb.org/3/movie/{$tmdb_id}?language=it-IT");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . TMDB_KEY, 'accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    $f = json_decode($resp ?: '{}', true) ?? [];
    if (empty($f['id'])) return false;

    // 3. Inserisci nella tabella Film
    $stmt = $pdo->prepare("
        INSERT INTO Film
            (TMDB_ID, Title, Original_Title, Original_Language,
             Overview, Release_Date, Runtime,
             Poster_Path, Backdrop_Path, Popularity, Vote_Average, Vote_Count)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $f['id'],
        $f['title']            ?? '',
        $f['original_title']   ?? null,
        $f['original_language'] ?? null,
        $f['overview']         ?? null,
        !empty($f['release_date']) ? $f['release_date'] : null,
        $f['runtime']          ?? null,
        $f['poster_path']      ?? null,
        $f['backdrop_path']    ?? null,
        $f['popularity']       ?? null,
        $f['vote_average']     ?? null,
        $f['vote_count']       ?? null,
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Legge il valore attuale di un campo booleano in Visione.
 * Restituisce null se la riga non esiste ancora.
 */
function getVisioneField(PDO $pdo, int $uid, int $film_id, string $field): ?bool
{
    $allowed = ['Is_Watched', 'In_Watchlist', 'Liked', 'Is_Favourite'];
    if (!in_array($field, $allowed, true)) return null;

    $stmt = $pdo->prepare("SELECT `{$field}` FROM Visione WHERE IDUtente = ? AND IDFilm = ? LIMIT 1");
    $stmt->execute([$uid, $film_id]);
    $val = $stmt->fetchColumn();
    return $val === false ? null : (bool)$val;
}

/**
 * Upsert generico su Visione.
 * Crea la riga se non esiste, aggiorna il campo se esiste.
 * Puoi passare un array di campi extra da settare insieme.
 */
function upsertVisione(PDO $pdo, int $uid, int $film_id, array $fields): void
{
    // Colonne accettate (whitelist per sicurezza)
    $allowed = ['Is_Watched', 'In_Watchlist', 'Liked', 'Rating', 'Is_Favourite'];
    $set = [];
    $vals = [];
    foreach ($fields as $col => $val) {
        if (!in_array($col, $allowed, true)) continue;
        $set[]  = "`{$col}` = ?";
        $vals[] = $val;
    }
    if (!$set) return;

    // Prima prova UPDATE
    $sql = "UPDATE Visione SET " . implode(', ', $set) . " WHERE IDUtente = ? AND IDFilm = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([...$vals, $uid, $film_id]);

    // Se nessuna riga aggiornata → INSERT
    if ($stmt->rowCount() === 0) {
        $cols = array_keys($fields);
        $colList = 'IDUtente, IDFilm, ' . implode(', ', array_map(fn($c) => "`{$c}`", $cols));
        $placeholders = implode(', ', array_fill(0, count($cols) + 2, '?'));
        $stmt = $pdo->prepare("INSERT INTO Visione ({$colList}) VALUES ({$placeholders})");
        $stmt->execute([$uid, $film_id, ...array_values($fields)]);
    }
}

// ═════════════════════════════════════════════
//  ROUTER AZIONI
// ═════════════════════════════════════════════

try {
    $film_id = ensureFilmExists($pdo, $tmdb_id);
    if ($film_id === false) {
        echo json_encode(['ok' => false, 'error' => 'Film non trovato su TMDB']);
        exit;
    }

    switch ($action) {

        // ── Toggle VISTO ─────────────────────
        case 'toggle_visto':
            $current = getVisioneField($pdo, $my_id, $film_id, 'Is_Watched');
            $nuovo   = !$current;   // inverte

            upsertVisione($pdo, $my_id, $film_id, ['Is_Watched' => (int)$nuovo]);

            // Se si toglie "visto", rimuove anche dalla watchlist (logica: non puoi averlo in watchlist se l'hai visto)
            // (opzionale — commenta se non vuoi questo comportamento)
            // if ($nuovo) upsertVisione($pdo, $my_id, $film_id, ['In_Watchlist' => 0]);

            echo json_encode(['ok' => true, 'stato' => $nuovo]);
            break;

        // ── Toggle WATCHLIST ─────────────────
        case 'toggle_watchlist':
            $current = getVisioneField($pdo, $my_id, $film_id, 'In_Watchlist');
            $nuovo   = !$current;

            upsertVisione($pdo, $my_id, $film_id, ['In_Watchlist' => (int)$nuovo]);

            echo json_encode(['ok' => true, 'stato' => $nuovo]);
            break;

        // ── Toggle LIKE ──────────────────────
        case 'toggle_like':
            $current = getVisioneField($pdo, $my_id, $film_id, 'Liked');
            $nuovo   = !$current;

            upsertVisione($pdo, $my_id, $film_id, ['Liked' => (int)$nuovo]);

            echo json_encode(['ok' => true, 'stato' => $nuovo]);
            break;

        // ── SET RATING ───────────────────────
        // Assegnare un voto implica la visione (come da consegna)
        case 'set_rating':
            if ($rating === null || $rating < 0.5 || $rating > 5.0) {
                echo json_encode(['ok' => false, 'error' => 'Voto non valido (0.5 – 5.0)']);
                break;
            }

            // Rating implica visione
            upsertVisione($pdo, $my_id, $film_id, [
                'Rating'     => $rating,
                'Is_Watched' => 1,
            ]);

            // Aggiorna (o inserisci) anche in Log per mantenere la storia
            // Controlla se esiste già un log per questo utente/film
            $stmt = $pdo->prepare(
                "SELECT ID FROM Log WHERE IDUtente = ? AND IDFilm = ? ORDER BY Data_Pubblicazione DESC LIMIT 1"
            );
            $stmt->execute([$my_id, $film_id]);
            $log_id = $stmt->fetchColumn();

            if ($log_id) {
                // Aggiorna voto nel log più recente
                $pdo->prepare("UPDATE Log SET Voto = ? WHERE ID = ?")
                    ->execute([$rating, $log_id]);
            } else {
                // Crea un log minimo (senza recensione testuale)
                $pdo->prepare("INSERT INTO Log (IDUtente, IDFilm, Data, Voto) VALUES (?, ?, CURDATE(), ?)")
                    ->execute([$my_id, $film_id, $rating]);
            }

            echo json_encode(['ok' => true, 'rating' => $rating]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => "Azione '{$action}' non riconosciuta"]);
    }

} catch (PDOException $e) {
    error_log('[GestioneFilm] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore database']);
}