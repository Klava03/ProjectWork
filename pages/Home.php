<?php

    function renderStars($rating) {
        $full = floor($rating);
        $out = str_repeat("★", $full);
        if ($rating - $full >= 0.5) $out .= "⯪";
        return $out . str_repeat("☆", 5 - ceil($rating));
    }
?>



    <div class="app">
        <?php include "aside.php"; ?>


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
