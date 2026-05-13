<?php
// pages/Home.php — Feed stile Instagram dei log dei seguiti

require_once 'Database.php';
$pdo   = getConnection();
$my_id = (int)$_SESSION['user_id'];

function homeAvatarUrl(?string $val, string $username): string {
    if ($val && !str_starts_with($val, 'http')) return '/Pulse/IMG/avatars/' . $val;
    return $val ?? "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=8b5cf6&color=fff&size=80";
}

$_rawAv = $_SESSION['avatar_url'] ?? null;
$myAvatarUrl = !$_rawAv
    ? "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['username'] ?? 'U') . "&background=8b5cf6&color=fff&size=80"
    : (str_starts_with($_rawAv, 'http') ? $_rawAv : '/Pulse/IMG/avatars/' . $_rawAv);

// ── Conta seguiti ────────────────────────────────────────────────
$stmtSeg = $pdo->prepare("SELECT COUNT(*) FROM Segui WHERE IDSeguitore = ?");
$stmtSeg->execute([$my_id]);
$cnt_seguiti = (int)$stmtSeg->fetchColumn();

// ── Feed ─────────────────────────────────────────────────────────
$feed = [];
if ($cnt_seguiti > 0) {
    $stmt = $pdo->prepare("
        SELECT L.ID                AS log_id,
               L.Voto,
               L.Recensione,
               L.Data              AS data_visione,
               L.Data_Pubblicazione,
               F.TMDB_ID,
               F.Title,
               F.Poster_Path,
               F.Backdrop_Path,
               F.Release_Date,
               U.ID                AS user_id,
               U.Username,
               U.Avatar_URL
        FROM Log L
        JOIN Film   F ON L.IDFilm   = F.ID
        JOIN Utente U ON L.IDUtente = U.ID
        WHERE L.IDUtente IN (
            SELECT IDSeguito FROM Segui WHERE IDSeguitore = ?
        )
        ORDER BY L.Data_Pubblicazione DESC, L.ID DESC
        LIMIT 50
    ");
    $stmt->execute([$my_id]);
    $feed = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Liked da me (per segnare i cuori già attivi) ─────────────────
$myLikes = [];
if (!empty($feed)) {
    $filmIds = array_unique(array_column($feed, 'TMDB_ID'));
    $ph = implode(',', array_fill(0, count($filmIds), '?'));
    $s  = $pdo->prepare("
        SELECT F.TMDB_ID FROM Visione V
        JOIN Film F ON V.IDFilm = F.ID
        WHERE V.IDUtente = ? AND V.Liked = 1 AND F.TMDB_ID IN ($ph)
    ");
    $s->execute([$my_id, ...$filmIds]);
    $myLikes = array_flip($s->fetchAll(PDO::FETCH_COLUMN));
}

// ── Suggerimenti utenti ──────────────────────────────────────────
$stmtSug = $pdo->prepare("
    SELECT U.ID, U.Username, U.Bio, U.Avatar_URL,
           (SELECT COUNT(*) FROM Log L2 WHERE L2.IDUtente = U.ID) AS cnt_log
    FROM Utente U
    WHERE U.ID != ?
      AND U.ID NOT IN (SELECT IDSeguito FROM Segui WHERE IDSeguitore = ?)
    ORDER BY cnt_log DESC
    LIMIT 5
");
$stmtSug->execute([$my_id, $my_id]);
$suggeriti = $stmtSug->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="/Pulse/CSS/Community.css">
<script src="/Pulse/JS/commenti.js" defer></script>

<div class="app">
    <?php require 'aside.php'; ?>

    <main class="center hf-center">
        <header class="hf-topbar">
            <h1 class="hf-title">
                <i class="bi bi-film" style="color:var(--accent)"></i>
                Feed
            </h1>
        </header>

        <?php if ($cnt_seguiti === 0): ?>
        <div class="hf-empty">
            <i class="bi bi-people" style="font-size:52px;color:var(--muted)"></i>
            <p class="hf-empty-title">Il tuo feed è vuoto</p>
            <p class="hf-empty-sub">Segui altri utenti per vedere i loro log qui.</p>
            <a href="/Pulse/cerca/utenti" class="hf-cta"><i class="bi bi-search"></i> Cerca persone</a>
        </div>

        <?php elseif (empty($feed)): ?>
        <div class="hf-empty">
            <i class="bi bi-camera-reels" style="font-size:52px;color:var(--muted)"></i>
            <p class="hf-empty-title">Ancora nessun log</p>
            <p class="hf-empty-sub">Le persone che segui non hanno ancora registrato film.</p>
        </div>

        <?php else: ?>
        <div class="hf-feed">
            <?php foreach ($feed as $p):
                $avatar   = homeAvatarUrl($p['Avatar_URL'], $p['Username']);
                $poster   = $p['Poster_Path']
                            ? "https://image.tmdb.org/t/p/w500" . $p['Poster_Path']
                            : "https://s.ltrbxd.com/static/img/empty-poster-230-nQeuntFa.png";
                $backdrop = $p['Backdrop_Path']
                            ? "https://image.tmdb.org/t/p/w780" . $p['Backdrop_Path']
                            : null;
                $anno     = !empty($p['Release_Date']) ? substr($p['Release_Date'], 0, 4) : '';
                $voto     = $p['Voto'] ? (float)$p['Voto'] : null;
                $hasRec   = !empty(trim($p['Recensione'] ?? ''));
                $ts       = $p['Data_Pubblicazione'] ? strtotime($p['Data_Pubblicazione']) : null;
                $dataLabel= $ts ? date('d M Y', $ts) : '';
                $iLiked   = isset($myLikes[$p['TMDB_ID']]);
            ?>
            <article class="hf-post" data-log="<?= (int)$p['log_id'] ?>">

                <!-- Header utente -->
                <div class="hf-post-header">
                    <a href="/Pulse/utente/<?= urlencode($p['Username']) ?>" class="hf-user-link">
                        <img src="<?= htmlspecialchars($avatar) ?>"
                             alt="@<?= htmlspecialchars($p['Username']) ?>"
                             class="hf-avatar">
                        <div class="hf-user-info">
                            <span class="hf-username">@<?= htmlspecialchars($p['Username']) ?></span>
                            <?php if ($dataLabel): ?>
                                <span class="hf-date"><?= $dataLabel ?></span>
                            <?php endif; ?>
                        </div>
                    </a>

                    <!-- Like button ♥ al posto del badge voto -->
                    <button class="hf-like-btn <?= $iLiked ? 'liked' : '' ?>"
                            data-tmdb="<?= (int)$p['TMDB_ID'] ?>"
                            data-liked="<?= $iLiked ? '1' : '0' ?>"
                            onclick="toggleLike(this)"
                            title="<?= $iLiked ? 'Rimuovi mi piace' : 'Mi piace' ?>">
                        <i class="bi <?= $iLiked ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                        <span class="hf-like-label"><?= $iLiked ? 'Ti piace' : 'Mi piace' ?></span>
                    </button>
                </div>

                <!-- Media -->
                <div class="hf-post-media <?= $backdrop ? 'has-backdrop' : 'has-poster' ?>">
                    <?php if ($backdrop): ?>
                        <img src="<?= htmlspecialchars($backdrop) ?>"
                             alt="<?= htmlspecialchars($p['Title']) ?>"
                             class="hf-backdrop" loading="lazy">
                        <img src="<?= htmlspecialchars($poster) ?>"
                             alt="" class="hf-poster-overlay" loading="lazy">
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($poster) ?>"
                             alt="<?= htmlspecialchars($p['Title']) ?>"
                             class="hf-poster-solo" loading="lazy">
                    <?php endif; ?>
                    <div class="hf-media-gradient"></div>
                    <div class="hf-media-caption <?= $backdrop ? 'shifted' : '' ?>">
                        <a href="/Pulse/film/<?= (int)$p['TMDB_ID'] ?>" class="hf-film-title">
                            <?= htmlspecialchars($p['Title']) ?>
                        </a>
                        <?php if ($anno): ?>
                            <span class="hf-film-anno"><?= $anno ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Footer: stelle + azioni -->
                <div class="hf-post-footer">
                    <div class="hf-stars">
                        <?php if ($voto !== null):
                            for ($i = 1; $i <= 5; $i++):
                                if ($voto >= $i)        $cls = 'full';
                                elseif ($voto >= $i-.5) $cls = 'half';
                                else                    $cls = 'empty';
                        ?>
                            <span class="hf-star <?= $cls ?>">★</span>
                        <?php endfor; else: ?>
                            <span class="hf-no-rating">Nessun voto</span>
                        <?php endif; ?>
                    </div>

                    <div class="hf-actions">
                        <a href="/Pulse/film/<?= (int)$p['TMDB_ID'] ?>" class="hf-btn hf-btn-ghost">
                            <i class="bi bi-film"></i> Film
                        </a>
                        <?php if ($hasRec): ?>
                        <button class="hf-btn hf-btn-accent"
                                onclick="openReview(this)"
                                data-user="<?= htmlspecialchars($p['Username']) ?>"
                                data-film="<?= htmlspecialchars($p['Title']) ?>"
                                data-voto="<?= $voto !== null ? number_format($voto, 1) : '' ?>"
                                data-testo="<?= htmlspecialchars($p['Recensione']) ?>"
                                data-avatar="<?= htmlspecialchars($avatar) ?>">
                            <i class="bi bi-chat-quote"></i> Leggi recensione
                        </button>
                        <?php else: ?>
                        <span class="hf-no-rec"><i class="bi bi-pencil"></i> Solo log</span>
                        <?php endif; ?>
                        <button class="hf-btn hf-btn-ghost hf-comm-toggle"
                    data-log="<?= (int)$p['log_id'] ?>">
                <i class="bi bi-chat"></i>
                <span data-comm-badge="log-<?= (int)$p['log_id'] ?>">0</span>
            </button>
                    </div>
                </div>

                    <div class="hf-comments-box" style="display:none">
        <section class="cm-section" data-target-tipo="log" data-target-id="<?= (int)$p['log_id'] ?>">
            <div class="cm-composer">
                <img src="<?= htmlspecialchars($myAvatarUrl) ?>" alt="" class="cm-avatar cm-avatar-sm">
                <div class="cm-composer-body">
                    <textarea class="cm-textarea cm-main-input"
                        placeholder="Commenta questo log…" rows="1" maxlength="2000"></textarea>
                    <div class="cm-composer-footer">
                        <span class="cm-counter"><span class="cm-counter-val">0</span>/2000</span>
                        <button type="button" class="cm-send-btn" disabled>
                            <i class="bi bi-send-fill"></i> Invia
                        </button>
                    </div>
                </div>
            </div>
            <div class="cm-list">
                <div class="cm-loading"><i class="bi bi-arrow-repeat cm-spin"></i></div>
            </div>
        </section>
    </div>

            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- Sidebar destra -->
    <aside class="right hf-right">
        <?php if (!empty($suggeriti)): ?>
        <div class="hf-side-card">
            <h3 class="hf-side-title"><i class="bi bi-person-plus"></i> Chi seguire</h3>
            <div class="hf-sug-list">
                <?php foreach ($suggeriti as $s):
                    $sAvatar = homeAvatarUrl($s['Avatar_URL'], $s['Username']);
                ?>
                <div class="hf-sug-item">
                    <a href="/Pulse/utente/<?= urlencode($s['Username']) ?>" class="hf-sug-user">
                        <img src="<?= htmlspecialchars($sAvatar) ?>" alt="" class="hf-sug-avatar">
                        <div>
                            <div class="hf-sug-name">@<?= htmlspecialchars($s['Username']) ?></div>
                            <div class="hf-sug-meta"><?= (int)$s['cnt_log'] ?> log</div>
                        </div>
                    </a>
                    <a href="/Pulse/utente/<?= urlencode($s['Username']) ?>" class="hf-follow-btn">Segui</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="hf-side-card hf-side-links">
            <a href="/Pulse/cerca">Cerca film</a> ·
            <a href="/Pulse/cerca/utenti">Utenti</a> ·
            <a href="/Pulse/recensioni">Recensioni</a>
        </div>
    </aside>
</div>

<!-- Modal recensione -->
<div id="hf-modal" class="hf-modal-overlay" onclick="closeReview(event)">
    <div class="hf-modal-box">
        <button class="hf-modal-close" onclick="closeReview(null)"><i class="bi bi-x-lg"></i></button>
        <div class="hf-modal-header">
            <img id="hf-modal-avatar" src="" alt="" class="hf-modal-avatar">
            <div>
                <div id="hf-modal-user" class="hf-modal-user"></div>
                <div id="hf-modal-film" class="hf-modal-film"></div>
            </div>
        </div>
        <div id="hf-modal-stars" class="hf-modal-stars"></div>
        <p id="hf-modal-testo" class="hf-modal-testo"></p>
    </div>
</div>

<!-- Toast -->
<div id="hf-toast" class="hf-toast"></div>

<script>
const FILM_BACKEND = '/Pulse/backend/GestioneFilm.php';

// ── Toast ─────────────────────────────────────
function hfToast(msg, type = 'ok') {
    const t = document.getElementById('hf-toast');
    t.textContent = msg;
    t.className = 'hf-toast show' + (type === 'error' ? ' error' : '');
    clearTimeout(t._t);
    t._t = setTimeout(() => t.className = 'hf-toast', 2800);
}

// ── Like toggle ──────────────────────────────
async function toggleLike(btn) {
    if (btn.disabled) return;
    const tmdb_id = parseInt(btn.dataset.tmdb);
    const liked   = btn.dataset.liked === '1';
    btn.disabled  = true;

    try {
        const res  = await fetch(FILM_BACKEND, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_like', tmdb_id })
        });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error ?? 'Errore');

        const nowLiked = json.stato === 1;
        btn.dataset.liked = nowLiked ? '1' : '0';
        btn.classList.toggle('liked', nowLiked);
        btn.querySelector('i').className = 'bi ' + (nowLiked ? 'bi-heart-fill' : 'bi-heart');
        btn.querySelector('.hf-like-label').textContent = nowLiked ? 'Ti piace' : 'Mi piace';
        btn.title = nowLiked ? 'Rimuovi mi piace' : 'Mi piace';

        if (nowLiked) {
            btn.classList.add('hf-like-pop');
            setTimeout(() => btn.classList.remove('hf-like-pop'), 400);
        }
        hfToast(nowLiked ? '♥ Aggiunto ai film che ti piacciono' : 'Rimosso dai film che ti piacciono');
    } catch (err) {
        hfToast(err.message, 'error');
    } finally {
        btn.disabled = false;
    }
}

// ── Modal recensione ──────────────────────────
function openReview(btn) {
    const voto = parseFloat(btn.dataset.voto);
    document.getElementById('hf-modal-avatar').src = btn.dataset.avatar;
    document.getElementById('hf-modal-user').textContent  = '@' + btn.dataset.user;
    document.getElementById('hf-modal-film').textContent  = btn.dataset.film;
    document.getElementById('hf-modal-testo').textContent = btn.dataset.testo;

    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (voto >= i)        stars += '<span class="hf-star full">★</span>';
        else if (voto >= i-.5) stars += '<span class="hf-star half">★</span>';
        else                   stars += '<span class="hf-star empty">★</span>';
    }
    document.getElementById('hf-modal-stars').innerHTML = stars;
    document.getElementById('hf-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeReview(e) {
    if (e && e.target !== document.getElementById('hf-modal')) return;
    document.getElementById('hf-modal').classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeReview(null); });

/* ── Toggle commenti nel feed ─── */
document.querySelectorAll('.hf-comm-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const logId = btn.dataset.log;
        const box = btn.closest('.hf-post').querySelector('.hf-comments-box');
        const isOpen = box.style.display !== 'none';
        box.style.display = isOpen ? 'none' : '';
        btn.classList.toggle('active', !isOpen);

        if (!isOpen && !box.dataset.init) {
            box.dataset.init = '1';
            // Inizializza sezione
            const section = box.querySelector('.cm-section');
            if (window.PulseCommenti) window.PulseCommenti.init(section);
        }
    });
});

// Carica conteggi commenti per ogni post del feed
document.querySelectorAll('.hf-post[data-log]').forEach(async post => {
    const logId = +post.dataset.log;
    try {
        const r = await fetch('/Pulse/backend/GestioneCommenti.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'conteggi', tipo: 'log', id: logId })
        });
        const j = await r.json();
        if (j.ok) {
            const badge = post.querySelector(`[data-comm-badge="log-${logId}"]`);
            if (badge) badge.textContent = j.commenti;
        }
    } catch {}
});
</script>