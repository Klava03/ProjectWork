<?php
session_start();
require 'Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

$pdo = getConnection();
$my_id = $_SESSION['user_id'];

// 1. Recupero tutte le liste dell'utente
// Usiamo una subquery o una JOIN per contare quanti film ci sono in ogni lista
try {
    $stmt = $pdo->prepare("
        SELECT L.*, 
        (SELECT COUNT(*) FROM Lista_Film WHERE IDLista = L.IDLista) as TotaleFilm,
        (SELECT F.Poster_Path FROM Lista_Film LF 
         JOIN Film F ON LF.IDFilm = F.ID 
         WHERE LF.IDLista = L.IDLista LIMIT 1) as AnteprimaPoster
        FROM Lista L 
        WHERE L.IDUtente = ? 
        ORDER BY L.IDLista DESC
    ");
    $stmt->execute([$my_id]);
    $liste = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Errore: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Pulse • Le mie Liste</title>
    <link rel="stylesheet" href="../CSS/Home.css">
    <link rel="stylesheet" href="../CSS/Liste.css">
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
            <a class="active" href="liste.php"><span class="ico">≡</span><span class="label">Liste</span></a>
            <a href="recensioni.php"><span class="ico">✍</span><span class="label">Recensioni</span></a>
            <a href="profilo.php"><span class="ico">☺</span><span class="label">Profilo</span></a>
        </nav>
    </aside>

    <main class="center">
        <header class="list-header">
            <div>
                <h2>Le tue Liste</h2>
                <p>Organizza i tuoi film preferiti o crea maratone a tema.</p>
            </div>
            <a href="nuova_lista.php" class="btn-create">+ Crea Nuova Lista</a>
        </header>

        <section class="grid-liste">
            <?php if (count($liste) > 0): ?>
                <?php foreach ($liste as $lista): 
                    $poster = $lista['AnteprimaPoster'] 
                              ? "https://image.tmdb.org/t/p/w500" . $lista['AnteprimaPoster'] 
                              : "../IMG/default_list.jpg";
                ?>
                    <div class="list-card" onclick="location.href='dettaglio_lista.php?id=<?= $lista['IDLista'] ?>'">
                        <div class="list-stack">
                            <img src="<?= $poster ?>" alt="Cover" class="list-cover">
                            <div class="stack-layer layer-1"></div>
                            <div class="stack-layer layer-2"></div>
                        </div>
                        <div class="list-info">
                            <h3><?= htmlspecialchars($lista['Titolo']) ?></h3>
                            <span><?= $lista['TotaleFilm'] ?> film</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-lists">
                    <p>Non hai ancora creato nessuna lista.</p>
                    <small>Crea la tua prima lista per raccogliere i film che ami!</small>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

</body>
</html>