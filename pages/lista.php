<?php
// pages/Lista.php — Dettaglio lista singola (stile Letterboxd)

require_once 'Database.php';
$pdo   = getConnection();
$my_id = (int)$_SESSION['user_id'];

$lista_id = (int)($_GET['id'] ?? 0);
if (!$lista_id) {
    echo '<div style="padding:60px;text-align:center;color:var(--muted)">Lista non trovata.</div>';
    return;
}

// ── Carica lista ─────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT L.IDLista, L.Titolo, L.Descrizione, L.IDUtente,
           U.Username, U.Avatar_URL
    FROM Lista L
    JOIN Utente U ON L.IDUtente = U.ID
    WHERE L.IDLista = ? LIMIT 1
");
$stmt->execute([$lista_id]);
$lista = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lista) {
    echo '<div style="padding:60px;text-align:center;color:var(--muted)">Lista non trovata.</div>';
    return;
}

$is_own = (int)$lista['IDUtente'] === $my_id;

// ── Avatar proprietario ──────────────────────────────────────────
$rawAv = $lista['Avatar_URL'];
if (!$rawAv) {
    $ownerAvatar = "https://ui-avatars.com/api/?name=" . urlencode($lista['Username']) . "&background=8b5cf6&color=fff&size=80";
} elseif (str_starts_with($rawAv, 'http')) {
    $ownerAvatar = $rawAv;
} else {
    $ownerAvatar = '/Pulse/IMG/avatars/' . $rawAv;
}

