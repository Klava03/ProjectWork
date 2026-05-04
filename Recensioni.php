<?php
session_start();
require 'Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

$pdo = getConnection();
$my_id = $_SESSION['user_id'];

// Recuperiamo tutte le recensioni dell'utente con i dati del film
try {
    $stmt = $pdo->prepare("
        SELECT L.*, F.Title, F.Poster_Path, F.Release_Date 
        FROM Log L 
        JOIN Film F ON L.IDFilm = F.ID 
        WHERE L.IDUtente = ? 
        ORDER BY L.Data_Pubblicazione DESC
    ");
    $stmt->execute([$my_id]);
    $recensioni = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Errore nel recupero delle recensioni: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Pulse • Le mie Recensioni</title>
    <link rel="stylesheet" href="../CSS/Home.css">
    <link rel="stylesheet" href="../CSS/Recensioni.css">
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
            <a class="active" href="recensioni.php"><span class="ico">✍</span><span class="label">Recensioni</span></a>
            <a href="profilo.php"><span class="ico">☺</span><span class="label">Profilo</span></a>
        </nav>
    </aside>

    <main class="center">
        <header class="page-header">
            <h2>Le mie Recensioni</h2>
            <p>Hai scritto <strong><?= count($recensioni) ?></strong> recensioni finora.</p>
        </header>

        <section class="reviews-container">
            <?php if (count($recensioni) > 0): ?>
                <?php foreach ($recensioni as $r): ?>
                    <article class="review-item">
                        <div class="movie-side">
                            <img src="https://image.tmdb.org/t/p/w300<?= $r['Poster_Path'] ?>" alt="Poster" class="rev-poster">
                        </div>
                        <div class="content-side">
                            <div class="rev-header">
                                <h3><?= htmlspecialchars($r['Title']) ?></h3>
                                <span class="rev-year"><?= substr($r['Release_Date'], 0, 4) ?></span>
                            </div>
                            
                            <div class="rev-meta">
                                <div class="rev-stars">
                                    <?php 
                                    $voto = $r['Voto'];
                                    for($i=1; $i<=5; $i++) {
                                        echo ($i <= $voto) ? '<span class="star gold">★</span>' : '<span class="star">☆</span>';
                                    }
                                    ?>
                                </div>
                                <span class="rev-date">Visto il <?= date('d/m/Y', strtotime($r['Data'])) ?></span>
                            </div>

                            <div class="rev-body">
                                <p><?= nl2br(htmlspecialchars($r['Recensione'])) ?></p>
                            </div>

                            <div class="rev-actions">
                                <a href="modifica_log.php?id=<?= $r['ID'] ?>" class="btn-sm">Modifica</a>
                                <button onclick="eliminaRecensione(<?= $r['ID'] ?>)" class="btn-sm btn-danger">Elimina</button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>Non hai ancora scritto nessuna recensione.</p>
                    <a href="cerca.php" class="btn-main">Cerca un film da recensire</a>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
function eliminaRecensione(id) {
    if (confirm("Sei sicuro di voler eliminare questa recensione?")) {
        window.location.href = "elimina_log.php?id=" + id;
    }
}
</script>

</body>
</html>