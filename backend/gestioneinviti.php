<?php
// backend/gestioneinviti.php
// Azioni: invia_invito | accetta | rifiuta | lista_amici

session_start();
require_once __DIR__ . '/../pages/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Non autenticato']);
    exit;
}

$pdo   = getConnection();
$my_id = (int)$_SESSION['user_id'];

$raw  = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true) ?? [];

$action = trim($data['action'] ?? $_GET['action'] ?? '');

try {
    switch ($action) {

        // ══════════════════════════════════════════════
        //  INVIA INVITO
        //  body: { action, lista_id, user_id }
        // ══════════════════════════════════════════════
        case 'invia_invito':
            $lista_id   = (int)($data['lista_id'] ?? 0);
            $invitato_id = (int)($data['user_id']  ?? 0);

            if (!$lista_id || !$invitato_id) {
                echo json_encode(['ok' => false, 'error' => 'Dati mancanti']); break;
            }

            // Verifica che la lista appartenga a chi invita
            $s = $pdo->prepare("SELECT IDUtente FROM Lista WHERE IDLista = ? LIMIT 1");
            $s->execute([$lista_id]);
            $owner = $s->fetchColumn();
            if ((int)$owner !== $my_id) {
                echo json_encode(['ok' => false, 'error' => 'Non sei il proprietario della lista']); break;
            }

            // Verifica che l'invitato non sia già membro
            $s = $pdo->prepare("SELECT 1 FROM Lista_Membro WHERE IDLista = ? AND IDUtente = ? LIMIT 1");
            $s->execute([$lista_id, $invitato_id]);
            if ($s->fetchColumn()) {
                echo json_encode(['ok' => false, 'error' => 'Utente già membro della lista']); break;
            }

            // Inserisci o ignora se già esiste un invito pending
            $s = $pdo->prepare("
                INSERT INTO Lista_Invito (IDLista, IDInvitante, IDInvitato, Stato)
                VALUES (?, ?, ?, 'pending')
                ON DUPLICATE KEY UPDATE
                    Stato = IF(Stato = 'rifiutato', 'pending', Stato),
                    Data  = IF(Stato = 'rifiutato', NOW(), Data)
            ");
            $s->execute([$lista_id, $my_id, $invitato_id]);

            // Crea notifica
            $s = $pdo->prepare("
                INSERT INTO Notifica (IDDestinatario, IDMittente, Tipo, IDRiferimento)
                VALUES (?, ?, 'invito_lista', ?)
            ");
            $s->execute([$invitato_id, $my_id, $lista_id]);

            // Segna la lista come condivisa
            $pdo->prepare("UPDATE Lista SET Condivisa = 1 WHERE IDLista = ?")->execute([$lista_id]);

            echo json_encode(['ok' => true]);
            break;

        // ══════════════════════════════════════════════
        //  ACCETTA INVITO
        //  body: { action, invito_id }
        // ══════════════════════════════════════════════
        case 'accetta':
            $invito_id = (int)($data['invito_id'] ?? 0);
            if (!$invito_id) { echo json_encode(['ok' => false, 'error' => 'ID invito mancante']); break; }

            // Carica invito e verifica che sia per me
            $s = $pdo->prepare("SELECT * FROM Lista_Invito WHERE ID = ? AND IDInvitato = ? AND Stato = 'pending' LIMIT 1");
            $s->execute([$invito_id, $my_id]);
            $invito = $s->fetch(PDO::FETCH_ASSOC);

            if (!$invito) {
                echo json_encode(['ok' => false, 'error' => 'Invito non trovato o già gestito']); break;
            }

            // Aggiorna stato invito
            $pdo->prepare("UPDATE Lista_Invito SET Stato = 'accettato' WHERE ID = ?")
                ->execute([$invito_id]);

            // Aggiungi come membro
            $pdo->prepare("INSERT IGNORE INTO Lista_Membro (IDLista, IDUtente) VALUES (?, ?)")
                ->execute([$invito['IDLista'], $my_id]);

            echo json_encode(['ok' => true, 'lista_id' => $invito['IDLista']]);
            break;

        // ══════════════════════════════════════════════
        //  RIFIUTA INVITO
        //  body: { action, invito_id }
        // ══════════════════════════════════════════════
        case 'rifiuta':
            $invito_id = (int)($data['invito_id'] ?? 0);
            if (!$invito_id) { echo json_encode(['ok' => false, 'error' => 'ID invito mancante']); break; }

            $s = $pdo->prepare("
                UPDATE Lista_Invito SET Stato = 'rifiutato'
                WHERE ID = ? AND IDInvitato = ? AND Stato = 'pending'
            ");
            $s->execute([$invito_id, $my_id]);

            if ($s->rowCount() === 0) {
                echo json_encode(['ok' => false, 'error' => 'Invito non trovato o già gestito']); break;
            }

            echo json_encode(['ok' => true]);
            break;

        // ══════════════════════════════════════════════
        //  LISTA AMICI INVITABILI
        //  GET: ?action=lista_amici&lista_id=X
        //  Restituisce: seguiti reciproci non ancora invitati/membri
        // ══════════════════════════════════════════════
        case 'lista_amici':
            $lista_id = (int)($_GET['lista_id'] ?? $data['lista_id'] ?? 0);
            if (!$lista_id) { echo json_encode(['ok' => false, 'error' => 'lista_id mancante']); break; }

            // Solo il proprietario può vedere la lista amici
            $s = $pdo->prepare("SELECT IDUtente FROM Lista WHERE IDLista = ? LIMIT 1");
            $s->execute([$lista_id]);
            if ((int)$s->fetchColumn() !== $my_id) {
                echo json_encode(['ok' => false, 'error' => 'Non autorizzato']); break;
            }

            // Amici = seguiti reciproci (io seguo loro E loro seguono me)
            $stmt = $pdo->prepare("
                SELECT
                    U.ID,
                    U.Username,
                    U.Avatar_URL,
                    COALESCE(LI.Stato, 'nessuno') AS stato_invito
                FROM Segui S1
                JOIN Segui S2 ON S2.IDSeguitore = S1.IDSeguito AND S2.IDSeguito = S1.IDSeguitore
                JOIN Utente U ON U.ID = S1.IDSeguito
                LEFT JOIN Lista_Invito LI
                    ON LI.IDLista = :lista_id AND LI.IDInvitato = U.ID
                LEFT JOIN Lista_Membro LM
                    ON LM.IDLista = :lista_id2 AND LM.IDUtente = U.ID
                WHERE S1.IDSeguitore = :my_id
                  AND LM.IDUtente IS NULL   -- non ancora membro
                ORDER BY U.Username ASC
            ");
            $stmt->execute([
                ':lista_id'  => $lista_id,
                ':lista_id2' => $lista_id,
                ':my_id'     => $my_id,
            ]);
            $amici = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Risolvi avatar URL
            foreach ($amici as &$a) {
                $av = $a['Avatar_URL'];
                if ($av && !str_starts_with($av, 'http'))
                    $a['Avatar_URL'] = '/Pulse/IMG/avatars/' . $av;
                elseif (!$av)
                    $a['Avatar_URL'] = "https://ui-avatars.com/api/?name=" . urlencode($a['Username']) . "&background=8b5cf6&color=fff&size=80";
            }
            unset($a);

            echo json_encode(['ok' => true, 'amici' => $amici]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => "Azione '$action' sconosciuta"]);
    }
} catch (PDOException $e) {
    error_log('[GestioneInviti] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore database']);
}