// ── Film della lista in ordine ───────────────────────────────────
$stmt = $pdo->prepare("
    SELECT F.ID AS film_db_id, F.TMDB_ID, F.Title, F.Poster_Path, F.Release_Date,
           LF.Posizione
    FROM Lista_Film LF
    JOIN Film F ON LF.IDFilm = F.ID
    WHERE LF.IDLista = ?
    ORDER BY LF.Posizione ASC
");
$stmt->execute([$lista_id]);
$films = $stmt->fetchAll(PDO::FETCH_ASSOC);
$tot   = count($films);

// ── Cover per header (prime 4 immagini) ─────────────────────────
$covers = array_slice(array_filter(array_map(
    fn($f) => $f['Poster_Path'] ? "https://image.tmdb.org/t/p/w200" . $f['Poster_Path'] : null,
    $films
)), 0, 4);
?>

<div class="app">
    <?php require 'aside.php'; ?>

    <main class="center lt-center">

        <!-- ── BACK ── -->
        <div class="lt-back">
            <a href="/Pulse/liste" class="lt-back-link">
                <i class="bi bi-arrow-left"></i> Le liste
            </a>
        </div>

        <!-- ── HEADER LISTA (stile Letterboxd) ── -->
        <header class="lt-detail-header">
            <!-- Mosaico cover -->
            <?php if (!empty($covers)): ?>
            <div class="lt-detail-covers lt-covers-<?= count($covers) ?>">
                <?php foreach ($covers as $c): ?>
                    <img src="<?= htmlspecialchars($c) ?>" alt="" class="lt-cover-img">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Info testo -->
            <div class="lt-detail-info">
                <div class="lt-detail-meta-top">
                    <img src="<?= htmlspecialchars($ownerAvatar) ?>" alt="" class="lt-detail-avatar">
                    <div>
                        <a href="/Pulse/utente/<?= urlencode($lista['Username']) ?>" class="lt-detail-owner">
                            @<?= htmlspecialchars($lista['Username']) ?>
                        </a>
                        <div class="lt-detail-meta-sub">Lista · <?= $tot ?> film</div>
                    </div>
                </div>

                <h1 class="lt-detail-title" id="lt-title-display">
                    <?= htmlspecialchars($lista['Titolo']) ?>
                </h1>

                <?php if (!empty($lista['Descrizione'])): ?>
                <p class="lt-detail-desc" id="lt-desc-display">
                    <?= nl2br(htmlspecialchars($lista['Descrizione'])) ?>
                </p>
                <?php else: ?>
                <p class="lt-detail-desc lt-muted" id="lt-desc-display">
                    <?= $is_own ? 'Aggiungi una descrizione…' : '' ?>
                </p>
                <?php endif; ?>

                <!-- Azioni proprietario -->
                <?php if ($is_own): ?>
                <div class="lt-detail-actions">
                    <button class="lt-btn-ghost" onclick="openEditModal()">
                        <i class="bi bi-pencil"></i> Modifica
                    </button>
                    <button class="lt-btn-danger" onclick="confirmDelete()">
                        <i class="bi bi-trash"></i> Elimina lista
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </header>

        <!-- ── AGGIUNGI FILM (solo owner) ── -->
        <?php if ($is_own): ?>
        <section class="lt-add-section">
            <div class="lt-add-bar">
                <i class="bi bi-search lt-add-icon"></i>
                <input type="text" id="lt-film-search"
                       class="lt-add-input"
                       placeholder="Aggiungi un film alla lista…"
                       autocomplete="off">
                <span class="lt-search-loader" id="lt-loader" style="display:none">
                    <i class="bi bi-hourglass-split"></i>
                </span>
            </div>
            <div class="lt-search-results" id="lt-search-results"></div>
        </section>
        <?php endif; ?>

        <!-- ── FILM NELLA LISTA ── -->
        <section class="lt-films-section">
            <?php if (empty($films)): ?>
            <div class="lt-empty" style="padding:60px 20px">
                <i class="bi bi-film" style="font-size:48px;color:var(--muted)"></i>
                <p><?= $is_own ? 'La lista è vuota. Cerca un film qui sopra per aggiungerlo.' : 'Questa lista non ha ancora film.' ?></p>
            </div>

            <?php else: ?>
            <div class="lt-films-list" id="lt-films-sortable">
                <?php foreach ($films as $i => $f):
                    $poster = $f['Poster_Path']
                        ? "https://image.tmdb.org/t/p/w185" . $f['Poster_Path']
                        : null;
                    $anno   = !empty($f['Release_Date']) ? substr($f['Release_Date'], 0, 4) : '';
                ?>
                <div class="lt-film-row <?= $is_own ? 'sortable-item' : '' ?>"
                     data-tmdb="<?= (int)$f['TMDB_ID'] ?>"
                     data-pos="<?= (int)$f['Posizione'] ?>">

                    <?php if ($is_own): ?>
                    <div class="lt-drag-handle" title="Trascina per riordinare">
                        <i class="bi bi-grip-vertical"></i>
                    </div>
                    <?php endif; ?>

                    <span class="lt-film-num"><?= $i + 1 ?></span>

                    <a href="/Pulse/film/<?= (int)$f['TMDB_ID'] ?>" class="lt-film-poster-wrap">
                        <?php if ($poster): ?>
                            <img src="<?= htmlspecialchars($poster) ?>"
                                 alt="<?= htmlspecialchars($f['Title']) ?>"
                                 class="lt-film-poster" loading="lazy">
                        <?php else: ?>
                            <div class="lt-film-poster-empty">
                                <i class="bi bi-film"></i>
                            </div>
                        <?php endif; ?>
                    </a>

                    <div class="lt-film-info">
                        <a href="/Pulse/film/<?= (int)$f['TMDB_ID'] ?>" class="lt-film-title">
                            <?= htmlspecialchars($f['Title']) ?>
                        </a>
                        <?php if ($anno): ?>
                            <span class="lt-film-year"><?= $anno ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_own): ?>
                    <button class="lt-remove-btn"
                            onclick="removeFilm(<?= (int)$f['TMDB_ID'] ?>, this)"
                            title="Rimuovi dalla lista">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

    </main>
</div>

