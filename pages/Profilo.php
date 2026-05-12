<?php
// pages/Profilo.php — profilo privato (utente loggato)

require_once 'Database.php';
$pdo   = getConnection();
$my_id = (int)$_SESSION['user_id'];
$tab   = $_GET['tab'] ?? 'diario';   // diario | watched | liste | attivita

// ── Dati utente ───────────────────────────────
$stmt = $pdo->prepare("SELECT Username, Bio, Avatar_URL FROM Utente WHERE ID = ?");
$stmt->execute([$my_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ── Stats ─────────────────────────────────────
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Visione WHERE IDUtente = ? AND Is_Watched = 1");
$stmt->execute([$my_id]);
$cnt_visti = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM Log WHERE IDUtente = ?");
$stmt->execute([$my_id]);
$cnt_log = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM Segui WHERE IDSeguito = ?");
$stmt->execute([$my_id]);
$cnt_follower = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM Segui WHERE IDSeguitore = ?");
$stmt->execute([$my_id]);
$cnt_seguiti = (int)$stmt->fetchColumn();

// ── PREFERITI (max 5) ─────────────────────────
$stmt = $pdo->prepare("
    SELECT F.ID, F.TMDB_ID, F.Title, F.Poster_Path, F.Release_Date
    FROM Visione V
    JOIN Film F ON V.IDFilm = F.ID
    WHERE V.IDUtente = ? AND V.Is_Favourite = 1
    ORDER BY F.Title ASC
    LIMIT 5
");
$stmt->execute([$my_id]);
$preferiti = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pad a 5 slot (gli slot vuoti diventano null)
$pref_slots = array_pad($preferiti, 5, null);

// ── Dati tab DIARIO ───────────────────────────
$logs = [];
if ($tab === 'diario') {
    $stmt = $pdo->prepare("
        SELECT L.ID       AS log_id,
               L.Data     AS data_vis,
               L.Voto,
               L.Recensione,
               L.Data_Pubblicazione,
               F.ID       AS film_id,
               F.TMDB_ID  AS tmdb_id,
               F.Title,
               F.Poster_Path,
               F.Release_Date,
               V.Liked
        FROM Log L
        JOIN Film F ON L.IDFilm = F.ID
        LEFT JOIN Visione V ON V.IDUtente = L.IDUtente AND V.IDFilm = L.IDFilm
        WHERE L.IDUtente = ?
        ORDER BY L.Data DESC, L.Data_Pubblicazione DESC
    ");
    $stmt->execute([$my_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Dati tab WATCHED ──────────────────────────
$watched = [];
if ($tab === 'watched') {
    $stmt = $pdo->prepare("
        SELECT F.ID, F.TMDB_ID, F.Title, F.Poster_Path, F.Release_Date,
               V.Rating
        FROM Visione V
        JOIN Film F ON V.IDFilm = F.ID
        WHERE V.IDUtente = ? AND V.Is_Watched = 1
        ORDER BY F.Title ASC
    ");
    $stmt->execute([$my_id]);
    $watched = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$avatar = $user['Avatar_URL']
    ?? "https://ui-avatars.com/api/?name=" . urlencode($user['Username']) . "&background=8b5cf6&color=fff&size=200";

// ── Helper stelle PHP ─────────────────────────
function starsHTML(float $rating): string {
    $html = '<span class="diary-stars">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i)          $html .= '<span class="sd-star full">★</span>';
        elseif ($rating >= $i-.5)   $html .= '<span class="sd-star half">★</span>';
        else                        $html .= '<span class="sd-star">★</span>';
    }
    return $html . '</span>';
}

function slugify(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9\s\-]/u', '', $s);
    $s = preg_replace('/[\s\-]+/', '-', $s);
    return trim($s, '-');
}

// Giorni in italiano
$giorni = ['Dom','Lun','Mar','Mer','Gio','Ven','Sab'];
$mesi   = ['','Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'];

// Raggruppa log per mese
$logsByMonth = [];
foreach ($logs as $log) {
    $ts  = strtotime($log['data_vis'] ?? $log['Data_Pubblicazione']);
    $key = date('Y-m', $ts);
    $logsByMonth[$key][] = $log;
}
?>

<div class="app" style="grid-template-columns: var(--w-left) 1fr !important;">
    <?php include "aside.php"; ?>

    <main class="center" style="gap:0; padding-bottom:60px;">

        <!-- ── HEADER ── -->
        <section class="prof-header">
            <div class="prof-top">
                <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="prof-avatar">
                <div class="prof-text">
                    <h2>@<?= htmlspecialchars($user['Username']) ?></h2>
                    <?php if (!empty($user['Bio'])): ?>
                        <p class="prof-bio"><?= htmlspecialchars($user['Bio']) ?></p>
                    <?php endif; ?>
                    <a href="/Pulse/modifica_profilo" class="btn-edit-prof">
                        <i class="bi bi-pencil"></i> Modifica profilo
                    </a>
                </div>
            </div>
            <div class="prof-stats">
                <div class="prof-stat"><span class="prof-num"><?= $cnt_visti ?></span><span class="prof-lab">Film</span></div>
                <div class="prof-stat"><span class="prof-num"><?= $cnt_log ?></span><span class="prof-lab">Log</span></div>
                <div class="prof-stat"><span class="prof-num"><?= $cnt_follower ?></span><span class="prof-lab">Follower</span></div>
                <div class="prof-stat"><span class="prof-num"><?= $cnt_seguiti ?></span><span class="prof-lab">Seguiti</span></div>
            </div>
        </section>

        <!-- ════════════════════════
             SEZIONE: FILM PREFERITI
             ════════════════════════ -->
        <section class="prof-favs">
            <h3 class="prof-favs-title">Film Preferiti</h3>
            <div class="prof-favs-grid">
                <?php foreach ($pref_slots as $idx => $f): ?>
                    <?php if ($f === null): ?>
                        <!-- Slot vuoto: mostra il pulsante "+" -->
                        <div class="fav-slot empty"
                             onclick="openFavModal(<?= $idx ?>)"
                             title="Aggiungi film preferito">
                            <div class="fav-plus">
                                <i class="bi bi-plus-lg"></i>
                            </div>
                        </div>
                    <?php else:
                        $poster = !empty($f['Poster_Path'])
                            ? "https://image.tmdb.org/t/p/w300" . $f['Poster_Path']
                            : null;
                        $anno   = !empty($f['Release_Date']) ? substr($f['Release_Date'], 0, 4) : '';
                    ?>
                        <a class="fav-slot filled"
                           href="/Pulse/film/<?= $f['TMDB_ID'] ?>-<?= slugify($f['Title']) ?>"
                           title="<?= htmlspecialchars($f['Title']) ?>">
                            <?php if ($poster): ?>
                                <img src="<?= $poster ?>" alt="<?= htmlspecialchars($f['Title']) ?>">
                            <?php else: ?>
                                <div class="fav-noimg">
                                    <span><?= htmlspecialchars($f['Title']) ?></span>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ── TABS ── -->
        <nav class="prof-tabs">
            <a href="/Pulse/profilo?tab=diario"  class="prof-tab <?= $tab==='diario'  ? 'active':'' ?>">Diario</a>
            <a href="/Pulse/profilo?tab=watched" class="prof-tab <?= $tab==='watched' ? 'active':'' ?>">Film Guardati</a>
            <a href="#" class="prof-tab soon">Liste</a>
            <a href="#" class="prof-tab soon">Attività</a>
        </nav>

        <!-- ════════════════════════
             TAB: DIARIO
             ════════════════════════ -->
        <?php if ($tab === 'diario'): ?>
            <?php if (!$logs): ?>
                <p class="prof-empty">
                    Non hai ancora loggato nessun film.<br>
                    <a href="/Pulse/crea" style="color:var(--accent);font-weight:700;">Crea il tuo primo log →</a>
                </p>
            <?php else: ?>
                <div class="diary-list" style="padding-top:8px;">
                    <?php
                    $lastMonth = null;
                    foreach ($logs as $log):
                        $ts       = strtotime($log['data_vis'] ?? $log['Data_Pubblicazione']);
                        $monthKey = date('Y-m', $ts);
                        $meseNum  = (int)date('n', $ts);
                        $annoNum  = date('Y', $ts);
                        $giorno   = (int)date('j', $ts);
                        $dowNum   = (int)date('w', $ts);

                        $poster   = $log['Poster_Path']
                            ? "https://image.tmdb.org/t/p/w200" . $log['Poster_Path']
                            : "/Pulse/IMG/default_list.jpg";
                        $anno_film = !empty($log['Release_Date']) ? substr($log['Release_Date'],0,4) : '';
                        $voto      = $log['Voto'] ? (float)$log['Voto'] : 0;
                        $liked     = !empty($log['Liked']);
                        $hasReview = !empty($log['Recensione']);
                    ?>
                    <?php if ($monthKey !== $lastMonth):
                        $lastMonth = $monthKey; ?>
                        <div class="diary-month-sep">
                            <?= $mesi[$meseNum] ?> <?= $annoNum ?>
                        </div>
                    <?php endif; ?>

                    <div class="diary-entry" data-log-id="<?= $log['log_id'] ?>">
                        <div class="diary-date">
                            <span class="diary-day"><?= $giorno ?></span>
                            <span class="diary-weekday"><?= $giorni[$dowNum] ?></span>
                        </div>

                        <a href="/Pulse/film/<?= $log['tmdb_id'] ?>-<?= slugify($log['Title']) ?>">
                            <img src="<?= $poster ?>" class="diary-poster" alt="" loading="lazy">
                        </a>

                        <div class="diary-info">
                            <a href="/Pulse/film/<?= $log['tmdb_id'] ?>-<?= slugify($log['Title']) ?>" class="diary-title">
                                <?= htmlspecialchars($log['Title']) ?>
                                <span class="diary-year"><?= $anno_film ?></span>
                            </a>

                            <div style="display:flex;align-items:center;gap:4px;margin-bottom:4px;">
                                <?= $voto ? starsHTML($voto) : '' ?>
                                <?= $liked ? '<span class="diary-like">♥</span>' : '' ?>
                            </div>

                            <?php if ($hasReview): ?>
                                <button class="diary-review-toggle"
                                        onclick="toggleReview(this)"
                                        data-open="0">
                                    <i class="bi bi-chevron-down"></i> Recensione
                                </button>
                                <div class="diary-review-body">
                                    <?= nl2br(htmlspecialchars($log['Recensione'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="diary-actions">
                            <button class="diary-btn"
                                    onclick="openEditModal(<?= htmlspecialchars(json_encode([
                                        'log_id'    => (int)$log['log_id'],
                                        'data'      => $log['data_vis'],
                                        'voto'      => $voto,
                                        'recensione'=> $log['Recensione'] ?? '',
                                        'liked'     => $liked,
                                        'title'     => $log['Title'],
                                    ])) ?>)">
                                <i class="bi bi-pencil"></i> Modifica
                            </button>
                            <button class="diary-btn del"
                                    onclick="askDeleteLog(<?= $log['log_id'] ?>, this, '<?= htmlspecialchars(addslashes($log['Title']), ENT_QUOTES) ?>')">
                                <i class="bi bi-trash"></i> Elimina
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <!-- ════════════════════════
             TAB: FILM GUARDATI
             ════════════════════════ -->
        <?php elseif ($tab === 'watched'): ?>
            <?php if (!$watched): ?>
                <p class="prof-empty">Non hai ancora segnato nessun film come visto.</p>
            <?php else: ?>
                <div class="watched-grid" style="padding-top:24px;">
                    <?php foreach ($watched as $w):
                        if (empty($w['Poster_Path'])) continue;
                        $poster  = "https://image.tmdb.org/t/p/w300" . $w['Poster_Path'];
                        $anno_w  = !empty($w['Release_Date']) ? substr($w['Release_Date'],0,4) : '';
                    ?>
                        <a class="watched-card"
                           href="/Pulse/film/<?= $w['TMDB_ID'] ?>-<?= slugify($w['Title']) ?>">
                            <img src="<?= $poster ?>" alt="<?= htmlspecialchars($w['Title']) ?>">
                            <div class="watched-card-info">
                                <strong><?= htmlspecialchars($w['Title']) ?></strong>
                                <small>
                                    <?= $anno_w ?>
                                    <?= $w['Rating'] ? ' · ' . number_format((float)$w['Rating'],1) . '★' : '' ?>
                                </small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <!-- ── PLACEHOLDER FUTURE TABS ── -->
        <?php else: ?>
            <p class="prof-empty">Questa sezione è in arrivo. 🚀</p>
        <?php endif; ?>

    </main>
</div>

<!-- ════════════════════════════════════════════
     MODAL: MODIFICA LOG
     ════════════════════════════════════════════ -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box modal-edit">
        <button class="modal-close-x" onclick="closeModal()" aria-label="Chiudi">
            <i class="bi bi-x-lg"></i>
        </button>
        <h3 class="modal-title" id="modalFilmTitle">Modifica Log</h3>
        <input type="hidden" id="editLogId">

        <div class="log-form" style="gap:18px;">
            <div class="form-field">
                <label class="form-label">Data visione</label>
                <input type="date" id="editData" class="form-input" max="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-field">
                <label class="form-label">Voto</label>
                <div class="log-star-picker" id="editStarPicker">
                    <span class="log-star" data-val="1">★</span>
                    <span class="log-star" data-val="2">★</span>
                    <span class="log-star" data-val="3">★</span>
                    <span class="log-star" data-val="4">★</span>
                    <span class="log-star" data-val="5">★</span>
                    <span class="star-val-label" id="editStarLabel">Nessun voto</span>
                </div>
                <input type="hidden" id="editVoto">
            </div>

            <div class="form-field">
                <label class="form-label">Mi piace</label>
                <div class="like-toggle" id="editLikeToggle">
                    <span class="heart-icon">♥</span>
                    <span>Mi piace</span>
                </div>
                <input type="hidden" id="editLiked" value="0">
            </div>

            <div class="form-field">
                <label class="form-label">Recensione</label>
                <textarea id="editRecensione" class="form-textarea" rows="4"
                          placeholder="Scrivi la tua recensione…"></textarea>
            </div>

            <div class="modal-actions">
                <button class="btn-submit" id="btnSalvaEdit" onclick="saveEdit()">Salva modifiche</button>
                <button class="btn-cancel" onclick="closeModal()">Annulla</button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════
     MODAL: CONFERMA ELIMINAZIONE (custom)
     ════════════════════════════════════════════ -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box confirm-box">
        <div class="confirm-icon">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <h3 class="confirm-title" id="confirmTitle">Eliminare il log?</h3>
        <p class="confirm-text" id="confirmText">
            Questa azione non può essere annullata.
        </p>
        <div class="confirm-actions">
            <button class="btn-cancel" onclick="closeConfirm()">Annulla</button>
            <button class="btn-danger" id="confirmOkBtn">
                <i class="bi bi-trash"></i> Elimina
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════
     MODAL: AGGIUNGI FILM PREFERITO
     ════════════════════════════════════════════ -->
<div class="modal-overlay" id="favModal">
    <div class="modal-box modal-fav">
        <button class="modal-close-x" onclick="closeFavModal()" aria-label="Chiudi">
            <i class="bi bi-x-lg"></i>
        </button>
        <h3 class="modal-title">Aggiungi un film preferito</h3>
        <p class="confirm-text" style="margin-bottom:16px;">
            Cerca un film e selezionalo per aggiungerlo ai tuoi preferiti.
        </p>

        <div class="film-search-wrap">
            <input type="text"
                   id="favSearchInput"
                   class="film-search-input"
                   placeholder="Cerca un film…"
                   autocomplete="off">
            <i class="bi bi-search film-search-icon"></i>
        </div>
        <div class="search-loading" id="favSearchLoading">Ricerca in corso…</div>
        <div class="film-results" id="favResults" style="margin-top:14px;max-height:340px;overflow-y:auto;"></div>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
const BACKEND_LOG    = '/Pulse/backend/GestioneLog.php';
const BACKEND_PREF   = '/Pulse/backend/GestionePreferiti.php';

// ── Toast ─────────────────────────────────────
function showToast(msg, type='success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = `toast ${type} show`;
    setTimeout(() => t.classList.remove('show'), 3200);
}

// ── Toggle recensione ─────────────────────────
function toggleReview(btn) {
    const body = btn.nextElementSibling;
    const open = body.classList.toggle('open');
    btn.dataset.open = open ? '1' : '0';
    btn.querySelector('i').className = open ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
    btn.childNodes[1].textContent = open ? ' Chiudi' : ' Recensione';
}

// ════════════════════════════════════════════
//  MODAL CONFERMA CUSTOM (sostituisce confirm())
// ════════════════════════════════════════════
let confirmCallback = null;

function showConfirm(title, text, onOk) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmText').textContent  = text;
    confirmCallback = onOk;
    document.getElementById('confirmModal').classList.add('open');
}
function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
    confirmCallback = null;
}
document.getElementById('confirmOkBtn').addEventListener('click', () => {
    const cb = confirmCallback;
    closeConfirm();
    if (cb) cb();
});
document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
});

// ── Elimina log (usa modal custom) ────────────
function askDeleteLog(logId, btn, title) {
    showConfirm(
        'Eliminare il log?',
        `Stai per eliminare il log di "${title}". Questa azione non può essere annullata.`,
        () => doDeleteLog(logId, btn)
    );
}

async function doDeleteLog(logId, btn) {
    try {
        const res  = await fetch(BACKEND_LOG, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'elimina_log', log_id: logId })
        });
        const json = await res.json();
        if (json.ok) {
            const row = btn.closest('.diary-entry');
            row.style.opacity    = '0';
            row.style.transition = 'opacity .3s';
            setTimeout(() => row.remove(), 310);
            showToast('Log eliminato.', 'success');
        } else {
            showToast('Errore: ' + (json.error ?? 'sconosciuto'), 'error');
        }
    } catch (err) {
        showToast('Errore di connessione', 'error');
    }
}

