<?php
// pages/Film.php — incluso da index.php
// $sub = "550-fight-club"  →  intval() estrae 550

require_once 'Database.php';

$apiKey = "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJiY2U5ZWZmYjRjMDA5ZTNmNGZmZTM1N2QzOTlhMTk4NSIsIm5iZiI6MTc2OTc4NDc2MC43NzcsInN1YiI6IjY5N2NjNWI4MWVhOGNmMGRlZTI2YWVmNSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.cPNGTwdDeQD6TGYY2Shn-i3Y0i-OjMSey9KPpdrpBAE";
$tmdb_id = (int)($sub ?? 0);

if (!$tmdb_id) {
    echo '<div style="padding:60px;text-align:center;color:var(--muted)">Film non trovato.</div>';
    return;
}

if (!function_exists('slugify')) {
    function slugify(string $s): string {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[^a-z0-9\s\-]/u', '', $s);
        $s = preg_replace('/[\s\-]+/', '-', $s);
        return trim($s, '-');
    }
}

// ── Fetch TMDB in parallelo ───────────────────
$mh = curl_multi_init();
$handles = [];
foreach ([
    'film'    => "movie/{$tmdb_id}?language=it-IT",
    'credits' => "movie/{$tmdb_id}/credits?language=it-IT",
    'similar' => "movie/{$tmdb_id}/similar?language=it-IT&page=1",
] as $key => $ep) {
    $ch = curl_init("https://api.themoviedb.org/3/" . $ep);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $apiKey", "accept: application/json"],
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[$key] = $ch;
}
do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running > 0);

$film    = json_decode(curl_multi_getcontent($handles['film']),    true) ?? [];
$credits = json_decode(curl_multi_getcontent($handles['credits']), true) ?? [];
$similar = json_decode(curl_multi_getcontent($handles['similar']), true) ?? [];

foreach ($handles as $ch) { curl_multi_remove_handle($mh, $ch); curl_close($ch); }
curl_multi_close($mh);

if (empty($film['id'])) {
    echo '<div style="padding:60px;text-align:center;color:var(--muted)">Film non trovato su TMDB.</div>';
    return;
}

// ── Stato utente dal DB ───────────────────────
$pdo    = getConnection();
$my_id  = $_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT V.* FROM Visione V
     JOIN Film F ON V.IDFilm = F.ID
     WHERE V.IDUtente = ? AND F.TMDB_ID = ? LIMIT 1"
);
$stmt->execute([$my_id, $tmdb_id]);
$visione = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$mio_voto = null;
if (!empty($visione)) {
    $stmt = $pdo->prepare(
        "SELECT L.Voto FROM Log L
         JOIN Film F ON L.IDFilm = F.ID
         WHERE L.IDUtente = ? AND F.TMDB_ID = ?
         ORDER BY L.Data_Pubblicazione DESC LIMIT 1"
    );
    $stmt->execute([$my_id, $tmdb_id]);
    $mio_voto = $stmt->fetchColumn();
}

// ── Elaborazione dati TMDB ────────────────────
$cast          = array_slice($credits['cast'] ?? [], 0, 24);
$crew          = $credits['crew'] ?? [];
$similar_films = array_filter(
    array_slice($similar['results'] ?? [], 0, 10),
    fn($f) => !empty($f['poster_path'])
);

$crewGroups = [
    'Regia'         => array_filter($crew, fn($c) => $c['job'] === 'Director'),
    'Sceneggiatura' => array_unique(array_filter($crew, fn($c) => in_array($c['job'], ['Screenplay','Writer','Story','Novel'])), SORT_REGULAR),
    'Produzione'    => array_filter($crew, fn($c) => $c['job'] === 'Producer'),
    'Musiche'       => array_filter($crew, fn($c) => $c['job'] === 'Original Music Composer'),
    'Fotografia'    => array_filter($crew, fn($c) => $c['job'] === 'Director of Photography'),
    'Montaggio'     => array_filter($crew, fn($c) => $c['job'] === 'Editor'),
];

