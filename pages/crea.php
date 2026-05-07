<?php
// pages/Crea.php — incluso da index.php
// Gestisce anche la rotta /Pulse/crea_log?tmdb_id=X (film pre-selezionato)

// Se arriva da /Pulse/crea_log, forza il tab "log" e legge il tmdb_id
$current_tab = 'log';   // unico tab attivo per ora
$preloaded_tmdb = (int)($_GET['tmdb_id'] ?? 0);

// Se arriva da crea_log con tmdb_id, prendiamo i dati del film da TMDB
$preloaded_film = null;
if ($preloaded_tmdb) {
    $apiKey = "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJiY2U5ZWZmYjRjMDA5ZTNmNGZmZTM1N2QzOTlhMTk4NSIsIm5iZiI6MTc2OTc4NDc2MC43NzcsInN1YiI6IjY5N2NjNWI4MWVhOGNmMGRlZTI2YWVmNSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.cPNGTwdDeQD6TGYY2Shn-i3Y0i-OjMSey9KPpdrpBAE";
    $ch = curl_init("https://api.themoviedb.org/3/movie/{$preloaded_tmdb}?language=it-IT");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $apiKey", "accept: application/json"],
    ]);
    $res = json_decode(curl_exec($ch) ?: '{}', true) ?? [];
    curl_close($ch);
    if (!empty($res['id'])) {
        $preloaded_film = [
            'id'          => $res['id'],
            'title'       => $res['title'] ?? '',
            'year'        => !empty($res['release_date']) ? substr($res['release_date'], 0, 4) : '',
            'poster_path' => $res['poster_path'] ?? null,
        ];
    }
}
?>

<div class="app" style="grid-template-columns: var(--w-left) 1fr !important;">
    <?php include "aside.php"; ?>

    <main class="center">
        <div class="crea-container">
            <h2 class="crea-title">Crea</h2>

            <!-- ── TAB BAR ── -->
            <div class="crea-tabs">
                <button class="crea-tab active" id="tab-log">
                    <i class="bi bi-journal-text"></i> Log
                </button>
                <button class="crea-tab disabled" title="Prossimamente">
                    <i class="bi bi-card-text"></i> Post
                </button>
            </div>

            <!-- ════════════════════════
                 TAB LOG
                 ════════════════════════ -->
            <div id="panel-log">

                <!-- STEP 1: Ricerca film (nascosta se film già selezionato) -->
                <div id="step-search" <?= $preloaded_film ? 'style="display:none"' : '' ?>>
                    <div class="film-search-wrap">
                        <input type="text"
                               id="filmSearchInput"
                               class="film-search-input"
                               placeholder="Cerca un film da loggare…"
                               autocomplete="off">
                        <i class="bi bi-search film-search-icon"></i>
                    </div>
                    <div class="search-loading" id="searchLoading">Ricerca in corso…</div>
                    <div class="film-results" id="filmResults"></div>
                </div>

                <!-- STEP 2: Film selezionato + form -->
                <div id="step-form" <?= $preloaded_film ? '' : 'style="display:none"' ?>>

                    <!-- Film selezionato -->
                    <div class="selected-film" id="selectedFilmBox">
                        <?php if ($preloaded_film): ?>
                            <?php $pp = $preloaded_film['poster_path']
                                ? "https://image.tmdb.org/t/p/w200" . $preloaded_film['poster_path']
                                : "/Pulse/IMG/default_list.jpg"; ?>
                            <img src="<?= $pp ?>" alt="Poster" id="selPoster">
                            <div class="selected-film-info">
                                <h3 id="selTitle"><?= htmlspecialchars($preloaded_film['title']) ?></h3>
                                <small id="selYear"><?= $preloaded_film['year'] ?></small>
                            </div>
                        <?php else: ?>
                            <img src="" alt="Poster" id="selPoster" style="display:none">
                            <div class="selected-film-info">
                                <h3 id="selTitle"></h3>
                                <small id="selYear"></small>
                            </div>
                        <?php endif; ?>
                        <button class="change-film-btn" id="changeFilmBtn" onclick="resetFilm()">
                            Cambia film
                        </button>
                    </div>

                    <!-- FORM LOG -->
                    <form class="log-form" id="logForm">
                        <input type="hidden" id="selectedTmdbId" value="<?= $preloaded_tmdb ?: '' ?>">

                        <!-- Data visione -->
                        <div class="form-field">
                            <label class="form-label" for="dataVisione">Data visione</label>
                            <input type="date" id="dataVisione" class="form-input"
                                   value="<?= date('Y-m-d') ?>"
                                   max="<?= date('Y-m-d') ?>">
                        </div>

                        <!-- Voto (half-star) -->
                        <div class="form-field">
                            <label class="form-label">Voto</label>
                            <div class="log-star-picker" id="logStarPicker">
                                <span class="log-star" data-val="1">★</span>
                                <span class="log-star" data-val="2">★</span>
                                <span class="log-star" data-val="3">★</span>
                                <span class="log-star" data-val="4">★</span>
                                <span class="log-star" data-val="5">★</span>
                                <span class="star-val-label" id="starValLabel">Nessun voto</span>
                            </div>
                            <input type="hidden" id="votoHidden" value="">
                        </div>

                        <!-- Mi piace -->
                        <div class="form-field">
                            <label class="form-label">Ti è piaciuto?</label>
                            <div class="like-toggle" id="likeToggle">
                                <span class="heart-icon">♥</span>
                                <span>Mi piace</span>
                            </div>
                            <input type="hidden" id="likedHidden" value="0">
                        </div>

                        <!-- Recensione -->
                        <div class="form-field">
                            <label class="form-label" for="recensioneText">
                                Recensione <span style="font-weight:400;text-transform:none;">(opzionale)</span>
                            </label>
                            <textarea id="recensioneText" class="form-textarea"
                                      placeholder="Scrivi i tuoi pensieri su questo film…"
                                      rows="5"></textarea>
                        </div>

                        <!-- Azioni -->
                        <div class="form-actions">
                            <button type="submit" class="btn-submit" id="btnSalva">
                                <i class="bi bi-check-lg"></i> Salva Log
                            </button>
                            <a href="/Pulse/profilo" class="btn-cancel">Annulla</a>
                        </div>
                    </form>
                </div>

            </div><!-- /panel-log -->
        </div>
    </main>
