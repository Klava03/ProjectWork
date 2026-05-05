<?php
    require 'Database.php';
    require 'QueryLogin.php';

    $error   = '';
    $success = '';
    $username = '';

    $pdo = getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username      = trim($_POST['username'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $password      = $_POST['password'] ?? '';
        $password_conf = $_POST['password_conf'] ?? '';

        if (empty($username) || empty($email) || empty($password) || empty($password_conf)) {
            $error = 'Compila tutti i campi.';
        } elseif (strlen($username) < 3) {
            $error = 'Lo username deve avere almeno 3 caratteri.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Il formato email non è valido.';
        } elseif (strlen($password) < 6) {
            $error = 'La password deve avere almeno 6 caratteri.';
        } elseif ($password !== $password_conf) {
            $error = 'Le password non coincidono.';
        } else {
            $result = registraUtente($pdo, $username, $email, $password);
            if ($result['success']) {
                $success  = 'Registrazione completata! Puoi ora accedere.';
                $username = '';
            } else {
                $error = $result['error'];
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pulse — Registrati</title>
    <link rel="stylesheet" href="/Pulse/CSS/Auth.css">
</head>
<body>

<div class="auth-wrapper">

    <!-- PANNELLO VISIVO SX -->
    <div class="auth-visual">
        <div class="auth-brand">
            <div class="auth-logo"></div>
            <h1>Pulse</h1>
        </div>

        <div class="auth-tagline">
            <blockquote>
                Ogni film è un'emozione<br>
                che aspetta di essere<br>
                <em>condivisa</em>.
            </blockquote>
            <p>— Unisciti alla community</p>
        </div>
    </div>

    <!-- PANNELLO FORM DX -->
    <div class="auth-form-panel">
        <h2>Crea account</h2>
        <p class="auth-subtitle">Inizia a tracciare i film che ami.</p>

        <?php if ($error): ?>
            <div class="auth-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-success">
                <?= htmlspecialchars($success) ?>
                — <a href="/Pulse/login" style="color:inherit;font-weight:700;">Accedi ora →</a>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form class="auth-form" method="POST" action="">

            <div class="field-group">
                <label for="username">Username</label>
                <input
                    class="auth-input"
                    id="username"
                    type="text"
                    name="username"
                    placeholder="es. cinefilo_doc"
                    value="<?= htmlspecialchars($username) ?>"
                    required
                    autocomplete="username"
                >
            </div>

            <div class="field-group">
                <label for="email">Email</label>
                <input
                    class="auth-input"
                    id="email"
                    type="email"
                    name="email"
                    placeholder="tu@esempio.it"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                    autocomplete="email"
                >
            </div>

            <div class="field-row">
                <div class="field-group">
                    <label for="password">Password</label>
                    <input
                        class="auth-input"
                        id="password"
                        type="password"
                        name="password"
                        placeholder="••••••••"
                        required
                        autocomplete="new-password"
                    >
                </div>

                <div class="field-group">
                    <label for="password_conf">Conferma</label>
                    <input
                        class="auth-input"
                        id="password_conf"
                        type="password"
                        name="password_conf"
                        placeholder="••••••••"
                        required
                        autocomplete="new-password"
                    >
                </div>
            </div>

            <button class="auth-submit" type="submit">Registrati</button>
        </form>
        <?php endif; ?>

        <div class="auth-footer">
            Hai già un account?
            <a href="/Pulse/login">Accedi</a>
        </div>
    </div>

</div>

</body>
</html>