// ════════════════════════════════════════════
//  STAR PICKER (riusabile)
// ════════════════════════════════════════════
class StarPicker {
    constructor(containerId, hiddenId, labelId) {
        this.container = document.getElementById(containerId);
        this.hidden    = document.getElementById(hiddenId);
        this.label     = document.getElementById(labelId);
        this.value     = 0;
        this.stars     = [...this.container.querySelectorAll('.log-star')];
        this.labels    = ['Nessun voto','½ stella','1 stella','1½ stelle','2 stelle',
                          '2½ stelle','3 stelle','3½ stelle','4 stelle','4½ stelle','5 stelle'];
        this.init();
    }
    getVal(s,e){ const r=s.getBoundingClientRect(); return (+s.dataset.val)-(e.clientX<r.left+r.width/2?.5:0); }
    repaint(val){
        this.stars.forEach(s=>{
            s.className='log-star'; s.style.cssText='';
            const sv=+s.dataset.val;
            if(val>=sv) s.classList.add('s-full');
            else if(val>=sv-.5) s.classList.add('s-half');
        });
    }
    hover(val){
        this.stars.forEach(s=>{
            s.className='log-star'; s.style.cssText='';
            const sv=+s.dataset.val;
            if(val>=sv) s.classList.add('s-hover-full');
            else if(val>=sv-.5) s.classList.add('s-hover-half');
        });
    }
    init(){
        this.stars.forEach(s=>{
            s.addEventListener('mousemove',e=>this.hover(this.getVal(s,e)));
            s.addEventListener('click',e=>{
                this.value=this.getVal(s,e);
                this.hidden.value=this.value;
                this.repaint(this.value);
                if(this.label) this.label.textContent=this.labels[Math.round(this.value*2)]??'';
            });
        });
        this.container.addEventListener('mouseleave',()=>this.repaint(this.value));
    }
    set(val){
        this.value=val; this.hidden.value=val; this.repaint(val);
        if(this.label) this.label.textContent=this.labels[Math.round(val*2)]??'';
    }
}

