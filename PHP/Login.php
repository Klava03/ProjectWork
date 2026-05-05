<?php
    require 'Database.php';
    require 'QueryLogin.php';

    session_start();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $pdo = getConnection();

        $utente = getUtenteByUsername($pdo, $username);

        if ($utente && password_verify($password, $utente['Password'])) {
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $utente['ID'];
            $_SESSION['username'] = $utente['Username'];

            header('Location: Home.php');
            exit();
        } else {
            $errore = "Credenziali errate";
        }
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Login</title>
    </head>
    <body>
        <h2>Login</h2>
        
        <?php if (isset($errore)): ?>
            <p style="color: red;"><?php echo $errore; ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>

        <div>
            Non hai un account? <a href="Registrazione.php">Registrati qui</a>
        </div>
    </body>
</html>