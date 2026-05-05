<?php
    session_start();

    // PROTEZIONE: Se l'utente non è loggato, rimanda al login
    if (!isset($_SESSION['user_id'])) {
        header("Location: Login.php");
        exit();
    }

    // Dati reali dalla sessione
    $currentUsername = $_SESSION['username'];
    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($currentUsername) . "&background=8b5cf6&color=fff";

    // --- MOCK FEED (rimane invariato per ora) ---
    $feed = [
        [
            "log_id" => 101,
            "user" => ["username" => "luca", "avatar" => "https://i.pravatar.cc/150?u=luca"],
            "film" => ["title" => "The Matrix", "poster" => "https://image.tmdb.org/t/p/w500/f89U3Y9S7qVp79p9oZwpjYvN7Y0.jpg"],
            "rating" => 4.5,
            "text" => "Un capolavoro assoluto del genere sci-fi.",
            "likes" => 128,
            "comments" => 14,
            "created_at" => "2h"
        ]
    ];

    function renderStars($rating) {
        $full = floor($rating);
        $out = str_repeat("★", $full);
        if ($rating - $full >= 0.5) $out .= "⯪";
        return $out . str_repeat("☆", 5 - ceil($rating));
    }
?>

<!doctype html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <title>Pulse • Home</title>
        <link rel="stylesheet" href="../CSS/Home.css">
    </head>
    <body>

    <div class="app">
        <aside class="left">
            <div class="brand">
                <div class="logo"></div>
                <h1>Pulse</h1>
            </div>

            <nav class="nav">
                <a class="active" href="home.php"><span class="ico">⌂</span><span class="label">Home</span></a>
                <a href="cerca.php"><span class="ico">⌕</span><span class="label">Cerca</span></a>
                <a href="community.php"><span class="ico">👥</span><span class="label">Community</span></a>
                <a href="crea.php"><span class="ico">＋</span><span class="label">Crea</span></a>
                <a href="notifiche.php"><span class="ico">🔔</span><span class="label">Notifiche</span></a>
                <a href="liste.php"><span class="ico">≡</span><span class="label">Liste</span></a>
                <a href="recensioni.php"><span class="ico">✍</span><span class="label">Recensioni</span></a>
                <a href="profilo.php"><span class="ico">☺</span><span class="label">Profilo</span></a>
            </nav>

            <div style="flex:1"></div>

            <div class="me">
                <img class="avatar" src="<?= $avatarUrl ?>" alt="Avatar">
                <div class="meta">
                    <strong>@<?= htmlspecialchars($currentUsername) ?></strong>
                    <a href="logout.php" style="font-size: 11px; color: var(--danger)">Log out</a>
                </div>
            </div>
        </aside>

        <main class="center">
            <?php foreach ($feed as $p): ?>
                <article class="post">
                    <div class="post-content" style="display:flex; align-items:center; gap:10px;">
                        <img src="<?= $p['user']['avatar'] ?>" style="width:30px; border-radius:50%">
                        <strong>@<?= htmlspecialchars($p['user']['username']) ?></strong>
                        <span style="margin-left:auto"><?= renderStars($p['rating']) ?></span>
                    </div>
                    
                    <img class="poster" src="<?= $p['film']['poster'] ?>" alt="Poster">
                    
                    <div class="post-content">
                        <p><strong><?= htmlspecialchars($p['film']['title']) ?></strong></p>
                        <p class="desc"><?= htmlspecialchars($p['text']) ?></p>
                        <button class="iconBtn likeBtn">♡ Mi piace</button>
                    </div>
                </article>
            <?php endforeach; ?>
        </main>

        <aside class="right">
            <section class="sideCard">
                <h3>Trending</h3>
                <p style="color:var(--muted); font-size:12px;">Nessun trend disponibile</p>
            </section>
        </aside>
    </div>

    <script>
        document.querySelectorAll(".likeBtn").forEach(btn => {
            btn.addEventListener("click", function() {
                this.classList.toggle("liked");
                this.textContent = this.classList.contains("liked") ? "♥ Ti piace" : "♡ Mi piace";
            });
        });
    </script>

    </body>
</html>