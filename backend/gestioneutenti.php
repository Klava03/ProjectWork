<?php
/**
 * backend/GestioneUtenti.php
 *
 * Gestione delle interazioni social fra utenti (follow / unfollow).
 *
 * Azioni disponibili (POST JSON):
 *   follow   → aggiunge una riga in Segui          { user_id }
 *   unfollow → rimuove una riga da Segui           { user_id }
 *   stato    → ritorna se sto seguendo un utente   { user_id }
 *
 * Risposta: JSON { ok, following?, error? }
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
$raw    = file_get_contents('php://input');
$data   = json_decode($raw ?: '{}', true) ?? [];
$action = $data['action'] ?? '';

try {
    switch ($action) {

        // ── Segui un utente ──────────────────────
        case 'follow':
            $target_id = (int)($data['user_id'] ?? 0);
            if (!$target_id) {
                echo json_encode(['ok' => false, 'error' => 'Utente non specificato']);
                break;
            }
            if ($target_id === $my_id) {
                echo json_encode(['ok' => false, 'error' => 'Non puoi seguire te stesso']);
                break;
            }

            // Verifica che l'utente esista
            $stmt = $pdo->prepare("SELECT 1 FROM Utente WHERE ID = ? LIMIT 1");
            $stmt->execute([$target_id]);
            if (!$stmt->fetchColumn()) {
                echo json_encode(['ok' => false, 'error' => 'Utente non trovato']);
                break;
            }

            // INSERT IGNORE: se la riga esiste già non fa nulla, evita duplicati.
            // La PRIMARY KEY (IDSeguitore, IDSeguito) garantisce l'unicità.
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO Segui (IDSeguitore, IDSeguito) VALUES (?, ?)"
            );
            $stmt->execute([$my_id, $target_id]);

            echo json_encode(['ok' => true, 'following' => true]);
            break;

        // ── Smetti di seguire ────────────────────
        case 'unfollow':
            $target_id = (int)($data['user_id'] ?? 0);
            if (!$target_id) {
                echo json_encode(['ok' => false, 'error' => 'Utente non specificato']);
                break;
            }

            $stmt = $pdo->prepare(
                "DELETE FROM Segui WHERE IDSeguitore = ? AND IDSeguito = ?"
            );
            $stmt->execute([$my_id, $target_id]);

            echo json_encode(['ok' => true, 'following' => false]);
            break;

        // ── Stato (sto seguendo?) ────────────────
        case 'stato':
            $target_id = (int)($data['user_id'] ?? 0);
            if (!$target_id) {
                echo json_encode(['ok' => false, 'error' => 'Utente non specificato']);
                break;
            }

            $stmt = $pdo->prepare(
                "SELECT 1 FROM Segui WHERE IDSeguitore = ? AND IDSeguito = ? LIMIT 1"
            );
            $stmt->execute([$my_id, $target_id]);
            echo json_encode(['ok' => true, 'following' => (bool)$stmt->fetchColumn()]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => "Azione '{$action}' non riconosciuta"]);
    }
} catch (PDOException $e) {
    error_log('[GestioneUtenti] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore database']);
}