$directors = array_values($crewGroups['Regia']);
$backdrop  = !empty($film['backdrop_path']) ? "https://image.tmdb.org/t/p/w1280" . $film['backdrop_path'] : null;
$poster    = !empty($film['poster_path'])   ? "https://image.tmdb.org/t/p/w500"  . $film['poster_path']   : "/Pulse/IMG/default_list.jpg";
$anno      = !empty($film['release_date'])  ? substr($film['release_date'], 0, 4) : 'N.D.';
$runtime   = $film['runtime'] ?? 0;
$durata    = $runtime ? floor($runtime/60) . 'h ' . ($runtime % 60) . 'min' : null;
$generi    = array_map(fn($g) => $g['name'], $film['genres'] ?? []);
$voto      = number_format($film['vote_average'] ?? 0, 1);
$voti_tot  = number_format($film['vote_count'] ?? 0, 0, ',', '.');
$lingue    = ['en'=>'Inglese','it'=>'Italiano','fr'=>'Francese','de'=>'Tedesco','es'=>'Spagnolo','ja'=>'Giapponese','ko'=>'Coreano','zh'=>'Cinese'];
$lingua_orig = $lingue[$film['original_language'] ?? ''] ?? strtoupper($film['original_language'] ?? '');
$budget    = $film['budget']  ? '$ ' . number_format($film['budget'],  0, ',', '.') : null;
$revenue   = $film['revenue'] ? '$ ' . number_format($film['revenue'], 0, ',', '.') : null;

// Stato pulsanti iniziale
$is_watched   = !empty($visione['Is_Watched']);
$in_watchlist = !empty($visione['In_Watchlist']);
$is_liked     = !empty($visione['Liked']);
$cur_rating   = $mio_voto ? (float)$mio_voto : (float)($visione['Rating'] ?? 0);
?>

