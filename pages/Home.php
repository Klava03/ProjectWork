<?php
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



    <div class="app">
        <?php include "aside.php"; ?>

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
