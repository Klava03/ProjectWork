<?php
// pages/Recensioni.php — Recensioni dei propri seguiti

require_once 'Database.php';
$pdo   = getConnection();
$my_id = (int)$_SESSION['user_id'];

$filtro = $_GET['filtro'] ?? 'seguiti';   // seguiti | tutte (proprie)

// Numero di seguiti
$stmtSeg = $pdo->prepare("SELECT COUNT(*) FROM Segui WHERE IDSeguitore = ?");
$stmtSeg->execute([$my_id]);
$cnt_seguiti = (int)$stmtSeg->fetchColumn();

// ── Recensioni dei SEGUITI ────────────────────
$recensioni = [];
if ($filtro === 'seguiti') {
    $stmt = $pdo->prepare("
        SELECT L.ID            AS log_id,
               L.Voto,
               L.Recensione,
               L.Data          AS data_visione,
               L.Data_Pubblicazione,
               F.TMDB_ID,
               F.Title,
               F.Poster_Path,
               F.Release_Date,
               U.ID            AS user_id,
               U.Username,
               U.Avatar_URL
        FROM Log L
        JOIN Film   F ON L.IDFilm   = F.ID
        JOIN Utente U ON L.IDUtente = U.ID
        WHERE L.IDUtente IN (
            SELECT IDSeguito FROM Segui WHERE IDSeguitore = ?
        )
        AND L.Recensione IS NOT NULL
        AND TRIM(L.Recensione) != ''
        ORDER BY L.Data_Pubblicazione DESC
        LIMIT 60
    ");
    $stmt->execute([$my_id]);
    $recensioni = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Proprie recensioni
    $stmt = $pdo->prepare("
        SELECT L.ID            AS log_id,
               L.Voto,
               L.Recensione,
               L.Data          AS data_visione,
               L.Data_Pubblicazione,
               F.TMDB_ID,
               F.Title,
               F.Poster_Path,
               F.Release_Date,
               U.ID            AS user_id,
               U.Username,
               U.Avatar_URL
        FROM Log L
        JOIN Film   F ON L.IDFilm   = F.ID
        JOIN Utente U ON L.IDUtente = U.ID
        WHERE L.IDUtente = ?
        AND L.Recensione IS NOT NULL
        AND TRIM(L.Recensione) != ''
        ORDER BY L.Data_Pubblicazione DESC
    ");
    $stmt->execute([$my_id]);
    $recensioni = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function slugify(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9\s\-]/u', '', $s);
    $s = preg_replace('/[\s\-]+/', '-', $s);
    return trim($s, '-');
}

function avatarUrl(?string $val, string $username): string {
    if ($val && !str_starts_with($val, 'http')) return '/Pulse/IMG/avatars/' . $val;
    return $val ?? "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=8b5cf6&color=fff&size=80";
}

function starsHTML(float $rating): string {
    $html = '<span class="rev-stars-row">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i)        $html .= '<span class="rev-star full">★</span>';
        elseif ($rating >= $i-.5) $html .= '<span class="rev-star half">★</span>';
        else                      $html .= '<span class="rev-star empty">★</span>';
    }
    return $html . '</span>';
}
?>

<div class="app">
    <?php require 'aside.php'; ?>

    <main class="center rec-center">

        <!-- ── HEADER ── -->
        <header class="rec-header">
            <div class="rec-header-text">
                <h1 class="rec-title">
                    <i class="bi bi-chat-quote-fill" style="color:var(--accent);font-size:22px;"></i>
                    Recensioni
                </h1>
                <p class="rec-subtitle">
                    <?php if ($filtro === 'seguiti'): ?>
                        Le opinioni di chi segui · <strong><?= count($recensioni) ?></strong> recensioni
                    <?php else: ?>
                        Le tue recensioni · <strong><?= count($recensioni) ?></strong>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Filtro -->
            <div class="rec-filter-tabs">
                <a href="/Pulse/recensioni?filtro=seguiti"
                   class="rec-filter-tab <?= $filtro === 'seguiti' ? 'active' : '' ?>">
                    <i class="bi bi-people-fill"></i>
                    Seguiti
                </a>
                <a href="/Pulse/recensioni?filtro=mie"
                   class="rec-filter-tab <?= $filtro === 'mie' ? 'active' : '' ?>">
                    <i class="bi bi-person-fill"></i>
                    Le mie
                </a>
            </div>
        </header>

        <!-- ── EMPTY STATE: nessun seguito ── -->
        <?php if ($filtro === 'seguiti' && $cnt_seguiti === 0): ?>
            <div class="rec-empty">
                <i class="bi bi-people" style="font-size:48px; color:var(--muted);"></i>
                <p>Non stai ancora seguendo nessuno.</p>
                <a href="/Pulse/cerca/utenti" class="rec-cta-btn">
                    <i class="bi bi-search"></i>
                    Cerca persone
                </a>
            </div>

        <!-- ── EMPTY STATE: nessuna recensione ── -->
        <?php elseif (!$recensioni): ?>
            <div class="rec-empty">
                <i class="bi bi-chat-square" style="font-size:48px; color:var(--muted);"></i>
                <?php if ($filtro === 'seguiti'): ?>
                    <p>Le persone che segui non hanno ancora scritto recensioni.</p>
                    <a href="/Pulse/cerca" class="rec-cta-btn">
                        <i class="bi bi-search"></i>
                        Scopri film
                    </a>
                <?php else: ?>
                    <p>Non hai ancora scritto nessuna recensione.</p>
                    <a href="/Pulse/crea" class="rec-cta-btn">
                        <i class="bi bi-plus-circle"></i>
                        Scrivi una recensione
                    </a>
                <?php endif; ?>
            </div>

        <!-- ── LISTA RECENSIONI ── -->
        <?php else: ?>
            <div class="rec-list">
                <?php foreach ($recensioni as $r):
                    $poster     = $r['Poster_Path']
                        ? "https://image.tmdb.org/t/p/w300" . $r['Poster_Path']
                        : null;
                    $anno       = !empty($r['Release_Date']) ? substr($r['Release_Date'], 0, 4) : '';
                    $voto       = $r['Voto'] ? (float)$r['Voto'] : null;
                    $uAvatar    = avatarUrl($r['Avatar_URL'], $r['Username']);
                    $pubDate    = strtotime($r['Data_Pubblicazione']);
                    $dataFmt    = date('j M Y', $pubDate);
                    $isMe       = ((int)$r['user_id'] === $my_id);
                ?>

                <article class="rec-card">

                    <!-- Poster film -->
                    <a href="/Pulse/film/<?= $r['TMDB_ID'] ?>-<?= slugify($r['Title']) ?>"
                       class="rec-poster-wrap">
                        <?php if ($poster): ?>
                            <img src="<?= $poster ?>"
                                 alt="<?= htmlspecialchars($r['Title']) ?>"
                                 class="rec-poster">
                        <?php else: ?>
                            <div class="rec-poster-fallback">
                                <i class="bi bi-film"></i>
                            </div>
                        <?php endif; ?>
                    </a>

                    <!-- Contenuto -->
                    <div class="rec-content">

                        <!-- Riga autore -->
                        <div class="rec-author-row">
                            <a href="/Pulse/utente/<?= urlencode($r['Username']) ?>"
                               class="rec-author-link">
                                <img src="<?= htmlspecialchars($uAvatar) ?>"
                                     alt=""
                                     class="rec-author-avatar">
                                <span class="rec-author-name">
                                    @<?= htmlspecialchars($r['Username']) ?>
                                    <?php if ($isMe): ?>
                                        <span class="rec-me-badge">tu</span>
                                    <?php endif; ?>
                                </span>
                            </a>
                            <span class="rec-date"><?= $dataFmt ?></span>
                        </div>

                        <!-- Titolo film + anno -->
                        <a href="/Pulse/film/<?= $r['TMDB_ID'] ?>-<?= slugify($r['Title']) ?>"
                           class="rec-film-title">
                            <?= htmlspecialchars($r['Title']) ?>
                            <?php if ($anno): ?>
                                <span class="rec-film-year"><?= $anno ?></span>
                            <?php endif; ?>
                        </a>

                        <!-- Voto stelle -->
                        <?php if ($voto): ?>
                            <div class="rec-rating">
                                <?= starsHTML($voto) ?>
                                <span class="rec-rating-num"><?= number_format($voto, 1) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Testo recensione -->
                        <p class="rec-text" id="recText-<?= $r['log_id'] ?>">
                            <?= nl2br(htmlspecialchars($r['Recensione'])) ?>
                        </p>

                        <?php if (mb_strlen($r['Recensione']) > 280): ?>
                            <button class="rec-expand-btn" data-target="recText-<?= $r['log_id'] ?>">
                                Leggi tutto <i class="bi bi-chevron-down"></i>
                            </button>
                        <?php endif; ?>

                    </div>
                </article>

                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>
</div>

<script>
// ── Espandi recensioni lunghe ──────────────────
document.querySelectorAll('.rec-expand-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const targetId = btn.dataset.target;
        const p = document.getElementById(targetId);
        if (!p) return;

        if (p.classList.contains('expanded')) {
            p.classList.remove('expanded');
            btn.innerHTML = 'Leggi tutto <i class="bi bi-chevron-down"></i>';
        } else {
            p.classList.add('expanded');
            btn.innerHTML = 'Riduci <i class="bi bi-chevron-up"></i>';
        }
    });
});
</script>