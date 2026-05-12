<?php
// pages/UtentePubblico.php — incluso da index.php
// URI: /Pulse/utente/{username}
//
// LOGICA DI INDIRIZZAMENTO:
//   - Se l'username richiesto corrisponde all'utente loggato
//     → redirect a /Pulse/profilo  (la pagina "privata" con tutti i controlli)
//   - Altrimenti mostra il profilo pubblico dell'altro utente

require_once 'Database.php';

$pdo              = getConnection();
$username_target  = trim($sub ?? '');

if ($username_target === '') {
    echo '<div style="padding:60px;text-align:center;color:var(--muted)">Nessun utente specificato.</div>';
    return;
}

// ── Redirect se è il proprio profilo ─────────
if (isset($_SESSION['username']) &&
    mb_strtolower($username_target) === mb_strtolower($_SESSION['username'])) {
    header('Location: /Pulse/profilo');
    exit;
}

// ── Recupera utente ───────────────────────────
$stmt = $pdo->prepare("SELECT * FROM Utente WHERE Username = ? LIMIT 1");
$stmt->execute([$username_target]);
$utente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$utente) {
    echo '<div style="padding:60px;text-align:center;color:var(--muted)">Utente <strong>@'
        . htmlspecialchars($username_target) . '</strong> non trovato.</div>';
    return;
}

$uid   = $utente['ID'];
$my_id = (int)$_SESSION['user_id'];

// Helper slug (locale, in caso non sia già definito)
if (!function_exists('slugify_up')) {
    function slugify_up(string $s): string {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[^a-z0-9\s\-]/u', '', $s);
        $s = preg_replace('/[\s\-]+/', '-', $s);
        return trim($s, '-');
    }
}

// ── Statistiche ───────────────────────────────
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Visione WHERE IDUtente = ? AND Is_Watched = 1");
$stmt->execute([$uid]);
$count_visti = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM Segui WHERE IDSeguito = ?");
$stmt->execute([$uid]);
$count_follower = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM Segui WHERE IDSeguitore = ?");
$stmt->execute([$uid]);
$count_seguiti = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM Log WHERE IDUtente = ?");
$stmt->execute([$uid]);
$count_recensioni = (int)$stmt->fetchColumn();

// ── Controllo "sto seguendo?" ─────────────────
$stmt = $pdo->prepare("SELECT 1 FROM Segui WHERE IDSeguitore = ? AND IDSeguito = ? LIMIT 1");
$stmt->execute([$my_id, $uid]);
$sto_seguendo = (bool)$stmt->fetchColumn();