const editStar = new StarPicker('editStarPicker','editVoto','editStarLabel');

// ════════════════════════════════════════════
//  MODAL MODIFICA LOG
// ════════════════════════════════════════════
document.getElementById('editLikeToggle').addEventListener('click', function() {
    const liked = this.classList.toggle('liked');
    document.getElementById('editLiked').value = liked ? '1' : '0';
});

function openEditModal(log) {
    document.getElementById('editLogId').value          = log.log_id;
    document.getElementById('editData').value           = log.data;
    document.getElementById('editRecensione').value     = log.recensione;
    document.getElementById('modalFilmTitle').textContent = 'Modifica — ' + log.title;
    editStar.set(log.voto || 0);
    const lt = document.getElementById('editLikeToggle');
    document.getElementById('editLiked').value = log.liked ? '1' : '0';
    lt.classList.toggle('liked', !!log.liked);
    document.getElementById('editModal').classList.add('open');
}

function closeModal() {
    document.getElementById('editModal').classList.remove('open');
}

async function saveEdit() {
    const log_id     = +document.getElementById('editLogId').value;
    if (!log_id) {
        showToast('Errore: ID log mancante', 'error');
        return;
    }
    const data       = document.getElementById('editData').value;
    const voto       = parseFloat(document.getElementById('editVoto').value) || null;
    const recensione = document.getElementById('editRecensione').value.trim();
    const liked      = document.getElementById('editLiked').value === '1';

    const btn = document.getElementById('btnSalvaEdit');
    btn.disabled = true;

    try {
        const res  = await fetch(BACKEND_LOG, {
            method:'POST', headers:{'Content-Type':'application/json'},
            // ATTENZIONE: action = 'modifica_log' → fa UPDATE, non INSERT
            body: JSON.stringify({ action:'modifica_log', log_id, data, voto, recensione, liked })
        });
        const json = await res.json();
        btn.disabled = false;

        if (json.ok) {
            showToast('Log aggiornato!','success');
            closeModal();
            setTimeout(()=>location.reload(), 800);
        } else {
            showToast('Errore: '+(json.error??'sconosciuto'),'error');
        }
    } catch (err) {
        btn.disabled = false;
        showToast('Errore di connessione','error');
    }
}