<div class="app film-app">
    <?php include "aside.php"; ?>

    <main class="center" style="gap:0; padding:0 0 60px 0; overflow:hidden; min-width:0;">

        <!-- ── HERO BACKDROP ── -->
        <?php if ($backdrop): ?>
            <div class="film-hero" style="background-image:url('<?= $backdrop ?>')">
                <div class="film-hero-overlay"></div>
            </div>
        <?php else: ?>
            <div style="height:100px"></div>
        <?php endif; ?>

        <!-- ── FILM HEADER ── -->
        <section class="film-header">
            <div class="film-poster-wrap">
                <img src="<?= $poster ?>" alt="<?= htmlspecialchars($film['title']) ?>" class="film-poster">
            </div>

            <div class="film-meta">
                <div class="film-title-row">
                    <h1 class="film-title"><?= htmlspecialchars($film['title']) ?></h1>
                    <span class="film-year"><?= $anno ?></span>
                </div>

                <?php if (!empty($film['original_title']) && $film['original_title'] !== $film['title']): ?>
                    <p class="film-original-title"><?= htmlspecialchars($film['original_title']) ?></p>
                <?php endif; ?>

                <?php if ($directors): ?>
                    <p class="film-directed-by">Diretto da
                        <?php foreach ($directors as $i => $d): ?>
                            <?= $i > 0 ? ', ' : ' ' ?><a href="/Pulse/persona/<?= $d['id'] ?>-<?= slugify($d['name']) ?>" class="crew-link"><?= htmlspecialchars($d['name']) ?></a>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>

                <div class="film-tags">
                    <?php foreach ($generi as $g): ?><span class="tag"><?= htmlspecialchars($g) ?></span><?php endforeach; ?>
                    <?php if ($durata): ?><span class="tag tag-neutral"><?= $durata ?></span><?php endif; ?>
                </div>

                <p class="film-overview"><?= htmlspecialchars($film['overview'] ?? 'Nessuna sinossi disponibile.') ?></p>

                <div class="film-scores">
                    <div class="score-circle"><?= $voto ?></div>
                    <div class="score-meta">
                        <strong>TMDB Score</strong>
                        <small><?= $voti_tot ?> voti</small>
                    </div>
                </div>

                <!-- ── Pulsanti con stato dal DB ── -->
                <div class="film-actions">
                    <button class="action-btn <?= $is_watched   ? 'active' : '' ?>"
                            id="btn-watched" data-action="toggle_visto" data-tmdb="<?= $tmdb_id ?>">
                        <i class="bi bi-eye<?= $is_watched ? '-fill' : '' ?>"></i>
                        <span><?= $is_watched ? 'Visto ✓' : 'Visto' ?></span>
                    </button>
                    <button class="action-btn <?= $in_watchlist ? 'active' : '' ?>"
                            id="btn-watchlist" data-action="toggle_watchlist" data-tmdb="<?= $tmdb_id ?>">
                        <i class="bi bi-clock<?= $in_watchlist ? '-fill' : '' ?>"></i>
                        <span><?= $in_watchlist ? 'In lista ✓' : 'Watchlist' ?></span>
                    </button>
                    <button class="action-btn <?= $is_liked ? 'active-heart' : '' ?>"
                            id="btn-like" data-action="toggle_like" data-tmdb="<?= $tmdb_id ?>">
                        <i class="bi bi-heart<?= $is_liked ? '-fill' : '' ?>"></i>
                        <span>Like</span>
                    </button>
                    <a href="/Pulse/crea_log?tmdb_id=<?= $film['id'] ?>" class="action-btn action-btn-primary">
                        <i class="bi bi-pencil"></i><span>Recensisci</span>
                    </a>
                </div>

                <!-- ── Half-star picker ── -->
                <div class="star-rating" id="starRating" data-tmdb="<?= $tmdb_id ?>">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star-btn" data-val="<?= $i ?>">★</span>
                    <?php endfor; ?>
                    <span class="star-label" id="starLabel">
                        <?= $cur_rating ? "Voto: {$cur_rating}/5" : "Il tuo voto" ?>
                    </span>
                </div>
            </div>
        </section>

        <!-- ── LA TUA RECENSIONE (se esiste) ── -->
        <?php
        // Cerca ultimo log dell'utente per questo film
        $stmt_rev = $pdo->prepare("
            SELECT L.ID, L.Voto, L.Recensione, L.Data
            FROM Log L JOIN Film F ON L.IDFilm = F.ID
            WHERE L.IDUtente = ? AND F.TMDB_ID = ?
            ORDER BY L.Data_Pubblicazione DESC LIMIT 1
        ");
        $stmt_rev->execute([$my_id, $tmdb_id]);
        $mia_rec = $stmt_rev->fetch(PDO::FETCH_ASSOC);
        if ($mia_rec): ?>
        <section class="film-section">
            <h2 class="section-title">La tua recensione</h2>
            <div style="background:var(--panel);border:1px solid rgba(139,92,246,.2);border-radius:var(--radius);padding:20px;">
                <?php if ($mia_rec['Voto']): ?>
                    <div class="stars-display" style="font-size:18px;margin-bottom:8px;">
                        <?php for ($i=1;$i<=5;$i++): ?>
                            <span class="sd-star <?= (float)$mia_rec['Voto']>=$i ? 'full' : ((float)$mia_rec['Voto']>=$i-.5 ? 'half' : '') ?>">★</span>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($mia_rec['Recensione'])): ?>
                    <p style="font-size:14px;line-height:1.8;color:var(--muted);">
                        <?= nl2br(htmlspecialchars($mia_rec['Recensione'])) ?>
                    </p>
                <?php else: ?>
                    <p style="color:var(--muted);font-size:13px;font-style:italic;">Hai loggato questo film il <?= date('d M Y', strtotime($mia_rec['Data'])) ?> senza recensione.</p>
                <?php endif; ?>
                <div style="margin-top:14px;display:flex;gap:10px;">
                    <a href="/Pulse/crea_log?tmdb_id=<?= $tmdb_id ?>" class="action-btn" style="font-size:12px;padding:7px 14px;">
                        <i class="bi bi-pencil"></i> Modifica
                    </a>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ── CAST (grid wrapping, nessun scroll) ── -->
        <?php if ($cast): ?>
        <section class="film-section">
            <h2 class="section-title">Cast</h2>
            <div class="cast-grid">
                <?php foreach ($cast as $a):
                    $foto = $a['profile_path']
                        ? "https://image.tmdb.org/t/p/w185" . $a['profile_path']
                        : "https://ui-avatars.com/api/?name=" . urlencode($a['name']) . "&background=1e2535&color=9aa3b2&size=185";
                ?>
                    <a href="/Pulse/persona/<?= $a['id'] ?>-<?= slugify($a['name']) ?>" class="cast-card-film">
                        <img src="<?= $foto ?>" alt="<?= htmlspecialchars($a['name']) ?>" class="cast-card-photo" loading="lazy">
                        <div class="cast-card-info">
                            <strong><?= htmlspecialchars($a['name']) ?></strong>
                            <span><?= htmlspecialchars($a['character'] ?? '') ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ── CREW ── -->
        <section class="film-section">
            <h2 class="section-title">Crew</h2>
            <div class="crew-grid">
                <?php foreach ($crewGroups as $label => $members):
                    $members = array_values($members);
                    if (empty($members)) continue;
                ?>
                    <div class="crew-group">
                        <span class="crew-group-label"><?= $label ?></span>
                        <?php foreach (array_slice($members, 0, 3) as $m): ?>
                            <a href="/Pulse/persona/<?= $m['id'] ?>-<?= slugify($m['name']) ?>" class="crew-member-link">
                                <?= htmlspecialchars($m['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ── DETTAGLI ── -->
        <section class="film-section">
            <h2 class="section-title">Dettagli</h2>
            <div class="film-details-table">
                <?php if (!empty($film['release_date'])): ?>
                    <div class="fd-item"><span class="fd-label">Data uscita</span><span class="fd-value"><?= date('d M Y', strtotime($film['release_date'])) ?></span></div>
                <?php endif; ?>
                <?php if ($lingua_orig): ?>
                    <div class="fd-item"><span class="fd-label">Lingua originale</span><span class="fd-value"><?= htmlspecialchars($lingua_orig) ?></span></div>
                <?php endif; ?>
                <?php if ($budget): ?>
                    <div class="fd-item"><span class="fd-label">Budget</span><span class="fd-value"><?= $budget ?></span></div>
                <?php endif; ?>
                <?php if ($revenue): ?>
                    <div class="fd-item"><span class="fd-label">Incasso</span><span class="fd-value"><?= $revenue ?></span></div>
                <?php endif; ?>
                <?php $paesi = array_map(fn($c) => $c['name'], $film['production_countries'] ?? []); ?>
                <?php if ($paesi): ?>
                    <div class="fd-item"><span class="fd-label">Paese</span><span class="fd-value"><?= htmlspecialchars(implode(', ', $paesi)) ?></span></div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ── FILM SIMILI ── -->
        <?php if ($similar_films): ?>
        <section class="film-section">
            <h2 class="section-title">Film Simili</h2>
            <div class="similar-grid">
                <?php foreach ($similar_films as $s):
                    $stit = $s['title'] ?? $s['original_title'] ?? '';
                    $san  = !empty($s['release_date']) ? substr($s['release_date'], 0, 4) : '';
                ?>
                    <a href="/Pulse/film/<?= $s['id'] ?>-<?= slugify($stit) ?>" class="similar-card">
                        <img src="https://image.tmdb.org/t/p/w300<?= $s['poster_path'] ?>" alt="<?= htmlspecialchars($stit) ?>" loading="lazy">
                        <div class="similar-info">
                            <strong><?= htmlspecialchars($stit) ?></strong>
                            <small><?= $san ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </main>
</div>

<script>
const BACKEND = '/Pulse/backend/GestioneFilm.php';

async function filmAction(action, tmdb_id, extra = {}) {
    try {
        const res = await fetch(BACKEND, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, tmdb_id, ...extra })
        });
        return await res.json();
    } catch (e) {
        console.error('AJAX error:', e);
        return { ok: false };
    }
}

