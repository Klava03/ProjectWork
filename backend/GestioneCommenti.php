<?php
/**
 * backend/GestioneCommenti.php
 *
 * Sistema commenti per Log, Post e Lista + Mi Piace.
 *
 * Azioni (POST JSON):
 *   lista_commenti     → { tipo: 'log'|'post'|'lista', id }
 *   aggiungi_commento  → { tipo, id, contenuto, parent_id? }
 *   elimina_commento   → { commento_id }
 *   toggle_like        → { tipo: 'log'|'post'|'lista'|'commento', id }
 *   conteggi           → { tipo: 'log'|'post'|'lista', id }
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

/* ── Helpers ──────────────────────────────────────────────────── */
function commAvatar(?string $val, string $username): string {
    if ($val && !str_starts_with($val, 'http')) return '/Pulse/IMG/avatars/' . $val;
    return $val ?? "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=8b5cf6&color=fff&size=80";
}

function commTimeAgo(string $ts): string {
    $d = time() - strtotime($ts);
    if ($d < 60)     return 'ora';
    if ($d < 3600)   return floor($d/60) . 'm';
    if ($d < 86400)  return floor($d/3600) . 'h';
    if ($d < 604800) return floor($d/86400) . 'g';
    return date('d/m/Y', strtotime($ts));
}

/* Mappa tipo → colonna DB */
function tipoToCol(string $tipo): ?string {
    return match($tipo) {
        'log'      => 'IDLog',
        'post'     => 'IDPost',
        'lista'    => 'IDLista',
        'commento' => 'IDCommento',
        default    => null,
    };
}

/* Mappa tipo → tabella per verifica esistenza */
function tipoToTbl(string $tipo): ?string {
    return match($tipo) {
        'log'      => 'Log',
        'post'     => 'Post',
        'lista'    => 'Lista',
        'commento' => 'Commento',
        default    => null,
    };
}

/* PK della tabella target */
function tblPK(string $tipo): string {
    return match($tipo) {
        'lista' => 'IDLista',
        default => 'ID',
    };
}