</div>

<!-- Toast feedback -->
<div class="toast" id="toast"></div>

<script>
// ── Utilities ─────────────────────────────────
const BACKEND_LOG  = '/Pulse/backend/GestioneLog.php';
const noImg        = '/Pulse/IMG/default_list.jpg';

function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = `toast ${type} show`;
    setTimeout(() => t.classList.remove('show'), 3200);
}

// ── HALF-STAR PICKER ──────────────────────────
class StarPicker {
    constructor(containerId, hiddenId, labelId, size = 36) {
        this.container = document.getElementById(containerId);
        this.hidden    = document.getElementById(hiddenId);
        this.label     = document.getElementById(labelId);
        this.value     = 0;
        this.stars     = [...this.container.querySelectorAll('.log-star')];
        this.init();
    }

    labels = ['Nessun voto','½ stella','1 stella','1½ stelle','2 stelle',
               '2½ stelle','3 stelle','3½ stelle','4 stelle','4½ stelle','5 stelle'];

    getVal(star, e) {
        const r = star.getBoundingClientRect();
        const half = e.clientX < r.left + r.width / 2;
        return (+star.dataset.val) - (half ? 0.5 : 0);
    }

    clearClass(cls) { this.stars.forEach(s => { s.classList.remove(cls); s.style.cssText=''; }); }

    paint(val, prefix) {
        this.clearClass(`${prefix}-full`);
        this.clearClass(`${prefix}-half`);
        this.stars.forEach(s => {
            const sv = +s.dataset.val;
            if (val >= sv) s.classList.add(`s-${prefix}-full`);
            else if (val >= sv - 0.5) s.classList.add(`s-${prefix}-half`);
        });
    }

    // fix: clear all visual states
    repaint(val) {
        this.stars.forEach(s => {
            s.className = 'log-star';
            s.style.cssText = '';
            const sv = +s.dataset.val;
            if (val >= sv)         s.classList.add('s-full');
            else if (val >= sv-.5) s.classList.add('s-half');
        });
    }

    hover(val) {
        this.stars.forEach(s => {
            s.className = 'log-star';
            s.style.cssText = '';
            const sv = +s.dataset.val;
            if (val >= sv)         s.classList.add('s-hover-full');
            else if (val >= sv-.5) s.classList.add('s-hover-half');
        });
    }

    init() {
        this.stars.forEach(s => {
            s.addEventListener('mousemove', e => this.hover(this.getVal(s, e)));
            s.addEventListener('click', e => {
                this.value = this.getVal(s, e);
                this.hidden.value = this.value;
                this.repaint(this.value);
                const idx = Math.round(this.value * 2);
                if (this.label) this.label.textContent = this.labels[idx] ?? '';
            });
        });
        this.container.addEventListener('mouseleave', () => this.repaint(this.value));
    }

