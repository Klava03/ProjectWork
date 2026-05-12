<?php
/**
 * backend/GestionePreferiti.php
 *
 * Gestione dei "Film Preferiti" mostrati nel profilo (max 5 slot).
 *
 * Azioni disponibili (POST JSON):
 *   cerca_film         → ricerca su TMDB                    { q }
 *   aggiungi_preferito → marca un film come preferito       { tmdb_id }
 *   rimuovi_preferito  → smarca un film come preferito      { tmdb_id }
 *
 * Risposta: JSON { ok, ..., error? }
 *
 * NOTA — logica richiesta:
 *   1. Si controlla se in Visione esiste già la riga (IDUtente, IDFilm).
 *   2. Se esiste  → UPDATE   Is_Favourite = 1
 *   3. Se NON esiste → INSERT con il SOLO valore Is_Favourite = 1
 *      (gli altri campi restano NULL).
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

const PREF_TMDB_KEY = "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJiY2U5ZWZmYjRjMDA5ZTNmNGZmZTM1N2QzOTlhMTk4NSIsIm5iZiI6MTc2OTc4NDc2MC43NzcsInN1YiI6IjY5N2NjNWI4MWVhOGNmMGRlZTI2YWVmNSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.cPNGTwdDeQD6TGYY2Shn-i3Y0i-OjMSey9KPpdrpBAE";

const MAX_PREFERITI = 5;

// ── Helper: assicura che il film sia nella tabella Film ──────────
function ensureFilmExistsPref(PDO $pdo, int $tmdb_id): int|false
{
    $stmt = $pdo->prepare("SELECT ID FROM Film WHERE TMDB_ID = ? LIMIT 1");
    $stmt->execute([$tmdb_id]);
    $id = $stmt->fetchColumn();
    if ($id !== false) return (int)$id;

    $ch = curl_init("https://api.themoviedb.org/3/movie/{$tmdb_id}?language=it-IT");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . PREF_TMDB_KEY,
            'accept: application/json',
        ],
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
        $f['id'],
        $f['title']            ?? '',
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
    return (int)$pdo->lastInsertId();
}

try {
    switch ($action) {

        // ── Ricerca film su TMDB ──────────────────
        case 'cerca_film':
            $q = trim($data['q'] ?? '');
            if (mb_strlen($q) < 2) {
                echo json_encode(['ok' => true, 'films' => []]);
                break;
            }

            $ch = curl_init("https://api.themoviedb.org/3/search/movie?query=" . urlencode($q) . "&language=it-IT");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . PREF_TMDB_KEY,
                    'accept: application/json',
                ],
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

        // ── Aggiungi film ai preferiti ────────────
        case 'aggiungi_preferito':
            $tmdb_id = (int)($data['tmdb_id'] ?? 0);
            if (!$tmdb_id) {
                echo json_encode(['ok' => false, 'error' => 'Film non specificato']);
                break;
            }

            $film_id = ensureFilmExistsPref($pdo, $tmdb_id);
            if (!$film_id) {
                echo json_encode(['ok' => false, 'error' => 'Film non trovato su TMDB']);
                break;
            }

            // Verifica se esiste già una riga in Visione per questa coppia.
            // È IL CONTROLLO RICHIESTO: se sì → UPDATE, altrimenti INSERT con
            // solo Is_Favourite settato.
            $stmt = $pdo->prepare(
                "SELECT Is_Favourite FROM Visione
                 WHERE IDUtente = ? AND IDFilm = ? LIMIT 1"
            );
            $stmt->execute([$my_id, $film_id]);
            $row    = $stmt->fetch(PDO::FETCH_ASSOC);
            $exists = $row !== false;
            $isAlreadyFav = $exists && (int)$row['Is_Favourite'] === 1;

            // Se non è già preferito, controlla che non superi il limite di 5.
            if (!$isAlreadyFav) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM Visione
                     WHERE IDUtente = ? AND Is_Favourite = 1"
                );
                $stmt->execute([$my_id]);
                $countFav = (int)$stmt->fetchColumn();
                if ($countFav >= MAX_PREFERITI) {
                    echo json_encode([
                        'ok'    => false,
                        'error' => 'Hai già raggiunto il limite di ' . MAX_PREFERITI . ' film preferiti',
                    ]);
                    break;
                }
            }

            if ($exists) {
                // Riga già presente → metto a TRUE il booleano.
                $pdo->prepare(
                    "UPDATE Visione SET Is_Favourite = 1
                     WHERE IDUtente = ? AND IDFilm = ?"
                )->execute([$my_id, $film_id]);
            } else {
                // Riga assente → la creo con il SOLO valore Is_Favourite = 1.
                // Tutti gli altri campi (Is_Watched, In_Watchlist, Liked, Rating)
                // restano NULL come da default dello schema.
                $pdo->prepare(
                    "INSERT INTO Visione (IDUtente, IDFilm, Is_Favourite)
                     VALUES (?, ?, 1)"
                )->execute([$my_id, $film_id]);
            }

            // Recupero i dati del film per restituirli al frontend
            $stmt = $pdo->prepare(
                "SELECT ID, TMDB_ID, Title, Poster_Path, Release_Date
                 FROM Film WHERE ID = ?"
            );
            $stmt->execute([$film_id]);
            $film = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'film' => $film]);
            break;

        // ── Rimuovi film dai preferiti ─────────────
        case 'rimuovi_preferito':
            $tmdb_id = (int)($data['tmdb_id'] ?? 0);
            if (!$tmdb_id) {
                echo json_encode(['ok' => false, 'error' => 'Film non specificato']);
                break;
            }

            $stmt = $pdo->prepare("SELECT ID FROM Film WHERE TMDB_ID = ? LIMIT 1");
            $stmt->execute([$tmdb_id]);
            $film_id = $stmt->fetchColumn();
            if (!$film_id) {
                echo json_encode(['ok' => false, 'error' => 'Film non trovato']);
                break;
            }

            $pdo->prepare(
                "UPDATE Visione SET Is_Favourite = 0
                 WHERE IDUtente = ? AND IDFilm = ?"
            )->execute([$my_id, (int)$film_id]);

            echo json_encode(['ok' => true]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => "Azione '{$action}' non riconosciuta"]);
    }
} catch (PDOException $e) {
    error_log('[GestionePreferiti] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore database']);
}