// Chiudi modal modifica cliccando fuori
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// ════════════════════════════════════════════
//  MODAL AGGIUNGI PREFERITO (con ricerca film)
// ════════════════════════════════════════════
function openFavModal(slot) {
    document.getElementById('favSearchInput').value   = '';
    document.getElementById('favResults').innerHTML   = '';
    document.getElementById('favSearchLoading').style.display = 'none';
    document.getElementById('favModal').classList.add('open');
    setTimeout(() => document.getElementById('favSearchInput').focus(), 50);
}

function closeFavModal() {
    document.getElementById('favModal').classList.remove('open');
}

document.getElementById('favModal').addEventListener('click', function(e) {
    if (e.target === this) closeFavModal();
});

// Ricerca film (debounce 380ms) — riusa il pattern già usato in Crea
let favSearchTimer = null;
const favInput     = document.getElementById('favSearchInput');
const favResults   = document.getElementById('favResults');
const favLoading   = document.getElementById('favSearchLoading');

favInput.addEventListener('input', function() {
    clearTimeout(favSearchTimer);
    const q = this.value.trim();
    if (q.length < 2) { favResults.innerHTML = ''; return; }
    favLoading.style.display = 'block';
    favSearchTimer = setTimeout(() => searchFavFilm(q), 380);
});

