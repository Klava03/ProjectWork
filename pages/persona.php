<?php
// pages/Persona.php — incluso da index.php
// $sub = "287-brad-pitt"   → intval() estrae 287

$apiKey = "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJiY2U5ZWZmYjRjMDA5ZTNmNGZmZTM1N2QzOTlhMTk4NSIsIm5iZiI6MTc2OTc4NDc2MC43NzcsInN1YiI6IjY5N2NjNWI4MWVhOGNmMGRlZTI2YWVmNSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.cPNGTwdDeQD6TGYY2Shn-i3Y0i-OjMSey9KPpdrpBAE";

$tmdb_id = (int)($sub ?? 0);

if (!$tmdb_id) {
    echo '<div style="padding:60px;text-align:center;color:var(--muted)">Persona non trovata.</div>';
    return;
}

if (!function_exists('tmdb_call')) {
    function tmdb_call(string $ep, string $key): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "https://api.themoviedb.org/3/" . $ep,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer $key", "accept: application/json"],
        ]);
        $r = curl_exec($ch); curl_close($ch);
        return json_decode($r ?: '{}', true) ?? [];
    }
}

if (!function_exists('slugify')) {
    function slugify(string $s): string {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[^a-z0-9\s\-]/u', '', $s);
        $s = preg_replace('/[\s\-]+/', '-', $s);
        return trim($s, '-');
    }
}

// Fetch persona + credits in parallelo
$mh = curl_multi_init();
$eps = [
    'persona' => "person/{$tmdb_id}?language=it-IT",
    'credits' => "person/{$tmdb_id}/movie_credits?language=it-IT",
];
$handles = [];
foreach ($eps as $k => $ep) {
    $ch = curl_init("https://api.themoviedb.org/3/" . $ep);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $apiKey", "accept: application/json"],
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[$k] = $ch;
}
do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running > 0);

$persona = json_decode(curl_multi_getcontent($handles['persona']), true) ?? [];
$credits = json_decode(curl_multi_getcontent($handles['credits']), true) ?? [];

foreach ($handles as $ch) { curl_multi_remove_handle($mh, $ch); curl_close($ch); }
curl_multi_close($mh);

if (empty($persona['id'])) {
    echo '<div style="padding:60px;text-align:center;color:var(--muted)">Persona non trovata su TMDB.</div>';
    return;
}

// Cast credits: ordina per popolarità, rimuovi senza poster
$as_cast = array_filter($credits['cast'] ?? [], fn($f) => !empty($f['poster_path']));
usort($as_cast, fn($a,$b) => ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0));
$as_cast = array_slice($as_cast, 0, 24);

// Crew credits: dedup per film, ordina per popolarità, rimuovi senza poster
$crew_raw = array_filter($credits['crew'] ?? [], fn($f) => !empty($f['poster_path']));
usort($crew_raw, fn($a,$b) => ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0));
$seen = [];
$as_crew = [];
foreach ($crew_raw as $c) {
    if (!isset($seen[$c['id']])) {
        $seen[$c['id']] = true;
        $as_crew[] = $c;
    }
}
$as_crew = array_slice($as_crew, 0, 20);

$foto = $persona['profile_path']
    ? "https://image.tmdb.org/t/p/w300" . $persona['profile_path']
    : "https://ui-avatars.com/api/?name=" . urlencode($persona['name']) . "&background=1e2535&color=9aa3b2&size=300";

$bio_full  = $persona['biography'] ?? '';
$bio_short = mb_strlen($bio_full) > 600 ? mb_substr($bio_full, 0, 600) . '…' : $bio_full;
$has_more  = mb_strlen($bio_full) > 600;

$birthday = !empty($persona['birthday'])
    ? date('d M Y', strtotime($persona['birthday']))
    : null;
$deathday = !empty($persona['deathday'])
    ? date('d M Y', strtotime($persona['deathday']))
    : null;

// Film noti per l'header (massimo 3)
$known = array_slice($as_cast, 0, 3);
$known_titles = implode(', ', array_map(fn($f) => $f['title'] ?? '', $known));
?>