// Toggle visto / watchlist / like
document.querySelectorAll('.action-btn[data-action]').forEach(btn => {
    btn.addEventListener('click', async function () {
        const action  = this.dataset.action;
        const tmdb_id = +this.dataset.tmdb;
        const icon    = this.querySelector('i');
        const label   = this.querySelector('span');

        const result  = await filmAction(action, tmdb_id);
        if (!result.ok) { alert('Errore: ' + (result.error ?? 'sconosciuto')); return; }

        const stato = result.stato;

        if (action === 'toggle_visto') {
            this.classList.toggle('active', stato);
            icon.className    = stato ? 'bi bi-eye-fill' : 'bi bi-eye';
            label.textContent = stato ? 'Visto ✓' : 'Visto';
        }
        if (action === 'toggle_watchlist') {
            this.classList.toggle('active', stato);
            icon.className    = stato ? 'bi bi-clock-fill' : 'bi bi-clock';
            label.textContent = stato ? 'In lista ✓' : 'Watchlist';
        }
        if (action === 'toggle_like') {
            this.classList.toggle('active-heart', stato);
            icon.className = stato ? 'bi bi-heart-fill' : 'bi bi-heart';
        }
    });
});

// Star rating (half-star)
class StarPicker {
    constructor(containerId, hiddenVal, labelId) {
        this.box   = document.getElementById(containerId);
        this.label = document.getElementById(labelId);
        this.value = hiddenVal;
        this.stars = [...this.box.querySelectorAll('.star-btn')];
        this.labels= ['Il tuo voto','½★','1★','1½★','2★','2½★','3★','3½★','4★','4½★','5★'];
        this.init();
        this.repaint(this.value);
    }
    getVal(s,e){ const r=s.getBoundingClientRect(); return (+s.dataset.val)-(e.clientX<r.left+r.width/2?.5:0); }
    repaint(val){
        this.stars.forEach(s=>{
            s.className='star-btn'; s.style.cssText='';
            const sv=+s.dataset.val;
            if(val>=sv) s.classList.add('s-full');
            else if(val>=sv-.5) s.classList.add('s-half');
        });
    }
    hover(val){
        this.stars.forEach(s=>{
            s.className='star-btn'; s.style.cssText='';
            const sv=+s.dataset.val;
            if(val>=sv) s.classList.add('s-hover-full');
            else if(val>=sv-.5) s.classList.add('s-hover-half');
        });
    }
    init(){
        this.stars.forEach(s=>{
            s.addEventListener('mousemove',e=>this.hover(this.getVal(s,e)));
            s.addEventListener('click',async e=>{
                if(saving) return;
                const val=this.getVal(s,e);
                this.value=val; this.repaint(val);
                const idx=Math.round(val*2);
                if(this.label) this.label.textContent='Salvataggio…';
                saving=true;
                const r=await filmAction('set_rating',+ratingBox.dataset.tmdb,{rating:val});
                if(this.label) this.label.textContent=r.ok?(this.labels[idx]??''):'Errore';
                saving=false;
            });
        });
        this.box.addEventListener('mouseleave',()=>this.repaint(this.value));
    }
}

const ratingBox = document.getElementById('starRating');
let saving = false;
const filmStars = new StarPicker('starRating', <?= (float)$cur_rating ?>, 'starLabel');
</script>