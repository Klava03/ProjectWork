<?php
// pages/ModificaProfilo.php — modifica profilo utente

require_once 'Database.php';
$pdo   = getConnection();
$my_id = (int)$_SESSION['user_id'];

// ── Dati utente ──────────────────────────────
$stmt = $pdo->prepare("SELECT Username, Bio, Avatar_URL FROM Utente WHERE ID = ?");
$stmt->execute([$my_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ── Preferiti correnti (max 5) ───────────────
$stmt = $pdo->prepare("
    SELECT F.ID, F.TMDB_ID, F.Title, F.Poster_Path, F.Release_Date
    FROM Visione V
    JOIN Film F ON V.IDFilm = F.ID
    WHERE V.IDUtente = ? AND V.Is_Favourite = 1
    ORDER BY F.Title ASC
    LIMIT 5
");
$stmt->execute([$my_id]);
$preferiti  = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pref_slots = array_pad($preferiti, 5, null);

// Avatar: se è un nome file locale → costruisco il path web
// Se è già un URL completo (http...) → usalo direttamente
$avatarVal = $user['Avatar_URL'] ?? null;
if ($avatarVal && !str_starts_with($avatarVal, 'http')) {
    $avatar = '/Pulse/IMG/avatars/' . $avatarVal;
} else {
    $avatar = $avatarVal ?? "https://ui-avatars.com/api/?name=" . urlencode($user['Username']) . "&background=8b5cf6&color=fff&size=200";
}

// Helper
function slugify(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9\s\-]/u', '', $s);
    $s = preg_replace('/[\s\-]+/', '-', $s);
    return trim($s, '-');
}
?>

<div class="app mp-app">
    <?php require 'aside.php'; ?>

    <main class="center mp-center">

        <header class="mp-page-header">
            <a href="/Pulse/profilo" class="mp-back-btn">
                <i class="bi bi-arrow-left"></i> Profilo
            </a>
            <h1>Modifica profilo</h1>
        </header>

        <!-- ══════════════════════════════════════
             SEZIONE: AVATAR
             ══════════════════════════════════════ -->
        <section class="mp-card">
            <h2 class="mp-card-title">
                <i class="bi bi-person-circle"></i>
                Immagine profilo
            </h2>

            <div class="mp-avatar-row">
                <div class="mp-avatar-wrap">
                    <img src="<?= htmlspecialchars($avatar) ?>"
                         alt="Avatar"
                         class="mp-avatar"
                         id="avatarPreview">
                    <label for="avatarInput" class="mp-avatar-overlay" title="Cambia foto">
                        <i class="bi bi-camera-fill"></i>
                    </label>
                </div>

                <div class="mp-avatar-info">
                    <p class="mp-label">Foto profilo</p>
                    <p class="mp-hint">JPG, PNG, WEBP o GIF · max 5 MB</p>
                    <div class="mp-avatar-actions">
                        <label for="avatarInput" class="mp-btn mp-btn-outline">
                            <i class="bi bi-upload"></i>
                            Scegli file
                        </label>
                        <button class="mp-btn mp-btn-primary" id="saveAvatarBtn" disabled>
                            <i class="bi bi-check2"></i>
                            Salva foto
                        </button>
                    </div>
                    <p class="mp-avatar-filename" id="avatarFilename">Nessun file selezionato</p>
                </div>
            </div>

            <!-- Input file nascosto -->
            <input type="file"
                   id="avatarInput"
                   name="avatar"
                   accept="image/jpeg,image/png,image/webp,image/gif"
                   style="display:none">
        </section>

        <!-- ══════════════════════════════════════
             SEZIONE: USERNAME
             ══════════════════════════════════════ -->
        <section class="mp-card">
            <h2 class="mp-card-title">
                <i class="bi bi-at"></i>
                Username
            </h2>

            <div class="mp-field-row">
                <div class="mp-field">
                    <label class="mp-label" for="usernameInput">Username</label>
                    <div class="mp-input-wrap">
                        <span class="mp-input-prefix">@</span>
                        <input type="text"
                               id="usernameInput"
                               class="mp-input"
                               value="<?= htmlspecialchars($user['Username']) ?>"
                               maxlength="50"
                               placeholder="il_tuo_username">
                    </div>
                    <p class="mp-hint">Lettere, numeri, _ . − · 3–50 caratteri</p>
                </div>
                <button class="mp-btn mp-btn-primary" id="saveUsernameBtn">
                    <i class="bi bi-check2"></i>
                    Salva
                </button>
            </div>
        </section>

        <!-- ══════════════════════════════════════
             SEZIONE: BIO
             ══════════════════════════════════════ -->
        <section class="mp-card">
            <h2 class="mp-card-title">
                <i class="bi bi-pencil-square"></i>
                Bio
            </h2>

            <div class="mp-field">
                <label class="mp-label" for="bioInput">Descrizione breve</label>
                <textarea id="bioInput"
                          class="mp-textarea"
                          maxlength="255"
                          placeholder="Racconta qualcosa di te..."><?= htmlspecialchars($user['Bio'] ?? '') ?></textarea>
                <div class="mp-bio-footer">
                    <span class="mp-char-count"><span id="bioCount"><?= strlen($user['Bio'] ?? '') ?></span>/255</span>
                    <button class="mp-btn mp-btn-primary" id="saveBioBtn">
                        <i class="bi bi-check2"></i>
                        Salva bio
                    </button>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════
             SEZIONE: FILM PREFERITI
             ══════════════════════════════════════ -->
        <section class="mp-card">
            <h2 class="mp-card-title">
                <i class="bi bi-heart-fill"></i>
                Film preferiti
                <span class="mp-card-sub">Scegli i tuoi 5 film del cuore</span>
            </h2>

            <div class="mp-favs-grid">
                <?php foreach ($pref_slots as $i => $f): ?>
                    <?php if ($f): ?>
                        <!-- Slot pieno -->
                        <?php
                            $poster = $f['Poster_Path']
                                ? "https://image.tmdb.org/t/p/w300" . $f['Poster_Path']
                                : null;
                        ?>
                        <div class="mp-fav-slot filled" data-tmdb="<?= $f['TMDB_ID'] ?>">
                            <?php if ($poster): ?>
                                <img src="<?= $poster ?>" alt="<?= htmlspecialchars($f['Title']) ?>">
                            <?php else: ?>
                                <div class="mp-fav-noimg"><?= htmlspecialchars($f['Title']) ?></div>
                            <?php endif; ?>
                            <div class="mp-fav-overlay">
                                <span class="mp-fav-title"><?= htmlspecialchars($f['Title']) ?></span>
                                <button class="mp-fav-remove"
                                        data-tmdb="<?= $f['TMDB_ID'] ?>"
                                        title="Rimuovi dai preferiti">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Slot vuoto -->
                        <div class="mp-fav-slot empty" data-slot="<?= $i ?>">
                            <div class="mp-fav-plus">
                                <i class="bi bi-plus-lg"></i>
                            </div>
                            <span class="mp-fav-empty-label">Aggiungi</span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

    </main>
</div>

<!-- ══════════════════════════════════════════
     MODALE: CERCA FILM DA AGGIUNGERE AI PREFERITI
     ══════════════════════════════════════════ -->
<div class="mp-modal-backdrop" id="searchModal" hidden>
    <div class="mp-modal">
        <div class="mp-modal-header">
            <h3>Cerca un film</h3>
            <button class="mp-modal-close" id="closeModal">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="mp-modal-search">
            <i class="bi bi-search mp-modal-search-ico"></i>
            <input type="text"
                   id="filmSearchInput"
                   class="mp-modal-input"
                   placeholder="Titolo del film..."
                   autocomplete="off">
        </div>
        <div class="mp-modal-results" id="modalResults">
            <p class="mp-modal-hint">Inizia a digitare per cercare...</p>
        </div>
    </div>
</div>

<!-- Toast feedback -->
<div class="toast" id="toast"></div>

<script>
// ── TMDB API KEY (read-only, uguale a quella del backend) ──
const TMDB_KEY = "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJiY2U5ZWZmYjRjMDA5ZTNmNGZmZTM1N2QzOTlhMTk4NSIsIm5iZiI6MTc2OTc4NDc2MC43NzcsInN1YiI6IjY5N2NjNWI4MWVhOGNmMGRlZTI2YWVmNSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.cPNGTwdDeQD6TGYY2Shn-i3Y0i-OjMSey9KPpdrpBAE";

// ── Toast ──────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = 'toast show ' + type;
    setTimeout(() => t.className = 'toast', 3200);
}

// ════════════════════════════════════════════
//  AVATAR UPLOAD
// ════════════════════════════════════════════
const avatarInput   = document.getElementById('avatarInput');
const avatarPreview = document.getElementById('avatarPreview');
const avatarFilename = document.getElementById('avatarFilename');
const saveAvatarBtn  = document.getElementById('saveAvatarBtn');

avatarInput.addEventListener('change', () => {
    const file = avatarInput.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        showToast('Il file supera i 5 MB', 'error');
        avatarInput.value = '';
        return;
    }

    // Anteprima immediata
    const reader = new FileReader();
    reader.onload = e => {
        avatarPreview.src = e.target.result;
        document.querySelector('.me .avatar').src = e.target.result; // Aggiorna sidebar
    };
    reader.readAsDataURL(file);

    avatarFilename.textContent = file.name;
    saveAvatarBtn.disabled = false;
});