<div class="app persona-app">
    <?php include "aside.php"; ?>

    <main class="center" style="gap:0; padding:0 0 60px 0;">

        <!-- ── HEADER PERSONA ── -->
        <section class="persona-header" style="padding-top:24px;">

            <img src="<?= $foto ?>" alt="<?= htmlspecialchars($persona['name']) ?>" class="persona-photo">

            <div class="persona-info">
                <h1 class="persona-name"><?= htmlspecialchars($persona['name']) ?></h1>

                <?php if (!empty($persona['known_for_department'])): ?>
                    <span class="persona-dept"><?= htmlspecialchars($persona['known_for_department']) ?></span>
                <?php endif; ?>

                <!-- Dettagli biografici -->
                <div class="persona-details">
                    <?php if ($birthday): ?>
                        <div class="detail-item">
                            <span class="detail-label">Nato il</span>
                            <span><?= $birthday ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($deathday): ?>
                        <div class="detail-item">
                            <span class="detail-label">Morto il</span>
                            <span><?= $deathday ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($persona['place_of_birth'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Nato a</span>
                            <span><?= htmlspecialchars($persona['place_of_birth']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($known_titles): ?>
                        <div class="detail-item">
                            <span class="detail-label">Noto per</span>
                            <span><?= htmlspecialchars($known_titles) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Biografia -->
                <?php if ($bio_full): ?>
                    <div class="persona-bio">
                        <p id="bioShort"><?= nl2br(htmlspecialchars($bio_short)) ?></p>
                        <?php if ($has_more): ?>
                            <button class="bio-toggle" id="bioToggle">Leggi di più</button>
                            <p id="bioFull" style="display:none"><?= nl2br(htmlspecialchars($bio_full)) ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--muted);font-size:14px;">Nessuna biografia disponibile.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- ── FILMOGRAFIA COME ATTORE ── -->
        <?php if ($as_cast): ?>
        <section class="persona-section">
            <h2 class="section-title-p">
                Filmografia — Attore/Attrice
                <span style="color:var(--muted);font-weight:400">(<?= count($as_cast) ?> titoli)</span>
            </h2>
            <div class="film-grid-persona">
                <?php foreach ($as_cast as $f):
                    $sp = "https://image.tmdb.org/t/p/w300" . $f['poster_path'];
                    $st = htmlspecialchars($f['title'] ?? '');
                    $sa = !empty($f['release_date']) ? substr($f['release_date'], 0, 4) : '';
                ?>
                    <a href="/Pulse/film/<?= $f['id'] ?>-<?= slugify($f['title'] ?? '') ?>" class="film-card-p">
                        <img src="<?= $sp ?>" alt="<?= $st ?>" loading="lazy">
                        <div class="film-card-p-info">
                            <strong><?= $st ?></strong>
                            <span><?= $sa ?><?= !empty($f['character']) ? ' · ' . htmlspecialchars(mb_substr($f['character'],0,30)) : '' ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ── FILMOGRAFIA COME CREW ── -->
        <?php if ($as_crew): ?>
        <section class="persona-section">
            <h2 class="section-title-p">
                Filmografia — Regia / Crew
                <span style="color:var(--muted);font-weight:400">(<?= count($as_crew) ?> titoli)</span>
            </h2>
            <div class="film-grid-persona">
                <?php foreach ($as_crew as $f):
                    $sp = "https://image.tmdb.org/t/p/w300" . $f['poster_path'];
                    $st = htmlspecialchars($f['title'] ?? '');
                    $sa = !empty($f['release_date']) ? substr($f['release_date'], 0, 4) : '';
                ?>
                    <a href="/Pulse/film/<?= $f['id'] ?>-<?= slugify($f['title'] ?? '') ?>" class="film-card-p">
                        <img src="<?= $sp ?>" alt="<?= $st ?>" loading="lazy">
                        <div class="film-card-p-info">
                            <strong><?= $st ?></strong>
                            <span><?= $sa ?><?= !empty($f['job']) ? ' · ' . htmlspecialchars($f['job']) : '' ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </main>
</div>

<script>
const bioToggle = document.getElementById('bioToggle');
if (bioToggle) {
    bioToggle.addEventListener('click', function() {
        const full  = document.getElementById('bioFull');
        const short = document.getElementById('bioShort');
        if (full.style.display === 'none') {
            full.style.display  = 'block';
            short.style.display = 'none';
            this.textContent    = 'Mostra meno';
        } else {
            full.style.display  = 'none';
            short.style.display = 'block';
            this.textContent    = 'Leggi di più';
        }
    });
}
</script>