<!-- ── MODAL MODIFICA ──────────────────────────────────────────────── -->
<?php if ($is_own): ?>
<div id="lt-edit-modal" class="lt-modal-overlay" onclick="closeEditModal(event)">
    <div class="lt-modal-box">
        <button class="lt-modal-close" onclick="closeEditModal(null)">
            <i class="bi bi-x-lg"></i>
        </button>
        <h2 class="lt-modal-title">
            <i class="bi bi-pencil" style="color:var(--accent)"></i>
            Modifica lista
        </h2>
        <div class="lt-form-group">
            <label class="lt-form-label">Titolo *</label>
            <input type="text" id="lt-edit-titolo" class="lt-form-input" maxlength="255"
                   value="<?= htmlspecialchars($lista['Titolo']) ?>">
        </div>
        <div class="lt-form-group">
            <label class="lt-form-label">Descrizione</label>
            <textarea id="lt-edit-desc" class="lt-form-textarea" rows="3" maxlength="255"><?= htmlspecialchars($lista['Descrizione'] ?? '') ?></textarea>
        </div>
        <div class="lt-modal-footer">
            <button class="lt-btn-ghost" onclick="closeEditModal(null)">Annulla</button>
            <button class="lt-btn-accent" onclick="submitEdit()">
                <i class="bi bi-check-lg"></i> Salva
            </button>
        </div>
    </div>
</div>

<!-- ── MODAL CONFERMA ELIMINA ── -->
<div id="lt-delete-modal" class="lt-modal-overlay" onclick="closeDeleteModal(event)">
    <div class="lt-modal-box lt-modal-box--sm">
        <h2 class="lt-modal-title" style="color:var(--danger)">
            <i class="bi bi-exclamation-triangle"></i>
            Elimina lista
        </h2>
        <p style="color:var(--muted);margin-bottom:24px;font-size:14px;line-height:1.6">
            Sei sicuro di voler eliminare la lista
            <strong style="color:var(--text)">"<?= htmlspecialchars($lista['Titolo']) ?>"</strong>?
            Questa azione è irreversibile.
        </p>
        <div class="lt-modal-footer">
            <button class="lt-btn-ghost" onclick="closeDeleteModal(null)">Annulla</button>
            <button class="lt-btn-danger-solid" onclick="submitDelete()">
                <i class="bi bi-trash"></i> Sì, elimina
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── TOAST ── -->
<div id="lt-toast" class="lt-toast"></div>

<!-- SortableJS via CDN -->
<?php if ($is_own): ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<?php endif; ?>

<script>
const BACKEND   = '/Pulse/backend/GestioneListe.php';
const LISTA_ID  = <?= $lista_id ?>;
const IS_OWN    = <?= $is_own ? 'true' : 'false' ?>;

// ── Toast ─────────────────────────────────────
function showToast(msg, type = 'ok') {
    const t = document.getElementById('lt-toast');
    t.textContent = msg;
    t.className = 'lt-toast show ' + (type === 'error' ? 'error' : '');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.className = 'lt-toast', 3200);
}

// ── Sortable drag & drop ──────────────────────
<?php if ($is_own && !empty($films)): ?>
const sortEl = document.getElementById('lt-films-sortable');
if (sortEl) {
    Sortable.create(sortEl, {
        handle: '.lt-drag-handle',
        animation: 200,
        ghostClass: 'lt-ghost',
        onEnd: async () => {
            const ordine = [...sortEl.querySelectorAll('.sortable-item')].map(
                el => parseInt(el.dataset.tmdb)
            );
            // Aggiorna numeri visibili
            sortEl.querySelectorAll('.lt-film-num').forEach((el, i) => el.textContent = i + 1);

            try {
                const res = await fetch(BACKEND, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'riordina', lista_id: LISTA_ID, ordine })
                });
                const json = await res.json();
                if (!json.ok) showToast('Errore nel salvataggio ordine', 'error');
            } catch { showToast('Errore di rete', 'error'); }
        }
    });
}
<?php endif; ?>

// ── Ricerca film ──────────────────────────────
<?php if ($is_own): ?>
let searchTimer;
const searchInput   = document.getElementById('lt-film-search');
const searchResults = document.getElementById('lt-search-results');
const loader        = document.getElementById('lt-loader');

searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const q = searchInput.value.trim();
    if (q.length < 2) { searchResults.innerHTML = ''; return; }
    loader.style.display = 'flex';
    searchTimer = setTimeout(() => doSearch(q), 400);
});

async function doSearch(q) {
    try {
        const res  = await fetch(BACKEND, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cerca_film', q })
        });
        const json = await res.json();
        loader.style.display = 'none';
        if (!json.ok || !json.films.length) {
            searchResults.innerHTML = '<div class="lt-no-results">Nessun risultato</div>';
            return;
        }
        searchResults.innerHTML = json.films.map(f => {
            const poster = f.poster_path
                ? `https://image.tmdb.org/t/p/w92${f.poster_path}`
                : '/Pulse/IMG/default_list.jpg';
            return `<div class="lt-result-item" onclick="addFilm(${f.tmdb_id}, this)">
                <img src="${poster}" alt="" class="lt-result-poster">
                <div class="lt-result-info">
                    <span class="lt-result-title">${escHtml(f.title)}</span>
                    <span class="lt-result-year">${f.year}</span>
                </div>
                <button class="lt-result-add"><i class="bi bi-plus-lg"></i></button>
            </div>`;
        }).join('');
    } catch {
        loader.style.display = 'none';
        searchResults.innerHTML = '<div class="lt-no-results">Errore di ricerca</div>';
    }
}

async function addFilm(tmdb_id, el) {
    el.classList.add('adding');
    try {
        const res  = await fetch(BACKEND, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'aggiungi_film', lista_id: LISTA_ID, tmdb_id })
        });
        const json = await res.json();
        if (!json.ok) { showToast(json.error ?? 'Errore', 'error'); el.classList.remove('adding'); return; }

        // Aggiunge la riga visivamente
        const f = json.film;
        const poster = f.Poster_Path
            ? `https://image.tmdb.org/t/p/w185${f.Poster_Path}`
            : null;
        const anno = f.Release_Date ? f.Release_Date.substring(0,4) : '';
        const list = document.getElementById('lt-films-sortable');

        // Crea l'elemento della lista se non esiste ancora (lista vuota)
        let sortable = list;
        if (!sortable) {
            const section = document.querySelector('.lt-films-section');
            section.innerHTML = '<div class="lt-films-list" id="lt-films-sortable"></div>';
            sortable = document.getElementById('lt-films-sortable');
            // Reinizializza sortable
            Sortable.create(sortable, { handle: '.lt-drag-handle', animation: 200,
                ghostClass: 'lt-ghost',
                onEnd: async () => {
                    const ordine = [...sortable.querySelectorAll('.sortable-item')].map(e => parseInt(e.dataset.tmdb));
                    sortable.querySelectorAll('.lt-film-num').forEach((e, i) => e.textContent = i + 1);
                    await fetch(BACKEND, { method:'POST', headers:{'Content-Type':'application/json'},
                        body: JSON.stringify({ action:'riordina', lista_id:LISTA_ID, ordine }) });
                }
            });
        }

        const num = sortable.querySelectorAll('.sortable-item').length + 1;
        const row = document.createElement('div');
        row.className = 'lt-film-row sortable-item';
        row.dataset.tmdb = f.TMDB_ID;
        row.innerHTML = `
            <div class="lt-drag-handle" title="Trascina per riordinare"><i class="bi bi-grip-vertical"></i></div>
            <span class="lt-film-num">${num}</span>
            <a href="/Pulse/film/${f.TMDB_ID}" class="lt-film-poster-wrap">
                ${poster ? `<img src="${poster}" alt="" class="lt-film-poster" loading="lazy">` : '<div class="lt-film-poster-empty"><i class="bi bi-film"></i></div>'}
            </a>
            <div class="lt-film-info">
                <a href="/Pulse/film/${f.TMDB_ID}" class="lt-film-title">${escHtml(f.Title)}</a>
                ${anno ? `<span class="lt-film-year">${anno}</span>` : ''}
            </div>
            <button class="lt-remove-btn" onclick="removeFilm(${f.TMDB_ID}, this)" title="Rimuovi">
                <i class="bi bi-x-lg"></i>
            </button>`;
        sortable.appendChild(row);
        row.style.animation = 'lt-slide-in .3s ease';

        el.classList.remove('adding');
        el.querySelector('.lt-result-add').innerHTML = '<i class="bi bi-check-lg"></i>';
        el.style.opacity = '.5';
        el.style.pointerEvents = 'none';
        showToast(`"${f.Title}" aggiunto alla lista`);

        // Aggiorna la cover header
        updateCovers();
        searchInput.value = '';
        setTimeout(() => { searchResults.innerHTML = ''; }, 800);
    } catch {
        el.classList.remove('adding');
        showToast('Errore di rete', 'error');
    }
}