saveAvatarBtn.addEventListener('click', async () => {
    const file = avatarInput.files[0];
    if (!file) return;

    saveAvatarBtn.disabled = true;
    saveAvatarBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Caricamento...';

    const fd = new FormData();
    fd.append('action', 'update_avatar');
    fd.append('avatar', file);

    try {
        const res  = await fetch('/Pulse/backend/gestioneprofilo.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.ok) {
            showToast('Foto profilo aggiornata!');
            avatarFilename.textContent = 'Salvato ✓';
        } else {
            showToast(data.error || 'Errore caricamento', 'error');
        }
    } catch {
        showToast('Errore di rete', 'error');
    } finally {
        saveAvatarBtn.innerHTML = '<i class="bi bi-check2"></i> Salva foto';
        saveAvatarBtn.disabled = false;
    }
});

// ════════════════════════════════════════════
//  USERNAME SAVE
// ════════════════════════════════════════════
document.getElementById('saveUsernameBtn').addEventListener('click', async () => {
    const val = document.getElementById('usernameInput').value.trim();
    if (!val) return;

    const btn = document.getElementById('saveUsernameBtn');
    btn.disabled = true;

    try {
        const res  = await fetch('/Pulse/backend/gestioneprofilo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_username', username: val })
        });
        const data = await res.json();

        if (data.ok) {
            showToast('Username aggiornato!');
            // Aggiorna sidebar
            const sideStrong = document.querySelector('.me .meta strong');
            if (sideStrong) sideStrong.textContent = '@' + data.username;
        } else {
            showToast(data.error || 'Errore', 'error');
        }
    } catch {
        showToast('Errore di rete', 'error');
    } finally {
        btn.disabled = false;
    }
});