    set(val) {
        this.value = val;
        this.hidden.value = val;
        this.repaint(val);
        const idx = Math.round(val * 2);
        if (this.label) this.label.textContent = this.labels[idx] ?? '';
    }
}

const starPicker = new StarPicker('logStarPicker', 'votoHidden', 'starValLabel');

// ── LIKE TOGGLE ───────────────────────────────
document.getElementById('likeToggle').addEventListener('click', function() {
    const liked = this.classList.toggle('liked');
    document.getElementById('likedHidden').value = liked ? '1' : '0';
});

// ── RICERCA FILM ──────────────────────────────
let searchTimer = null;
const searchInput = document.getElementById('filmSearchInput');
const resultsBox  = document.getElementById('filmResults');
const loadingEl   = document.getElementById('searchLoading');

searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) { resultsBox.innerHTML = ''; return; }
    loadingEl.style.display = 'block';
    searchTimer = setTimeout(() => searchFilm(q), 380);
});

async function searchFilm(q) {
    try {
        const res = await fetch(BACKEND_LOG, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cerca_film', q })
        });
        const data = await res.json();
        loadingEl.style.display = 'none';
        renderResults(data.films ?? []);
    } catch (e) {
        loadingEl.style.display = 'none';
    }
}

function renderResults(films) {
    resultsBox.innerHTML = '';
    if (!films.length) {
        resultsBox.innerHTML = '<p style="color:var(--muted);font-size:13px;padding:10px 0;">Nessun risultato.</p>';
        return;
    }
    films.forEach(f => {
        const el = document.createElement('div');
        el.className = 'film-result-item';
        const poster = f.poster_path
            ? `https://image.tmdb.org/t/p/w200${f.poster_path}`
            : noImg;
        el.innerHTML = `
            <img src="${poster}" class="film-result-poster" alt="">
            <div class="film-result-info">
                <strong>${f.title}</strong>
                <small>${f.year}</small>
            </div>
        `;
        el.addEventListener('click', () => selectFilm(f));
        resultsBox.appendChild(el);
    });
}

function selectFilm(film) {
    document.getElementById('selectedTmdbId').value = film.id;
    document.getElementById('selTitle').textContent  = film.title;
    document.getElementById('selYear').textContent   = film.year;
    const img = document.getElementById('selPoster');
    img.src = film.poster_path ? `https://image.tmdb.org/t/p/w200${film.poster_path}` : noImg;
    img.style.display = '';
    document.getElementById('step-search').style.display = 'none';
    document.getElementById('step-form').style.display   = '';
    starPicker.set(0);
}

function resetFilm() {
    document.getElementById('selectedTmdbId').value = '';
    document.getElementById('step-form').style.display   = 'none';
    document.getElementById('step-search').style.display = '';
    document.getElementById('filmSearchInput').value = '';
    document.getElementById('filmResults').innerHTML  = '';
    starPicker.set(0);
    document.getElementById('likedHidden').value = '0';
    document.getElementById('likeToggle').classList.remove('liked');
    document.getElementById('recensioneText').value = '';
}

// ── SUBMIT FORM ───────────────────────────────
document.getElementById('logForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const tmdb_id   = +document.getElementById('selectedTmdbId').value;
    const data      = document.getElementById('dataVisione').value;
    const voto      = parseFloat(document.getElementById('votoHidden').value) || null;
    const recensione= document.getElementById('recensioneText').value.trim();
    const liked     = document.getElementById('likedHidden').value === '1';

    if (!tmdb_id) { showToast('Seleziona un film prima di salvare.', 'error'); return; }

    const btn = document.getElementById('btnSalva');
    btn.disabled = true;
    btn.textContent = 'Salvataggio…';

    try {
        const res  = await fetch(BACKEND_LOG, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'salva_log', tmdb_id, data, voto, recensione, liked })
        });
        const json = await res.json();

        if (json.ok) {
            showToast('✓ Log salvato con successo!', 'success');
            setTimeout(() => window.location.href = '/Pulse/profilo', 1200);
        } else {
            showToast('Errore: ' + (json.error ?? 'sconosciuto'), 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Salva Log';
        }
    } catch (err) {
        showToast('Errore di connessione.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Salva Log';
    }
});
</script>