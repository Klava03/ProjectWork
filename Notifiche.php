<?php
session_start();
require 'Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

$pdo = getConnection();
$my_id = $_SESSION['user_id'];

// Query per recuperare i nuovi Follower (Notifiche di tipo "Follow")
try {
    $stmt = $pdo->prepare("
        SELECT U.Username, U.Avatar_URL, S.Data_Seguimento as Data, 'follow' as Tipo 
        FROM Segui S 
        JOIN Utente U ON S.IDSeguitore = U.ID 
        WHERE S.IDSeguito = ? 
        ORDER BY S.Data_Seguimento DESC LIMIT 20
    ");
    $stmt->execute([$my_id]);
    $notifiche = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Errore nel caricamento notifiche: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Pulse • Notifiche</title>
    <link rel="stylesheet" href="../CSS/Home.css">
    <link rel="stylesheet" href="../CSS/Notifiche.css">
</head>
<body>

<div class="app">
    <aside class="left">
        <div class="brand"><div class="logo"></div><h1>Pulse</h1></div>
        <nav class="nav">
            <a href="home.php"><span class="ico">⌂</span><span class="label">Home</span></a>
            <a href="cerca.php"><span class="ico">⌕</span><span class="label">Cerca</span></a>
            <a href="community.php"><span class="ico">👥</span><span class="label">Community</span></a>
            <a href="crea.php"><span class="ico">＋</span><span class="label">Crea</span></a>
            <a class="active" href="notifiche.php"><span class="ico">🔔</span><span class="label">Notifiche</span></a>
            <a href="liste.php"><span class="ico">≡</span><span class="label">Liste</span></a>
            <a href="recensioni.php"><span class="ico">✍</span><span class="label">Recensioni</span></a>
            <a href="profilo.php"><span class="ico">☺</span><span class="label">Profilo</span></a>
        </nav>
    </aside>

    <main class="center">
        <header class="page-header">
            <h2>Notifiche</h2>
        </header>

        <section class="notification-list">
            <?php if (count($notifiche) > 0): ?>
                <?php foreach ($notifiche as $n): 
                    $avatar = $n['Avatar_URL'] ?? "https://ui-avatars.com/api/?name=".urlencode($n['Username'])."&background=8b5cf6&color=fff";
                ?>
                    <div class="notification-item <?= $n['Tipo'] ?>">
                        <img src="<?= $avatar ?>" alt="Avatar" class="notif-avatar">
                        <div class="notif-content">
                            <p>
                                <strong>@<?= htmlspecialchars($n['Username']) ?></strong> 
                                <?php if($n['Tipo'] == 'follow') echo "ha iniziato a seguirti."; ?>
                            </p>
                            <span class="notif-time"><?= date('d M, H:i', strtotime($n['Data'])) ?></span>
                        </div>
                        <div class="notif-dot"></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <span class="big-ico">🔔</span>
                    <p>Non hai ancora nessuna notifica.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

</body>
</html>