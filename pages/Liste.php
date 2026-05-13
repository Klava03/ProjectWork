<?php
// pages/Liste.php — Lista delle liste, tab Mie / Condivise / Seguiti

require_once 'Database.php';
$pdo   = getConnection();
$my_id = (int)$_SESSION['user_id'];

$filtro = $_GET['filtro'] ?? 'mie';   // mie | condivise | seguiti

// ── Conteggio seguiti ─────────────────────────────────────────────
$stmtSeg = $pdo->prepare("SELECT COUNT(*) FROM Segui WHERE IDSeguitore = ?");
$stmtSeg->execute([$my_id]);
$cnt_seguiti = (int)$stmtSeg->fetchColumn();

// ── Conteggio liste condivise di cui sono membro ──────────────────
$stmtMem = $pdo->prepare("SELECT COUNT(*) FROM Lista_Membro WHERE IDUtente = ?");
$stmtMem->execute([$my_id]);
$cnt_condivise = (int)$stmtMem->fetchColumn();

// ── Carica liste ──────────────────────────────────────────────────
$liste = [];

if ($filtro === 'mie') {
    $stmt = $pdo->prepare("
        SELECT L.IDLista, L.Titolo, L.Descrizione, L.IDUtente,
               U.Username,
               (SELECT COUNT(*) FROM Lista_Film LF WHERE LF.IDLista = L.IDLista) AS TotaleFilm,
               (SELECT F.Poster_Path FROM Lista_Film LF2
                JOIN Film F ON LF2.IDFilm = F.ID
                WHERE LF2.IDLista = L.IDLista ORDER BY LF2.Posizione ASC LIMIT 1) AS Cover1,
               (SELECT F.Poster_Path FROM Lista_Film LF2
                JOIN Film F ON LF2.IDFilm = F.ID
                WHERE LF2.IDLista = L.IDLista ORDER BY LF2.Posizione ASC LIMIT 1 OFFSET 1) AS Cover2,
               (SELECT F.Poster_Path FROM Lista_Film LF2
                JOIN Film F ON LF2.IDFilm = F.ID
                WHERE LF2.IDLista = L.IDLista ORDER BY LF2.Posizione ASC LIMIT 1 OFFSET 2) AS Cover3
        FROM Lista L
        JOIN Utente U ON L.IDUtente = U.ID
        WHERE L.IDUtente = ?
        ORDER BY L.IDLista DESC
    ");
    $stmt->execute([$my_id]);

} elseif ($filtro === 'condivise') {
    $stmt = $pdo->prepare("
        SELECT L.IDLista, L.Titolo, L.Descrizione, L.IDUtente,
               U.Username, U.Avatar_URL,
               (SELECT COUNT(*) FROM Lista_Film LF WHERE LF.IDLista = L.IDLista) AS TotaleFilm,
               (SELECT F.Poster_Path FROM Lista_Film LF2
                JOIN Film F ON LF2.IDFilm = F.ID
                WHERE LF2.IDLista = L.IDLista ORDER BY LF2.Posizione ASC LIMIT 1) AS Cover1,
               (SELECT F.Poster_Path FROM Lista_Film LF2
                JOIN Film F ON LF2.IDFilm = F.ID
                WHERE LF2.IDLista = L.IDLista ORDER BY LF2.Posizione ASC LIMIT 1 OFFSET 1) AS Cover2,
               (SELECT F.Poster_Path FROM Lista_Film LF2
                JOIN Film F ON LF2.IDFilm = F.ID
                WHERE LF2.IDLista = L.IDLista ORDER BY LF2.Posizione ASC LIMIT 1 OFFSET 2) AS Cover3
        FROM Lista_Membro LM
        JOIN Lista L ON LM.IDLista = L.IDLista
        JOIN Utente U ON L.IDUtente = U.ID
        WHERE LM.IDUtente = ?
        ORDER BY L.IDLista DESC
        LIMIT 80
    ");
    $stmt->execute([$my_id]);

} else {
    $stmt = $pdo->prepare("
        SELECT L.IDLista, L.Titolo, L.Descrizione, L.IDUtente,
               U.Username, U.Avatar_URL,
               (SELECT COUNT(*) FROM Lista_Film LF WHERE LF.IDLista = L.IDLista) AS TotaleFilm,
               (SELECT F.Poster_Path FROM Lista_Film LF2
                JOIN Film F ON LF2.IDFilm = F.ID
                WHERE LF2.IDLista = L.IDLista ORDER BY LF2.Posizione ASC LIMIT 1) AS Cover1,
               (SELECT F.Poster_Path FROM Lista_Film LF2
                JOIN Film F ON LF2.IDFilm = F.ID
                WHERE LF2.IDLista = L.IDLista ORDER BY LF2.Posizione ASC LIMIT 1 OFFSET 1) AS Cover2,
               (SELECT F.Poster_Path FROM Lista_Film LF2
                JOIN Film F ON LF2.IDFilm = F.ID
                WHERE LF2.IDLista = L.IDLista ORDER BY LF2.Posizione ASC LIMIT 1 OFFSET 2) AS Cover3
        FROM Lista L
        JOIN Utente U ON L.IDUtente = U.ID
        WHERE L.IDUtente IN (SELECT IDSeguito FROM Segui WHERE IDSeguitore = ?)
        ORDER BY L.IDLista DESC
        LIMIT 80
    ");
    $stmt->execute([$my_id]);
}
$liste = $stmt->fetchAll(PDO::FETCH_ASSOC);

function listaAvatarUrl(?string $val, string $username): string {
    if ($val && !str_starts_with($val, 'http')) return '/Pulse/IMG/avatars/' . $val;
    return $val ?? "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=8b5cf6&color=fff&size=80";
}
?>

<div class="app">
    <?php require 'aside.php'; ?>

    <main class="center lt-center">

        <!-- ── HEADER ── -->
        <header class="lt-header">
            <div>
                <h1 class="lt-title">
                    <i class="bi bi-collection-fill" style="color:var(--accent)"></i>
                    Liste
                </h1>
                <p class="lt-subtitle">
                    <?php if ($filtro === 'mie'): ?>
                        Le tue raccolte · <strong><?= count($liste) ?></strong>
                    <?php elseif ($filtro === 'condivise'): ?>
                        Liste condivise con te · <strong><?= count($liste) ?></strong>
                    <?php else: ?>
                        Liste di chi segui · <strong><?= count($liste) ?></strong>
                    <?php endif; ?>
                </p>
            </div>

            <div class="lt-header-right">
                <div class="lt-filter-tabs">
                    <a href="/Pulse/liste?filtro=mie"
                       class="lt-filter-tab <?= $filtro === 'mie' ? 'active' : '' ?>">
                        <i class="bi bi-person-fill"></i> Le mie
                    </a>
                    <a href="/Pulse/liste?filtro=condivise"
                       class="lt-filter-tab <?= $filtro === 'condivise' ? 'active' : '' ?>">
                        <i class="bi bi-people-fill"></i> Condivise
                        <?php if ($cnt_condivise > 0): ?>
                            <span class="lt-tab-count"><?= $cnt_condivise ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/Pulse/liste?filtro=seguiti"
                       class="lt-filter-tab <?= $filtro === 'seguiti' ? 'active' : '' ?>">
                        <i class="bi bi-eye-fill"></i> Seguiti
                    </a>
                </div>

                <?php if ($filtro === 'mie'): ?>
                <button class="lt-create-btn" onclick="openCreateModal()">
                    <i class="bi bi-plus-lg"></i> Nuova lista
                </button>
                <?php endif; ?>
            </div>
        </header>

        <!-- ── EMPTY STATES ── -->
        <?php if ($filtro === 'seguiti' && $cnt_seguiti === 0): ?>
        <div class="lt-empty">
            <i class="bi bi-people" style="font-size:48px;color:var(--muted)"></i>
            <p>Non stai seguendo nessuno.</p>
            <a href="/Pulse/cerca/utenti" class="lt-cta-btn">
                <i class="bi bi-search"></i> Cerca persone
            </a>
        </div>

        <?php elseif ($filtro === 'condivise' && $cnt_condivise === 0): ?>
        <div class="lt-empty">
            <i class="bi bi-people" style="font-size:48px;color:var(--muted)"></i>
            <p>Non sei ancora membro di nessuna lista condivisa.</p>
            <small style="color:var(--muted);margin-top:4px;display:block">Quando qualcuno ti invita in una sua lista, la trovi qui.</small>
        </div>

        <?php elseif (empty($liste)): ?>
        <div class="lt-empty">
            <i class="bi bi-collection" style="font-size:48px;color:var(--muted)"></i>
            <?php if ($filtro === 'mie'): ?>
                <p>Non hai ancora nessuna lista.</p>
                <button class="lt-cta-btn" onclick="openCreateModal()">
                    <i class="bi bi-plus-circle"></i> Crea la prima lista
                </button>
            <?php else: ?>
                <p>Le persone che segui non hanno ancora liste.</p>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- ── GRIGLIA LISTE ── -->
        <div class="lt-grid">
            <?php foreach ($liste as $l):
                $covers = array_filter([
                    $l['Cover1'] ? "https://image.tmdb.org/t/p/w200" . $l['Cover1'] : null,
                    $l['Cover2'] ? "https://image.tmdb.org/t/p/w200" . $l['Cover2'] : null,
                    $l['Cover3'] ? "https://image.tmdb.org/t/p/w200" . $l['Cover3'] : null,
                ]);
                $isOwn = (int)$l['IDUtente'] === $my_id;
            ?>
            <div class="lt-card" onclick="location.href='/Pulse/lista?id=<?= (int)$l['IDLista'] ?>'">

                <div class="lt-card-covers lt-covers-<?= count($covers) ?>">
                    <?php if (empty($covers)): ?>
                        <div class="lt-cover-placeholder">
                            <i class="bi bi-collection"></i>
                        </div>
                    <?php else: foreach ($covers as $c): ?>
                        <img src="<?= htmlspecialchars($c) ?>" alt="" class="lt-cover-img" loading="lazy">
                    <?php endforeach; endif; ?>
                </div>

                <div class="lt-card-body">
                    <?php if ($filtro !== 'mie'): ?>
                    <div class="lt-card-author">
                        <img src="<?= htmlspecialchars(listaAvatarUrl($l['Avatar_URL'] ?? null, $l['Username'])) ?>"
                             alt="" class="lt-author-avatar">
                        <span>@<?= htmlspecialchars($l['Username']) ?></span>
                    </div>
                    <?php endif; ?>

                    <h3 class="lt-card-title"><?= htmlspecialchars($l['Titolo']) ?></h3>
                    <?php if (!empty($l['Descrizione'])): ?>
                        <p class="lt-card-desc"><?= htmlspecialchars(mb_substr($l['Descrizione'], 0, 70)) ?><?= mb_strlen($l['Descrizione']) > 70 ? '…' : '' ?></p>
                    <?php endif; ?>
                    <div class="lt-card-meta">
                        <span><i class="bi bi-film"></i> <?= (int)$l['TotaleFilm'] ?> film</span>
                        <?php if ($filtro === 'condivise'): ?>
                            <span class="lt-card-shared"><i class="bi bi-people-fill"></i> Membro</span>
                        <?php elseif ($isOwn): ?>
                            <span class="lt-card-mine"><i class="bi bi-pencil"></i> Modifica</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </main>
</div>

<!-- ── MODAL CREA LISTA ── -->
<div id="lt-create-modal" class="lt-modal-overlay" onclick="closeCreateModal(event)">
    <div class="lt-modal-box">
        <button class="lt-modal-close" onclick="closeCreateModal(null)">
            <i class="bi bi-x-lg"></i>
        </button>
        <h2 class="lt-modal-title">
            <i class="bi bi-plus-circle" style="color:var(--accent)"></i>
            Nuova lista
        </h2>
        <div class="lt-form-group">
            <label class="lt-form-label">Titolo *</label>
            <input type="text" id="lt-new-titolo" class="lt-form-input"
                   placeholder="Es. Migliori film del 2024" maxlength="255">
        </div>
        <div class="lt-form-group">
            <label class="lt-form-label">Descrizione</label>
            <textarea id="lt-new-desc" class="lt-form-textarea"
                      placeholder="Una breve descrizione della tua lista…" maxlength="255" rows="3"></textarea>
        </div>
        <div class="lt-modal-footer">
            <button class="lt-btn-ghost" onclick="closeCreateModal(null)">Annulla</button>
            <button class="lt-btn-accent" id="lt-create-submit" onclick="submitCreate()">
                <i class="bi bi-plus-lg"></i> Crea lista
            </button>
        </div>
    </div>
</div>

<div id="lt-toast" class="lt-toast"></div>

<style>
.lt-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    background: var(--accent);
    color: #fff;
    border-radius: 9px;
    font-size: 10px;
    font-weight: 800;
    margin-left: 2px;
}
.lt-card-shared {
    font-size: 11px;
    color: #60a5fa;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
</style>

<script>
const BACKEND = '/Pulse/backend/GestioneListe.php';

function showToast(msg, type = 'ok') {
    const t = document.getElementById('lt-toast');
    t.textContent = msg;
    t.className = 'lt-toast show ' + (type === 'error' ? 'error' : '');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.className = 'lt-toast', 3000);
}

// ── Modale — usa classe "open" come definita nel CSS ──────────────
function openCreateModal() {
    document.getElementById('lt-create-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('lt-new-titolo').focus(), 60);
}

function closeCreateModal(e) {
    // Chiude solo se si clicca l'overlay (non il box interno)
    if (e && e.target !== document.getElementById('lt-create-modal')) return;
    document.getElementById('lt-create-modal').classList.remove('open');
    document.body.style.overflow = '';
}

async function submitCreate() {
    const titolo = document.getElementById('lt-new-titolo').value.trim();
    const desc   = document.getElementById('lt-new-desc').value.trim();
    if (!titolo) { showToast('Inserisci un titolo', 'error'); return; }

    const btn = document.getElementById('lt-create-submit');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Creazione…';

    try {
        const res  = await fetch(BACKEND, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'crea_lista', titolo, descrizione: desc })
        });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error ?? 'Errore');
        window.location.href = '/Pulse/lista?id=' + json.lista_id;
    } catch (err) {
        showToast(err.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-lg"></i> Crea lista';
    }
}

document.getElementById('lt-new-titolo')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') submitCreate();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeCreateModal(null);
});
</script>