async function removeFilm(tmdb_id, btn) {
    const row = btn.closest('.lt-film-row');
    const title = row.querySelector('.lt-film-title')?.textContent ?? 'film';
    if (!confirm(`Rimuovere "${title}" dalla lista?`)) return;

    try {
        const res  = await fetch(BACKEND, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'rimuovi_film', lista_id: LISTA_ID, tmdb_id })
        });
        const json = await res.json();
        if (!json.ok) { showToast(json.error ?? 'Errore', 'error'); return; }

        row.style.transition = 'opacity .25s, transform .25s';
        row.style.opacity = '0'; row.style.transform = 'translateX(30px)';
        setTimeout(() => {
            row.remove();
            // Rinumera
            document.querySelectorAll('.lt-film-num').forEach((el, i) => el.textContent = i + 1);
            updateCovers();
        }, 260);
        showToast('Film rimosso');
    } catch { showToast('Errore di rete', 'error'); }
}

function updateCovers() {
    // Aggiorna il contatore
    const cnt = document.querySelectorAll('.sortable-item').length;
    const metaSub = document.querySelector('.lt-detail-meta-sub');
    if (metaSub) metaSub.textContent = `Lista · ${cnt} film`;
}
<?php endif; ?>

// ── Modifica lista ────────────────────────────
<?php if ($is_own): ?>
function openEditModal() {
    document.getElementById('lt-edit-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeEditModal(e) {
    if (e && e.target !== document.getElementById('lt-edit-modal')) return;
    document.getElementById('lt-edit-modal').classList.remove('open');
    document.body.style.overflow = '';
}
async function submitEdit() {
    const titolo = document.getElementById('lt-edit-titolo').value.trim();
    const desc   = document.getElementById('lt-edit-desc').value.trim();
    if (!titolo) { showToast('Titolo obbligatorio', 'error'); return; }
    try {
        const res  = await fetch(BACKEND, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'modifica_lista', lista_id: LISTA_ID, titolo, descrizione: desc })
        });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error ?? 'Errore');

        document.getElementById('lt-title-display').textContent = titolo;
        const descEl = document.getElementById('lt-desc-display');
        if (descEl) descEl.textContent = desc || 'Aggiungi una descrizione…';
        closeEditModal(null);
        showToast('Lista aggiornata!');
    } catch (err) { showToast(err.message, 'error'); }
}

// ── Elimina lista ─────────────────────────────
function confirmDelete() {
    document.getElementById('lt-delete-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeDeleteModal(e) {
    if (e && e.target !== document.getElementById('lt-delete-modal')) return;
    document.getElementById('lt-delete-modal').classList.remove('open');
    document.body.style.overflow = '';
}
async function submitDelete() {
    try {
        const res  = await fetch(BACKEND, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'elimina_lista', lista_id: LISTA_ID })
        });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error ?? 'Errore');
        window.location.href = '/Pulse/liste';
    } catch (err) { showToast(err.message, 'error'); }
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeEditModal(null); closeDeleteModal(null); }
});
<?php endif; ?>

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>