async function searchFavFilm(q) {
    try {
        const res  = await fetch(BACKEND_PREF, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cerca_film', q })
        });
        const data = await res.json();
        favLoading.style.display = 'none';
        renderFavResults(data.films ?? []);
    } catch (e) {
        favLoading.style.display = 'none';
        showToast('Errore di rete', 'error');
    }
}

function renderFavResults(films) {
    favResults.innerHTML = '';
    if (!films.length) {
        favResults.innerHTML =
            '<p style="color:var(--muted);font-size:13px;padding:10px 0;">Nessun risultato.</p>';
        return;
    }
    films.forEach(f => {
        const el = document.createElement('div');
        el.className = 'film-result-item';
        const poster = f.poster_path
            ? `https://image.tmdb.org/t/p/w200${f.poster_path}`
            : '/Pulse/IMG/default_list.jpg';
        el.innerHTML = `
            <img src="${poster}" class="film-result-poster" alt="">
            <div class="film-result-info">
                <strong>${f.title}</strong>
                <small>${f.year ?? ''}</small>
            </div>
        `;
        el.addEventListener('click', () => addPreferito(f));
        favResults.appendChild(el);
    });
}

async function addPreferito(film) {
    try {
        const res = await fetch(BACKEND_PREF, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'aggiungi_preferito', tmdb_id: film.id })
        });
        const json = await res.json();
        if (json.ok) {
            showToast('✓ Preferito aggiunto', 'success');
            closeFavModal();
            setTimeout(() => location.reload(), 700);
        } else {
            showToast('Errore: ' + (json.error ?? 'sconosciuto'), 'error');
        }
    } catch (err) {
        showToast('Errore di connessione', 'error');
    }
}

// ESC chiude qualsiasi modal aperta
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    closeModal();
    closeConfirm();
    closeFavModal();
});
</script>