// ════════════════════════════════════════════
//  BIO SAVE
// ════════════════════════════════════════════
const bioInput  = document.getElementById('bioInput');
const bioCount  = document.getElementById('bioCount');

bioInput.addEventListener('input', () => {
    bioCount.textContent = bioInput.value.length;
});

document.getElementById('saveBioBtn').addEventListener('click', async () => {
    const val = bioInput.value.trim();
    const btn = document.getElementById('saveBioBtn');
    btn.disabled = true;

    try {
        const res  = await fetch('/Pulse/backend/gestioneprofilo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_bio', bio: val })
        });
        const data = await res.json();
        data.ok ? showToast('Bio aggiornata!') : showToast(data.error || 'Errore', 'error');
    } catch {
        showToast('Errore di rete', 'error');
    } finally {
        btn.disabled = false;
    }
});

// ════════════════════════════════════════════
//  PREFERITI — rimuovi
// ════════════════════════════════════════════
document.querySelectorAll('.mp-fav-remove').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const tmdb = btn.dataset.tmdb;
        if (!tmdb) return;

        btn.disabled = true;
        try {
            const res  = await fetch('/Pulse/backend/gestionepreferiti.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'rimuovi_preferito', tmdb_id: parseInt(tmdb) })
            });
            const data = await res.json();
            if (data.ok) {
                showToast('Film rimosso dai preferiti');
                // Ricarica la pagina per aggiornare gli slot
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(data.error || 'Errore', 'error');
                btn.disabled = false;
            }
        } catch {
            showToast('Errore di rete', 'error');
            btn.disabled = false;
        }
    });
});

