<?php
/**
 * backend/gestioneprofilo.php
 *
 * Gestisce le modifiche al profilo utente:
 *   update_username  → aggiorna Username in DB + sessione
 *   update_bio       → aggiorna Bio in DB
 *   update_avatar    → upload immagine in /Pulse/IMG/avatars/, salva path in DB
 *
 * Avatar: nel DB (Avatar_URL) viene salvato solo il NOME FILE (es. "user_3_1720000000.jpg")
 * Per visualizzarlo: "/Pulse/IMG/avatars/" . $row['Avatar_URL']
 * Se Avatar_URL è NULL → usa ui-avatars.com come fallback
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

$my_id = (int)$_SESSION['user_id'];
$pdo   = getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ══════════════════════════════════════════════
//  AVATAR UPLOAD  (multipart/form-data)
// ══════════════════════════════════════════════
if ($action === 'update_avatar') {
    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'Nessun file ricevuto']);
        exit;
    }

    $file   = $_FILES['avatar'];
    $maxMB  = 5;
    $maxBytes = $maxMB * 1024 * 1024;

    // Validazione dimensione
    if ($file['size'] > $maxBytes) {
        echo json_encode(['ok' => false, 'error' => "Il file supera i {$maxMB} MB"]);
        exit;
    }

    // Validazione MIME reale (non solo estensione)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeReal = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mimeReal, $allowed, true)) {
        echo json_encode(['ok' => false, 'error' => 'Formato non supportato. Usa JPG, PNG, WEBP o GIF']);
        exit;
    }

    // Estensione dal MIME reale
    $ext = match($mimeReal) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'jpg',
    };

    // Cartella di destinazione sul disco
    // Adatta questo path alla tua struttura reale
    $uploadDir = __DIR__ . '/../IMG/avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Elimina il vecchio avatar (se era un file locale)
    $stmtOld = $pdo->prepare("SELECT Avatar_URL FROM Utente WHERE ID = ?");
    $stmtOld->execute([$my_id]);
    $oldName = $stmtOld->fetchColumn();
    if ($oldName && !str_starts_with($oldName, 'http')) {
        $oldPath = $uploadDir . $oldName;
        if (file_exists($oldPath)) @unlink($oldPath);
    }

    // Nome univoco per il nuovo file
    $newName = 'user_' . $my_id . '_' . time() . '.' . $ext;
    $dest    = $uploadDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['ok' => false, 'error' => 'Impossibile salvare il file']);
        exit;
    }

    // Aggiorna DB: salviamo SOLO il nome file
    $pdo->prepare("UPDATE Utente SET Avatar_URL = ? WHERE ID = ?")
        ->execute([$newName, $my_id]);

    // Aggiorna la sessione con il path web completo
    $webPath = '/Pulse/IMG/avatars/' . $newName;
    $_SESSION['avatar_url'] = $webPath;

    echo json_encode(['ok' => true, 'avatar_url' => $webPath]);
    exit;
}

// ══════════════════════════════════════════════
//  UPDATE USERNAME / BIO  (JSON body)
// ══════════════════════════════════════════════
$raw  = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true) ?? [];
$action = $data['action'] ?? $action;

if ($action === 'update_username') {
    $username = trim($data['username'] ?? '');
    if (strlen($username) < 3 || strlen($username) > 50) {
        echo json_encode(['ok' => false, 'error' => 'Username deve essere tra 3 e 50 caratteri']);
        exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $username)) {
        echo json_encode(['ok' => false, 'error' => 'Username può contenere solo lettere, numeri, _ . -']);
        exit;
    }

    // Verifica unicità
    $check = $pdo->prepare("SELECT ID FROM Utente WHERE Username = ? AND ID != ?");
    $check->execute([$username, $my_id]);
    if ($check->fetchColumn()) {
        echo json_encode(['ok' => false, 'error' => 'Username già in uso']);
        exit;
    }

    $pdo->prepare("UPDATE Utente SET Username = ? WHERE ID = ?")->execute([$username, $my_id]);
    $_SESSION['username'] = $username;

    echo json_encode(['ok' => true, 'username' => $username]);
    exit;
}

if ($action === 'update_bio') {
    $bio = trim($data['bio'] ?? '');
    if (strlen($bio) > 255) {
        echo json_encode(['ok' => false, 'error' => 'Bio max 255 caratteri']);
        exit;
    }
    $pdo->prepare("UPDATE Utente SET Bio = ? WHERE ID = ?")->execute([$bio ?: null, $my_id]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Azione non riconosciuta']);