<?php
    require 'Database.php';
    require 'QueryLogin.php';

    $error = '';
    $success = '';

    $username = '';
    $pdo = getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_conf = $_POST['password_conf'] ?? '';

        if (empty($username) || empty($email) || empty($password) || empty($password_conf)) {
            $error = 'Compila tutti i campi';
        } elseif (strlen($username) < 3) {
            $error = 'Username deve avere almeno 3 caratteri';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Formato email non valido';
        } elseif (strlen($password) < 6) {
            $error = 'Password deve avere almeno 6 caratteri';
        } elseif ($password !== $password_conf) {
            $error = 'Le password non coincidono';
        } else {
            $result = registraUtente($pdo, $username, $email, $password);
            
            if ($result['success']) {
                $success = 'Registrazione completata! Accedi ora.';
                $username = '';
            } else {
                $error = $result['error'];
            }
        }
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Registrazione</title>
    </head>
    <body>
        <h2>Registrazione</h2>

        <?php if ($error): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p style="color: green;"><?php echo htmlspecialchars($success); ?></p>
            <p><a href="login.php">Vai al login</a></p>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" 
                value="<?php echo htmlspecialchars($username); ?>" required>

            <input type="email" name="email" placeholder="Email" required>
            
            <input type="password" name="password" placeholder="Password" required>
            <input type="password" name="password_conf" placeholder="Conferma Password" required>
            
            <button type="submit">Registrati</button>
        </form>
    </body>
</html>