// ════════════════════════════════════════════
//  MODALE CERCA FILM (per slot vuoti)
// ════════════════════════════════════════════
const modal        = document.getElementById('searchModal');
const closeModal   = document.getElementById('closeModal');
const filmInput    = document.getElementById('filmSearchInput');
const modalResults = document.getElementById('modalResults');
let searchTimeout  = null;

// Apri modale cliccando uno slot vuoto
document.querySelectorAll('.mp-fav-slot.empty').forEach(slot => {
    slot.addEventListener('click', () => {
        modal.hidden = false;
        filmInput.value = '';
        modalResults.innerHTML = '<p class="mp-modal-hint">Inizia a digitare per cercare...</p>';
        setTimeout(() => filmInput.focus(), 80);
    });
});

closeModal.addEventListener('click', () => { modal.hidden = true; });
modal.addEventListener('click', e => { if (e.target === modal) modal.hidden = true; });

// Escape per chiudere
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !modal.hidden) modal.hidden = true;
});

// Ricerca TMDB con debounce
filmInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    const q = filmInput.value.trim();
    if (q.length < 2) {
        modalResults.innerHTML = '<p class="mp-modal-hint">Scrivi almeno 2 caratteri...</p>';
        return;
    }
    modalResults.innerHTML = '<p class="mp-modal-hint">Ricerca in corso...</p>';
    searchTimeout = setTimeout(() => searchFilms(q), 380);
});

async function searchFilms(query) {
    try {
        const url = `https://api.themoviedb.org/3/search/movie?query=${encodeURIComponent(query)}&language=it-IT&page=1`;
        const res  = await fetch(url, {
            headers: { 'Authorization': 'Bearer ' + TMDB_KEY, 'accept': 'application/json' }
        });
        const data = await res.json();
        renderModalResults(data.results || []);
    } catch {
        modalResults.innerHTML = '<p class="mp-modal-hint" style="color:var(--danger)">Errore di ricerca</p>';
    }
}

function renderModalResults(films) {
    if (!films.length) {
        modalResults.innerHTML = '<p class="mp-modal-hint">Nessun film trovato</p>';
        return;
    }
    modalResults.innerHTML = films.slice(0, 8).map(f => {
        const poster = f.poster_path
            ? `https://image.tmdb.org/t/p/w92${f.poster_path}`
            : '/Pulse/IMG/default_list.jpg';
        const anno = f.release_date ? f.release_date.slice(0, 4) : '—';
        return `
            <div class="mp-modal-film" data-tmdb="${f.id}">
                <img src="${poster}" alt="" class="mp-modal-poster">
                <div class="mp-modal-film-info">
                    <span class="mp-modal-film-title">${escHtml(f.title || f.original_title)}</span>
                    <span class="mp-modal-film-year">${anno}</span>
                </div>
                <button class="mp-modal-add-btn" data-tmdb="${f.id}" title="Aggiungi ai preferiti">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
        `;
    }).join('');

    // Listener sui bottoni aggiungi
    modalResults.querySelectorAll('.mp-modal-add-btn').forEach(btn => {
        btn.addEventListener('click', () => addPreferito(parseInt(btn.dataset.tmdb), btn));
    });
}

async function addPreferito(tmdbId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    try {
        const res  = await fetch('/Pulse/backend/gestionepreferiti.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'aggiungi_preferito', tmdb_id: tmdbId })
        });
        const data = await res.json();
        if (data.ok) {
            showToast('Aggiunto ai preferiti!');
            modal.hidden = true;
            setTimeout(() => location.reload(), 700);
        } else {
            showToast(data.error || 'Errore', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-plus-lg"></i>';
        }
    } catch {
        showToast('Errore di rete', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-lg"></i>';
    }
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
</script>