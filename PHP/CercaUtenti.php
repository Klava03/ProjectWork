<?php
session_start();
require 'Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

$pdo = getConnection();
$ricerca = $_GET['u'] ?? '';
$risultati = [];

if ($ricerca !== '') {
    try {
        $stmt = $pdo->prepare("SELECT ID, Username, Bio, Avatar_URL FROM Utente 
                               WHERE Username LIKE :q
                               LIMIT 20");
        $stmt->execute([
            'q' => "%$ricerca%"
        ]);
        $risultati = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Errore ricerca utenti: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Pulse • Cerca Utenti</title>
    <link rel="stylesheet" href="../CSS/Home.css">
    <link rel="stylesheet" href="../CSS/Cerca.css"> <link rel="stylesheet" href="../CSS/CercaUtenti.css">
</head>
<body>

<div class="app">
    <aside class="left">
        <div class="brand">
            <div class="logo"></div>
            <h1>Pulse</h1>
        </div>
        <nav class="nav">
            <a href="home.php"><span class="ico">⌂</span><span class="label">Home</span></a>
            <a class="active" href="cerca.php"><span class="ico">⌕</span><span class="label">Cerca</span></a>
            <a href="community.php"><span class="ico">👥</span><span class="label">Community</span></a>
            <a href="crea.php"><span class="ico">＋</span><span class="label">Crea</span></a>
            <a href="notifiche.php"><span class="ico">🔔</span><span class="label">Notifiche</span></a>
            <a href="liste.php"><span class="ico">≡</span><span class="label">Liste</span></a>
            <a href="recensioni.php"><span class="ico">✍</span><span class="label">Recensioni</span></a>
            <a href="profilo.php"><span class="ico">☺</span><span class="label">Profilo</span></a>
        </nav>
    </aside>

    <main class="center">
        <section class="search-header">
            <div class="search-tabs">
                <a href="cerca.php" class="tab">Film</a>
                <a href="cercautenti.php" class="tab active">Utenti</a>
            </div>

            <h2>Trova persone</h2>
            <form action="cercautenti.php" method="GET" class="search-form">
                <input type="text" name="u" class="search-input" 
                       placeholder="Cerca username..." 
                       value="<?= htmlspecialchars($ricerca) ?>" required>
                <button type="submit" class="search-btn">Cerca</button>
            </form>
        </section>

        <div class="user-results-container">
            <?php if ($ricerca !== ''): ?>
                <?php if (count($risultati) > 0): ?>
                    <div class="user-list">
                        <?php foreach ($risultati as $user):
                            $avatar = $user['Avatar_URL'] ?? "https://ui-avatars.com/api/?name=".urlencode($user['Username'])."&background=8b5cf6&color=fff";
                        ?>
                            <div class="user-card">
                                <img src="<?= $avatar ?>" alt="Avatar" class="user-avatar">
                                <div class="user-info">
                                    <span class="username">@<?= htmlspecialchars($user['Username']) ?></span>
                                    <p class="bio"><?= htmlspecialchars($user['Bio'] ?? 'Nessuna bio') ?></p>
                                </div>
                                <button class="follow-btn" data-id="<?= $user['ID'] ?>" onclick="handleFollow(this)">
                                    Segui
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-results">Nessun utente trovato per "<?= htmlspecialchars($ricerca) ?>".</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="no-results">Inserisci un nome per iniziare la ricerca.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function handleFollow(btn) {
    const userId = btn.getAttribute('data-id');
    
    if (btn.classList.contains('following')) {
        btn.textContent = 'Segui';
        btn.classList.remove('following');
    } else {
        btn.textContent = 'Seguito';
        btn.classList.add('following');
    }
    
}
</script>

</body>
</html>