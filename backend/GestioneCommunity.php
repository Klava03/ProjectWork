<?php
/**
 * backend/GestioneCommunity.php
 *
 * Azioni (POST JSON):
 *   generi_liberi      → genera lista generi senza community
 *   crea_community     → { idGenere, nome, descrizione }
 *   iscriviti          → { community_id }
 *   disiscriviti       → { community_id }
 *   crea_post          → { community_id (opz.), contenuto }
 *   elimina_post       → { post_id }
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
$action = $data['action'] ?? $_GET['action'] ?? '';

/* ── Bootstrap generi TMDB se la tabella è vuota ─────────────── */
function ensureGeneriSeed(PDO $pdo): void {
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM Genere")->fetchColumn();
    if ($cnt > 0) return;
    $seed = [
        28=>'Azione', 12=>'Avventura', 16=>'Animazione', 35=>'Commedia',
        80=>'Crime',  99=>'Documentario', 18=>'Dramma', 10751=>'Famiglia',
        14=>'Fantasy', 36=>'Storico', 27=>'Horror', 10402=>'Musica',
        9648=>'Mistero', 10749=>'Romantico', 878=>'Fantascienza',
        10770=>'Film TV', 53=>'Thriller', 10752=>'Guerra', 37=>'Western',
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO Genere (ID, Name) VALUES (?, ?)");
    foreach ($seed as $id => $name) $stmt->execute([$id, $name]);
}

try {
    switch ($action) {

        /* ────────────────────────────────────────────────
           LISTA GENERI SENZA COMMUNITY (per il modal "Crea")
           ──────────────────────────────────────────────── */
        case 'generi_liberi':
            ensureGeneriSeed($pdo);
            $stmt = $pdo->query("
                SELECT G.ID, G.Name
                FROM Genere G
                LEFT JOIN Community C ON C.IDGenere = G.ID
                WHERE C.ID IS NULL
                ORDER BY G.Name ASC
            ");
            echo json_encode(['ok' => true, 'generi' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        /* ────────────────────────────────────────────────
           CREA COMMUNITY (e iscrivi automaticamente il creatore)
           ──────────────────────────────────────────────── */
        case 'crea_community':
            ensureGeneriSeed($pdo);
            $idGenere = (int)($data['idGenere'] ?? 0);
            $nome     = trim($data['nome'] ?? '');
            $desc     = trim($data['descrizione'] ?? '');

            if (!$idGenere)      { echo json_encode(['ok'=>false,'error'=>'Seleziona un genere']); break; }
            if ($nome === '')    { echo json_encode(['ok'=>false,'error'=>'Il nome è obbligatorio']); break; }
            if (mb_strlen($nome) > 100) $nome = mb_substr($nome, 0, 100);

            // Verifica che il genere esista e non abbia già una community
            $chk = $pdo->prepare("SELECT 1 FROM Genere WHERE ID = ?");
            $chk->execute([$idGenere]);
            if (!$chk->fetchColumn()) { echo json_encode(['ok'=>false,'error'=>'Genere non valido']); break; }

            $chk = $pdo->prepare("SELECT ID FROM Community WHERE IDGenere = ?");
            $chk->execute([$idGenere]);
            if ($chk->fetchColumn()) { echo json_encode(['ok'=>false,'error'=>'Esiste già una community per questo genere']); break; }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO Community (IDGenere, Nome, Descrizione) VALUES (?, ?, ?)");
            $stmt->execute([$idGenere, $nome, $desc ?: null]);
            $cid = (int)$pdo->lastInsertId();

            // Iscrizione automatica del creatore
            $pdo->prepare("INSERT IGNORE INTO Iscrizione_Community (IDUtente, IDCommunity) VALUES (?, ?)")
                ->execute([$my_id, $cid]);
            $pdo->commit();

            echo json_encode(['ok' => true, 'community_id' => $cid]);
            break;

        /* ────────────────────────────────────────────────
           ISCRIVITI
           ──────────────────────────────────────────────── */
        case 'iscriviti':
            $cid = (int)($data['community_id'] ?? 0);
            if (!$cid) { echo json_encode(['ok'=>false,'error'=>'Community non specificata']); break; }

            $chk = $pdo->prepare("SELECT 1 FROM Community WHERE ID = ?");
            $chk->execute([$cid]);
            if (!$chk->fetchColumn()) { echo json_encode(['ok'=>false,'error'=>'Community inesistente']); break; }

            $pdo->prepare("INSERT IGNORE INTO Iscrizione_Community (IDUtente, IDCommunity) VALUES (?, ?)")
                ->execute([$my_id, $cid]);
            echo json_encode(['ok' => true, 'iscritto' => true]);
            break;

        /* ────────────────────────────────────────────────
           DISISCRIVITI
           ──────────────────────────────────────────────── */
        case 'disiscriviti':
            $cid = (int)($data['community_id'] ?? 0);
            if (!$cid) { echo json_encode(['ok'=>false,'error'=>'Community non specificata']); break; }

            $pdo->prepare("DELETE FROM Iscrizione_Community WHERE IDUtente = ? AND IDCommunity = ?")
                ->execute([$my_id, $cid]);
            echo json_encode(['ok' => true, 'iscritto' => false]);
            break;

        /* ────────────────────────────────────────────────
           CREA POST (in community o "globale")
           ──────────────────────────────────────────────── */
        case 'crea_post':
            $contenuto = trim($data['contenuto'] ?? '');
            $cid       = (int)($data['community_id'] ?? 0) ?: null;

            if (mb_strlen($contenuto) < 1) { echo json_encode(['ok'=>false,'error'=>'Il post non può essere vuoto']); break; }
            if (mb_strlen($contenuto) > 5000) $contenuto = mb_substr($contenuto, 0, 5000);

            // Se è in una community, devo esserne iscritto
            if ($cid !== null) {
                $chk = $pdo->prepare("SELECT 1 FROM Iscrizione_Community WHERE IDUtente = ? AND IDCommunity = ?");
                $chk->execute([$my_id, $cid]);
                if (!$chk->fetchColumn()) {
                    echo json_encode(['ok'=>false,'error'=>'Devi iscriverti alla community per pubblicare']);
                    break;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO Post (IDUtente, IDCommunity, Contenuto) VALUES (?, ?, ?)");
            $stmt->execute([$my_id, $cid, $contenuto]);
            echo json_encode(['ok' => true, 'post_id' => (int)$pdo->lastInsertId()]);
            break;

        /* ────────────────────────────────────────────────
           ELIMINA POST (solo proprio)
           ──────────────────────────────────────────────── */
        case 'elimina_post':
            $pid = (int)($data['post_id'] ?? 0);
            if (!$pid) { echo json_encode(['ok'=>false,'error'=>'Post non specificato']); break; }

            $stmt = $pdo->prepare("DELETE FROM Post WHERE ID = ? AND IDUtente = ?");
            $stmt->execute([$pid, $my_id]);

            if ($stmt->rowCount() === 0) {
                echo json_encode(['ok'=>false,'error'=>'Non autorizzato o post inesistente']);
            } else {
                echo json_encode(['ok' => true]);
            }
            break;

        default:
            echo json_encode(['ok' => false, 'error' => "Azione '{$action}' non riconosciuta"]);
    }

} catch (PDOException $e) {
    error_log('[GestioneCommunity] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore database']);
}