try {
    switch ($action) {

        /* ────────────────────────────────────────────────
           LISTA COMMENTI (con thread annidati a 1 livello)
           ──────────────────────────────────────────────── */
        case 'lista_commenti': {
            $tipo = $data['tipo'] ?? '';
            $id   = (int)($data['id'] ?? 0);
            $col  = tipoToCol($tipo);
            if (!$col || !$id || $tipo === 'commento') {
                echo json_encode(['ok'=>false,'error'=>'Parametri non validi']); break;
            }

            $stmt = $pdo->prepare("
                SELECT C.ID, C.Contenuto, C.Data, C.IDCommentoPadre,
                       U.ID AS user_id, U.Username, U.Avatar_URL,
                       (SELECT COUNT(*) FROM MiPiace M WHERE M.IDCommento = C.ID) AS likes,
                       (SELECT COUNT(*) > 0 FROM MiPiace M WHERE M.IDCommento = C.ID AND M.IDUtente = ?) AS i_liked
                FROM Commento C
                JOIN Utente U ON C.IDUtente = U.ID
                WHERE C.{$col} = ?
                ORDER BY C.Data ASC
            ");
            $stmt->execute([$my_id, $id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $commenti = array_map(fn($r) => [
                'id'        => (int)$r['ID'],
                'contenuto' => $r['Contenuto'],
                'parent_id' => $r['IDCommentoPadre'] ? (int)$r['IDCommentoPadre'] : null,
                'user_id'   => (int)$r['user_id'],
                'username'  => $r['Username'],
                'avatar'    => commAvatar($r['Avatar_URL'], $r['Username']),
                'ago'       => commTimeAgo($r['Data']),
                'likes'     => (int)$r['likes'],
                'i_liked'   => (bool)$r['i_liked'],
                'is_mine'   => ((int)$r['user_id'] === $my_id),
            ], $rows);

            echo json_encode(['ok'=>true, 'commenti'=>$commenti]);
            break;
        }

        /* ────────────────────────────────────────────────
           AGGIUNGI COMMENTO
           ──────────────────────────────────────────────── */
        case 'aggiungi_commento': {
            $tipo      = $data['tipo'] ?? '';
            $id        = (int)($data['id'] ?? 0);
            $contenuto = trim($data['contenuto'] ?? '');
            $parent_id = (int)($data['parent_id'] ?? 0) ?: null;
            $col       = tipoToCol($tipo);
            $tbl       = tipoToTbl($tipo);
            $pk        = tblPK($tipo);

            if (!$col || !$tbl || !$id || $tipo === 'commento') {
                echo json_encode(['ok'=>false,'error'=>'Parametri non validi']); break;
            }
            if (mb_strlen($contenuto) < 1) {
                echo json_encode(['ok'=>false,'error'=>'Il commento è vuoto']); break;
            }
            if (mb_strlen($contenuto) > 2000) $contenuto = mb_substr($contenuto, 0, 2000);

            // Verifica target
            $chk = $pdo->prepare("SELECT 1 FROM {$tbl} WHERE {$pk} = ?");
            $chk->execute([$id]);
            if (!$chk->fetchColumn()) {
                echo json_encode(['ok'=>false,'error'=>ucfirst($tipo).' non trovato']); break;
            }

            // Verifica commento padre
            if ($parent_id) {
                $chk = $pdo->prepare("SELECT 1 FROM Commento WHERE ID = ? AND {$col} = ?");
                $chk->execute([$parent_id, $id]);
                if (!$chk->fetchColumn()) {
                    echo json_encode(['ok'=>false,'error'=>'Commento padre non valido']); break;
                }
            }

            // Colonne dinamiche
            $idLog   = $tipo === 'log'   ? $id : null;
            $idPost  = $tipo === 'post'  ? $id : null;
            $idLista = $tipo === 'lista' ? $id : null;

            $stmt = $pdo->prepare("
                INSERT INTO Commento (IDUtente, Contenuto, IDLog, IDPost, IDLista, IDCommentoPadre)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$my_id, $contenuto, $idLog, $idPost, $idLista, $parent_id]);
            $cid = (int)$pdo->lastInsertId();

            // Notifica al proprietario del target (se non è l'utente stesso)
            try {
                $ownerSql = match($tipo) {
                    'log'   => "SELECT IDUtente FROM Log   WHERE ID = ?",
                    'post'  => "SELECT IDUtente FROM Post  WHERE ID = ?",
                    'lista' => "SELECT IDUtente FROM Lista WHERE IDLista = ?",
                    default => null,
                };
                if ($ownerSql) {
                    $os = $pdo->prepare($ownerSql);
                    $os->execute([$id]);
                    $owner_id = (int)($os->fetchColumn() ?: 0);
                    if ($owner_id && $owner_id !== $my_id) {
                        // Inserisce notifica se tabella esiste (catch silenzioso se no)
                        try {
                            $pdo->prepare("
                                INSERT IGNORE INTO Notifica (IDDestinatario, IDMittente, Tipo, IDRiferimento)
                                VALUES (?, ?, 'commento', ?)
                            ")->execute([$owner_id, $my_id, $cid]);
                        } catch (PDOException) {}
                    }
                }
            } catch (PDOException) {}

            // Ritorna il commento appena creato
            $stmt = $pdo->prepare("
                SELECT C.ID, C.Contenuto, C.Data, C.IDCommentoPadre,
                       U.ID AS user_id, U.Username, U.Avatar_URL
                FROM Commento C JOIN Utente U ON C.IDUtente = U.ID
                WHERE C.ID = ?
            ");
            $stmt->execute([$cid]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['ok'=>true, 'commento'=>[
                'id'        => (int)$r['ID'],
                'contenuto' => $r['Contenuto'],
                'parent_id' => $r['IDCommentoPadre'] ? (int)$r['IDCommentoPadre'] : null,
                'user_id'   => (int)$r['user_id'],
                'username'  => $r['Username'],
                'avatar'    => commAvatar($r['Avatar_URL'], $r['Username']),
                'ago'       => 'ora',
                'likes'     => 0,
                'i_liked'   => false,
                'is_mine'   => true,
            ]]);
            break;
        }

        /* ────────────────────────────────────────────────
           ELIMINA COMMENTO
           ──────────────────────────────────────────────── */
        case 'elimina_commento': {
            $cid = (int)($data['commento_id'] ?? 0);
            if (!$cid) { echo json_encode(['ok'=>false,'error'=>'Commento non specificato']); break; }

            $stmt = $pdo->prepare("DELETE FROM Commento WHERE ID = ? AND IDUtente = ?");
            $stmt->execute([$cid, $my_id]);

            echo $stmt->rowCount()
                ? json_encode(['ok' => true])
                : json_encode(['ok'=>false,'error'=>'Non autorizzato']);
            break;
        }

        /* ────────────────────────────────────────────────
           TOGGLE LIKE su qualsiasi target
           ──────────────────────────────────────────────── */
        case 'toggle_like': {
            $tipo = $data['tipo'] ?? '';
            $id   = (int)($data['id'] ?? 0);
            $col  = tipoToCol($tipo);
            $tbl  = tipoToTbl($tipo);
            $pk   = tblPK($tipo);

            if (!$col || !$tbl || !$id) {
                echo json_encode(['ok'=>false,'error'=>'Parametri non validi']); break;
            }

            // Verifica target
            $chk = $pdo->prepare("SELECT 1 FROM {$tbl} WHERE {$pk} = ?");
            $chk->execute([$id]);
            if (!$chk->fetchColumn()) {
                echo json_encode(['ok'=>false,'error'=>'Elemento non trovato']); break;
            }

            // Esiste già?
            $chk = $pdo->prepare("SELECT 1 FROM MiPiace WHERE IDUtente = ? AND {$col} = ? LIMIT 1");
            $chk->execute([$my_id, $id]);
            $already = (bool)$chk->fetchColumn();

            if ($already) {
                $pdo->prepare("DELETE FROM MiPiace WHERE IDUtente = ? AND {$col} = ?")->execute([$my_id, $id]);
                $liked = false;
            } else {
                $pdo->prepare("INSERT INTO MiPiace (IDUtente, {$col}) VALUES (?, ?)")->execute([$my_id, $id]);
                $liked = true;
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM MiPiace WHERE {$col} = ?");
            $stmt->execute([$id]);
            echo json_encode(['ok'=>true, 'liked'=>$liked, 'count'=>(int)$stmt->fetchColumn()]);
            break;
        }

        /* ────────────────────────────────────────────────
           CONTEGGI rapidi
           ──────────────────────────────────────────────── */
        case 'conteggi': {
            $tipo = $data['tipo'] ?? '';
            $id   = (int)($data['id'] ?? 0);
            $col  = tipoToCol($tipo);
            if (!$col || !$id || $tipo === 'commento') {
                echo json_encode(['ok'=>false,'error'=>'Parametri non validi']); break;
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM MiPiace  WHERE {$col} = ?");
            $stmt->execute([$id]);
            $likes = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Commento WHERE {$col} = ?");
            $stmt->execute([$id]);
            $comm = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) > 0 FROM MiPiace WHERE {$col} = ? AND IDUtente = ?");
            $stmt->execute([$id, $my_id]);
            $i_liked = (bool)$stmt->fetchColumn();

            echo json_encode(['ok'=>true, 'likes'=>$likes, 'commenti'=>$comm, 'i_liked'=>$i_liked]);
            break;
        }

        default:
            echo json_encode(['ok' => false, 'error' => "Azione '{$action}' non riconosciuta"]);
    }
} catch (PDOException $e) {
    error_log('[GestioneCommenti] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore database']);
}