// ── PREFERITI dell'utente (max 5) ─────────────
$stmt = $pdo->prepare("
    SELECT F.ID, F.TMDB_ID, F.Title, F.Poster_Path, F.Release_Date
    FROM Visione V
    JOIN Film F ON V.IDFilm = F.ID
    WHERE V.IDUtente = ? AND V.Is_Favourite = 1
    ORDER BY F.Title ASC
    LIMIT 5
");
$stmt->execute([$uid]);
$preferiti_pub = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pref_slots_pub = array_pad($preferiti_pub, 5, null);

// ── Ultimi log ────────────────────────────────
$stmt = $pdo->prepare("
    SELECT L.ID, L.Voto, L.Recensione, L.Data, L.Data_Pubblicazione,
           F.Title, F.Poster_Path, F.Release_Date
    FROM Log L
    JOIN Film F ON L.IDFilm = F.ID
    WHERE L.IDUtente = ?
    ORDER BY L.Data_Pubblicazione DESC
    LIMIT 6
");
$stmt->execute([$uid]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Liste ─────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT L.IDLista, L.Titolo,
           COUNT(LF.IDFilm) AS TotaleFilm,
           (SELECT F.Poster_Path
            FROM Lista_Film LF2
            JOIN Film F ON LF2.IDFilm = F.ID
            WHERE LF2.IDLista = L.IDLista
            LIMIT 1) AS AnteprimaPoster
    FROM Lista L
    LEFT JOIN Lista_Film LF ON LF.IDLista = L.IDLista
    WHERE L.IDUtente = ?
    GROUP BY L.IDLista
    ORDER BY L.IDLista DESC
    LIMIT 4
");
$stmt->execute([$uid]);
$liste = $stmt->fetchAll(PDO::FETCH_ASSOC);

$avatar = $utente['Avatar_URL']
    ?? "https://ui-avatars.com/api/?name=" . urlencode($utente['Username']) . "&background=8b5cf6&color=fff&size=200";
?>

<div class="app utente-app">
    <?php include "aside.php"; ?>

    <main class="center" style="gap:0; padding:0 0 60px 0;">

        <!-- ── HEADER PROFILO PUBBLICO ── -->
        <section class="up-header">

            <div class="up-avatar-wrap">
                <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="up-avatar">
            </div>

            <div class="up-info">
                <div class="up-name-row">
                    <h1 class="up-username">@<?= htmlspecialchars($utente['Username']) ?></h1>

                    <button class="up-action-btn <?= $sto_seguendo ? 'following' : '' ?>"
                            id="followBtn"
                            data-id="<?= $uid ?>"
                            data-following="<?= $sto_seguendo ? '1' : '0' ?>">
                        <?php if ($sto_seguendo): ?>
                            <i class="bi bi-check2"></i> <span class="btn-label">Seguito</span>
                        <?php else: ?>
                            <i class="bi bi-person-plus"></i> <span class="btn-label">Segui</span>
                        <?php endif; ?>
                    </button>
                </div>

                <?php if (!empty($utente['Bio'])): ?>
                    <p class="up-bio"><?= htmlspecialchars($utente['Bio']) ?></p>
                <?php else: ?>
                    <p class="up-bio" style="font-style:italic;opacity:.6;">Nessuna biografia.</p>
                <?php endif; ?>

                <div class="up-stats">
                    <div class="up-stat"><span class="up-num" id="statFollower"><?= $count_follower ?></span><span class="up-lab">Follower</span></div>
                    <div class="up-stat"><span class="up-num"><?= $count_seguiti ?></span><span class="up-lab">Seguiti</span></div>
                    <div class="up-stat"><span class="up-num"><?= $count_visti ?></span><span class="up-lab">Film</span></div>
                    <div class="up-stat"><span class="up-num"><?= $count_recensioni ?></span><span class="up-lab">Recensioni</span></div>
                </div>
            </div>
        </section>

        <!-- ════════════════════════
             PREFERITI PUBBLICI
             ════════════════════════ -->
        <section class="up-favs">
            <h3 class="up-favs-title">Film Preferiti</h3>
            <div class="up-favs-grid">
                <?php foreach ($pref_slots_pub as $f): ?>
                    <?php if ($f === null): ?>
                        <!-- Slot vuoto: stesso stile usato quando un film non ha copertina -->
                        <div class="fav-slot-pub empty">
                            <div class="fav-noimg-pub">
                                <i class="bi bi-film"></i>
                            </div>
                        </div>
                    <?php else:
                        $poster = !empty($f['Poster_Path'])
                            ? "https://image.tmdb.org/t/p/w300" . $f['Poster_Path']
                            : null;
                    ?>
                        <a class="fav-slot-pub filled"
                           href="/Pulse/film/<?= $f['TMDB_ID'] ?>-<?= slugify_up($f['Title']) ?>"
                           title="<?= htmlspecialchars($f['Title']) ?>">
                            <?php if ($poster): ?>
                                <img src="<?= $poster ?>" alt="<?= htmlspecialchars($f['Title']) ?>">
                            <?php else: ?>
                                <div class="fav-noimg-pub">
                                    <span><?= htmlspecialchars($f['Title']) ?></span>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ── RECENSIONI RECENTI ── -->
        <section class="up-section">
            <div class="up-section-header">
                <h2 class="up-section-title">Recensioni Recenti</h2>
            </div>

            <?php if ($logs): ?>
                <div class="up-logs">
                    <?php foreach ($logs as $log):
                        if (empty($log['Poster_Path'])) continue;
                        $poster = "https://image.tmdb.org/t/p/w185" . $log['Poster_Path'];
                        $anno   = !empty($log['Release_Date']) ? substr($log['Release_Date'], 0, 4) : '';
                    ?>
                        <div class="up-log-card">
                            <img src="<?= $poster ?>" alt="Poster" class="up-log-poster">
                            <div class="up-log-info">
                                <h4><?= htmlspecialchars($log['Title']) ?>
                                    <span class="up-log-year"><?= $anno ?></span>
                                </h4>
                                <?php if ($log['Voto']): ?>
                                    <div class="up-log-stars">
                                        <?php for ($i=1; $i<=5; $i++) echo $i<=(float)$log['Voto'] ? '★' : '☆'; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($log['Recensione'])): ?>
                                    <p class="up-log-text">
                                        <?= htmlspecialchars(mb_substr($log['Recensione'], 0, 220)) ?>
                                        <?= mb_strlen($log['Recensione']) > 220 ? '…' : '' ?>
                                    </p>
                                <?php endif; ?>
                                <small class="up-log-date">
                                    <?= date('d M Y', strtotime($log['Data_Pubblicazione'])) ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="up-empty">@<?= htmlspecialchars($utente['Username']) ?> non ha ancora scritto recensioni.</p>
            <?php endif; ?>
        </section>

        <!-- ── LISTE ── -->
        <?php if ($liste): ?>
        <section class="up-section">
            <div class="up-section-header">
                <h2 class="up-section-title">Liste</h2>
            </div>
            <div class="up-liste-grid">
                <?php foreach ($liste as $lista):
                    if (empty($lista['AnteprimaPoster'])) continue;
                    $cover = "https://image.tmdb.org/t/p/w300" . $lista['AnteprimaPoster'];
                ?>
                    <a href="/Pulse/lista?id=<?= $lista['IDLista'] ?>" class="up-lista-card">
                        <div class="up-lista-stack">
                            <div class="up-lista-layer2"></div>
                            <div class="up-lista-layer"></div>
                            <img src="<?= $cover ?>" alt="Cover" class="up-lista-cover">
                        </div>
                        <span class="up-lista-titolo"><?= htmlspecialchars($lista['Titolo']) ?></span>
                        <span class="up-lista-meta"><?= $lista['TotaleFilm'] ?> film</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </main>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
function showToastUp(msg, type='success') {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.className = `toast ${type} show`;
    setTimeout(() => t.classList.remove('show'), 3000);
}

const followBtn = document.getElementById('followBtn');
if (followBtn) {
    followBtn.addEventListener('click', async function () {
        const userId    = this.dataset.id;
        const following = this.dataset.following === '1';
        const action    = following ? 'unfollow' : 'follow';

        // Disabilita brevemente per evitare doppi click
        this.disabled = true;

        // Ottimistico: aggiorna subito la UI
        const labelEl   = this.querySelector('.btn-label');
        const iconEl    = this.querySelector('i');
        const followCnt = document.getElementById('statFollower');
        const oldFollowers = parseInt(followCnt?.textContent ?? '0', 10);

        if (following) {
            this.dataset.following = '0';
            this.classList.remove('following');
            if (iconEl)  iconEl.className = 'bi bi-person-plus';
            if (labelEl) labelEl.textContent = 'Segui';
            if (followCnt) followCnt.textContent = Math.max(0, oldFollowers - 1);
        } else {
            this.dataset.following = '1';
            this.classList.add('following');
            if (iconEl)  iconEl.className = 'bi bi-check2';
            if (labelEl) labelEl.textContent = 'Seguito';
            if (followCnt) followCnt.textContent = oldFollowers + 1;
        }

        try {
            const res = await fetch('/Pulse/backend/GestioneUtenti.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, user_id: parseInt(userId, 10) })
            });
            const json = await res.json();

            if (!json.ok) {
                // Rollback in caso di errore
                this.dataset.following = following ? '1' : '0';
                this.classList.toggle('following', following);
                if (iconEl)  iconEl.className = following ? 'bi bi-check2' : 'bi bi-person-plus';
                if (labelEl) labelEl.textContent = following ? 'Seguito' : 'Segui';
                if (followCnt) followCnt.textContent = oldFollowers;
                showToastUp('Errore: ' + (json.error ?? 'sconosciuto'), 'error');
            }
        } catch (err) {
            // Rollback in caso di errore di rete
            this.dataset.following = following ? '1' : '0';
            this.classList.toggle('following', following);
            if (iconEl)  iconEl.className = following ? 'bi bi-check2' : 'bi bi-person-plus';
            if (labelEl) labelEl.textContent = following ? 'Seguito' : 'Segui';
            if (followCnt) followCnt.textContent = oldFollowers;
            showToastUp('Errore di connessione', 'error');
        } finally {
            this.disabled = false;
        }
    });
}
</script>