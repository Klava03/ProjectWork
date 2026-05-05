<?php
session_start();
require 'Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

$pdo = getConnection();
$my_id = $_SESSION['user_id'];

// 1. Recupero dati utente
$stmt = $pdo->prepare("SELECT Username, Bio, Avatar_URL FROM Utente WHERE ID = ?");
$stmt->execute([$my_id]);
$user = $stmt->fetch();

// 2. Conteggio Statistiche (Visti, Follower, Seguiti)
// Film visti
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Visione WHERE IDUtente = ? AND Is_Watched = 1");
$stmt->execute([$my_id]);
$count_visti = $stmt->fetchColumn();

// Follower
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Segui WHERE IDSeguito = ?");
$stmt->execute([$my_id]);
$count_follower = $stmt->fetchColumn();

// 3. Recupero ultimi Log (Recensioni) dell'utente
$stmt = $pdo->prepare("
    SELECT L.*, F.Title, F.Poster_Path, F.Release_Date 
    FROM Log L 
    JOIN Film F ON L.IDFilm = F.ID 
    WHERE L.IDUtente = ? 
    ORDER BY L.Data_Pubblicazione DESC LIMIT 10
");
$stmt->execute([$my_id]);
$logs = $stmt->fetchAll();

$avatar = $user['Avatar_URL'] ?? "https://ui-avatars.com/api/?name=".urlencode($user['Username'])."&background=8b5cf6&color=fff";
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Pulse • <?= htmlspecialchars($user['Username']) ?></title>
    <link rel="stylesheet" href="../CSS/Home.css">
    <link rel="stylesheet" href="../CSS/Profilo.css">
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
            <a href="notifiche.php"><span class="ico">🔔</span><span class="label">Notifiche</span></a>
            <a href="liste.php"><span class="ico">≡</span><span class="label">Liste</span></a>
            <a href="recensioni.php"><span class="ico">✍</span><span class="label">Recensioni</span></a>
            <a class="active" href="profilo.php"><span class="ico">☺</span><span class="label">Profilo</span></a>
        </nav>
    </aside>

    <main class="center">
        <section class="profile-header">
            <div class="profile-main-info">
                <img src="<?= $avatar ?>" alt="Avatar" class="profile-lg-avatar">
                <div class="profile-text">
                    <h2><?= htmlspecialchars($user['Username']) ?></h2>
                    <p class="bio"><?= htmlspecialchars($user['Bio'] ?? 'Nessuna biografia inserita.') ?></p>
                    <a href="modifica_profilo.php" class="btn-edit">Modifica Profilo</a>
                </div>
            </div>

            <div class="profile-stats">
                <div class="stat">
                    <span class="num"><?= $count_visti ?></span>
                    <span class="lab">Film</span>
                </div>
                <div class="stat">
                    <span class="num"><?= $count_follower ?></span>
                    <span class="lab">Follower</span>
                </div>
                <div class="stat">
                    <span class="num">0</span> <span class="lab">Seguiti</span>
                </div>
            </div>
        </section>

        <section class="profile-content">
            <h3>Ultime Recensioni</h3>
            <div class="log-list">
                <?php if (count($logs) > 0): ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="log-card">
                            <img src="https://image.tmdb.org/t/p/w200<?= $log['Poster_Path'] ?>" alt="Poster" class="log-poster">
                            <div class="log-details">
                                <h4><?= htmlspecialchars($log['Title']) ?> <span>(<?= substr($log['Release_Date'], 0, 4) ?>)</span></h4>
                                <div class="rating">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <span class="star"><?= $i <= $log['Voto'] ? '★' : '☆' ?></span>
                                    <?php endfor; ?>
                                </div>
                                <p class="review-text"><?= htmlspecialchars($log['Recensione']) ?></p>
                                <small class="date"><?= date('d M Y', strtotime($log['Data_Pubblicazione'])) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty-msg">Non hai ancora scritto nessuna recensione. Inizia a cercare un film!</p>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

</body>
</html>