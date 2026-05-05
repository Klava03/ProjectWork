<?php
    require 'Database.php';
    require 'QueryLogin.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $pdo = getConnection();

        $utente = getUtenteByUsername($pdo, $username);

        if ($utente && password_verify($password, $utente['Password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $utente['ID'];
            $_SESSION['username']   = $utente['Username'];
            $_SESSION['avatar_url'] = "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['username']) . "&background=8b5cf6&color=ffffff";

            header('Location: /Pulse/home');
            exit();
        } else {
            $errore = "Credenziali non valide. Riprova.";
        }
    }
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pulse — Accedi</title>
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
                Il cinema non è uno specchio,<br>
                è un <em>martello</em>.
            </blockquote>
            <p>— Werner Herzog</p>
        </div>
    </div>

    <!-- PANNELLO FORM DX -->
    <div class="auth-form-panel">
        <h2>Bentornato</h2>
        <p class="auth-subtitle">Accedi al tuo account Pulse.</p>

        <?php if (isset($errore)): ?>
            <div class="auth-error"><?= htmlspecialchars($errore) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="POST" action="">

            <div class="field-group">
                <label for="username">Username</label>
                <input
                    class="auth-input"
                    id="username"
                    type="text"
                    name="username"
                    placeholder="Il tuo username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    required
                    autocomplete="username"
                >
            </div>

            <div class="field-group">
                <label for="password">Password</label>
                <input
                    class="auth-input"
                    id="password"
                    type="password"
                    name="password"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button class="auth-submit" type="submit">Accedi</button>
        </form>

        <div class="auth-footer">
            Non hai un account?
            <a href="/Pulse/register">Registrati</a>
        </div>
    </div>

</div>

</body>
</html>