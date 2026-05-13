<?php
// pages/Community.php — Lista community + dettaglio singola community

require_once 'Database.php';
$pdo   = getConnection();
$my_id = (int)$_SESSION['user_id'];

global $sub;

/* Determina la modalità: lista | dettaglio
   - /Pulse/community            → lista
   - /Pulse/community?id=NN      → dettaglio
   - /Pulse/community/NN         → dettaglio (alias)
*/
$cid_qs   = (int)($_GET['id'] ?? 0);
$cid_path = (int)($sub ?? 0);
$cid      = $cid_qs ?: $cid_path;
$is_detail = $cid > 0;

/* ─── Bootstrap generi se vuoto ─── */
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM Genere")->fetchColumn();
if ($cnt === 0) {
    $seed = [
        28=>'Azione', 12=>'Avventura', 16=>'Animazione', 35=>'Commedia',
        80=>'Crime', 99=>'Documentario', 18=>'Dramma', 10751=>'Famiglia',
        14=>'Fantasy', 36=>'Storico', 27=>'Horror', 10402=>'Musica',
        9648=>'Mistero', 10749=>'Romantico', 878=>'Fantascienza',
        10770=>'Film TV', 53=>'Thriller', 10752=>'Guerra', 37=>'Western',
    ];
    $s = $pdo->prepare("INSERT IGNORE INTO Genere (ID, Name) VALUES (?, ?)");
    foreach ($seed as $gid => $name) $s->execute([$gid, $name]);
}

function commAvatarUrl(?string $val, string $username): string {
    if ($val && !str_starts_with($val, 'http')) return '/Pulse/IMG/avatars/' . $val;
    return $val ?? "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=8b5cf6&color=fff&size=80";
}
function commTimeAgo(string $ts): string {
    $d = time() - strtotime($ts);
    if ($d < 60)     return 'ora';
    if ($d < 3600)   return floor($d/60).'m';
    if ($d < 86400)  return floor($d/3600).'h';
    if ($d < 604800) return floor($d/86400).'g';
    return date('d M Y', strtotime($ts));
}

/* ══════════════════════════════════════════════
   MODALITÀ DETTAGLIO: carica community + post
   ══════════════════════════════════════════════ */
if ($is_detail) {
    $stmt = $pdo->prepare("
        SELECT C.ID, C.Nome, C.Descrizione, C.IDGenere, G.Name AS Genere,
               (SELECT COUNT(*) FROM Iscrizione_Community I WHERE I.IDCommunity = C.ID) AS membri,
               (SELECT 1 FROM Iscrizione_Community I WHERE I.IDCommunity = C.ID AND I.IDUtente = ?) AS sono_iscritto
        FROM Community C JOIN Genere G ON C.IDGenere = G.ID
        WHERE C.ID = ?
    ");
    $stmt->execute([$my_id, $cid]);
    $community = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$community) {
        echo '<div class="app"><aside class="left"></aside><main class="center">';
        require 'aside.php';
        echo '<div style="padding:60px;text-align:center;color:var(--muted)">Community non trovata.</div>';
        echo '</main></div>';
        return;
    }

    $sono_iscritto = (bool)$community['sono_iscritto'];

    /* Post della community */
    $stmt = $pdo->prepare("
        SELECT P.ID, P.Contenuto, P.Data_Pubblicazione,
               U.ID AS user_id, U.Username, U.Avatar_URL,
               (SELECT COUNT(*) FROM MiPiace  M WHERE M.IDPost = P.ID) AS likes,
               (SELECT COUNT(*) FROM Commento Co WHERE Co.IDPost = P.ID) AS commenti,
               (SELECT 1 FROM MiPiace M WHERE M.IDPost = P.ID AND M.IDUtente = ?) AS i_liked
        FROM Post P JOIN Utente U ON P.IDUtente = U.ID
        WHERE P.IDCommunity = ?
        ORDER BY P.Data_Pubblicazione DESC
        LIMIT 60
    ");
    $stmt->execute([$my_id, $cid]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
/* ══════════════════════════════════════════════
   MODALITÀ LISTA: tutte le community
   ══════════════════════════════════════════════ */
else {
    $filtro = $_GET['filtro'] ?? 'esplora';   // esplora | mie

    if ($filtro === 'mie') {
        $stmt = $pdo->prepare("
            SELECT C.ID, C.Nome, C.Descrizione, G.Name AS Genere,
                   (SELECT COUNT(*) FROM Iscrizione_Community I WHERE I.IDCommunity = C.ID) AS membri,
                   1 AS sono_iscritto
            FROM Iscrizione_Community IC
            JOIN Community C ON IC.IDCommunity = C.ID
            JOIN Genere   G ON C.IDGenere    = G.ID
            WHERE IC.IDUtente = ?
            ORDER BY C.Nome ASC
        ");
        $stmt->execute([$my_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT C.ID, C.Nome, C.Descrizione, G.Name AS Genere,
                   (SELECT COUNT(*) FROM Iscrizione_Community I WHERE I.IDCommunity = C.ID) AS membri,
                   (SELECT 1 FROM Iscrizione_Community I WHERE I.IDCommunity = C.ID AND I.IDUtente = ?) AS sono_iscritto
            FROM Community C JOIN Genere G ON C.IDGenere = G.ID
            ORDER BY membri DESC, C.Nome ASC
        ");
        $stmt->execute([$my_id]);
    }
    $communities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Conteggio "mie" per badge */
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Iscrizione_Community WHERE IDUtente = ?");
    $stmt->execute([$my_id]);
    $cnt_mie = (int)$stmt->fetchColumn();
}
?>

<div class="app">
    <?php require 'aside.php'; ?>

    <main class="center cm-center">

<?php if ($is_detail): /* ══════════════════ DETTAGLIO ══════════════════ */ ?>

    <div class="cm-back">
        <a href="/Pulse/community" class="cm-back-link">
            <i class="bi bi-arrow-left"></i> Tutte le community
        </a>
    </div>

    <!-- Header community -->
    <header class="cm-detail-header">
        <div class="cm-detail-icon">
            <?= mb_strtoupper(mb_substr($community['Nome'], 0, 1)) ?>
        </div>
        <div class="cm-detail-info">
            <span class="cm-detail-genre"><?= htmlspecialchars($community['Genere']) ?></span>
            <h1 class="cm-detail-name"><?= htmlspecialchars($community['Nome']) ?></h1>
            <?php if (!empty($community['Descrizione'])): ?>
                <p class="cm-detail-desc"><?= nl2br(htmlspecialchars($community['Descrizione'])) ?></p>
            <?php endif; ?>
            <div class="cm-detail-meta">
                <span><i class="bi bi-people-fill"></i> <strong><?= (int)$community['membri'] ?></strong> membri</span>
            </div>
        </div>
        <button id="btnIscrizione"
                class="cm-join-btn <?= $sono_iscritto ? 'joined' : '' ?>"
                data-id="<?= (int)$community['ID'] ?>">
            <?php if ($sono_iscritto): ?>
                <i class="bi bi-check-lg"></i> <span>Iscritto</span>
            <?php else: ?>
                <i class="bi bi-plus-lg"></i> <span>Unisciti</span>
            <?php endif; ?>
        </button>
    </header>

    <!-- Composer post (solo iscritti) -->
    <?php if ($sono_iscritto): ?>
    <section class="cm-post-composer">
        <textarea id="newPostText" class="cm-textarea"
                  placeholder="Cosa vuoi condividere con la community <?= htmlspecialchars($community['Nome']) ?>?"
                  rows="3" maxlength="5000"></textarea>
        <div class="cm-post-composer-footer">
            <span class="cm-counter"><span id="postCounter">0</span>/5000</span>
            <button id="btnPubblicaPost" class="cm-btn-accent" disabled>
                <i class="bi bi-send-fill"></i> Pubblica
            </button>
        </div>
    </section>
    <?php else: ?>
    <div class="cm-join-cta">
        <i class="bi bi-lock"></i>
        Iscriviti alla community per pubblicare e commentare i post.
    </div>
    <?php endif; ?>

    <!-- Feed post -->
    <section class="cm-posts-feed" id="postsFeed">
        <?php if (empty($posts)): ?>
            <div class="cm-empty-state">
                <i class="bi bi-card-text" style="font-size:48px;color:var(--muted)"></i>
                <p>Nessun post in questa community.</p>
                <?php if ($sono_iscritto): ?>
                    <small>Sii il primo a pubblicare!</small>
                <?php endif; ?>
            </div>
        <?php else: foreach ($posts as $p):
            $avatar = commAvatarUrl($p['Avatar_URL'], $p['Username']);
            $isMine = ((int)$p['user_id'] === $my_id);
        ?>
            <article class="cm-post" data-id="<?= (int)$p['ID'] ?>">
                <header class="cm-post-head">
                    <a href="/Pulse/utente/<?= rawurlencode($p['Username']) ?>" class="cm-post-author">
                        <img src="<?= htmlspecialchars($avatar) ?>" alt="" class="cm-post-avatar">
                        <div>
                            <strong>@<?= htmlspecialchars($p['Username']) ?></strong>
                            <span class="cm-post-time"><?= commTimeAgo($p['Data_Pubblicazione']) ?></span>
                        </div>
                    </a>
                    <?php if ($isMine): ?>
                        <button class="cm-post-del" data-id="<?= (int)$p['ID'] ?>"
                                title="Elimina post">
                            <i class="bi bi-three-dots"></i>
                        </button>
                    <?php endif; ?>
                </header>

                <div class="cm-post-content"><?= nl2br(htmlspecialchars($p['Contenuto'])) ?></div>

                <div class="cm-post-actions">
                    <button class="cm-post-like <?= $p['i_liked'] ? 'liked' : '' ?>"
                            data-id="<?= (int)$p['ID'] ?>">
                        <i class="bi <?= $p['i_liked'] ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                        <span class="cm-post-like-count"><?= (int)$p['likes'] ?></span>
                    </button>
                    <button class="cm-post-comm-toggle" data-id="<?= (int)$p['ID'] ?>">
                        <i class="bi bi-chat"></i>
                        <span data-comm-badge="post-<?= (int)$p['ID'] ?>"><?= (int)$p['commenti'] ?></span>
                        commenti
                    </button>
                </div>

                <!-- Sezione commenti (caricata su richiesta) -->
                <div class="cm-post-commenti" style="display:none"></div>
            </article>
        <?php endforeach; endif; ?>
    </section>

<?php else: /* ══════════════════ LISTA ══════════════════ */ ?>

    <header class="cm-header">
        <div>
            <h1 class="cm-title">
                <i class="bi bi-people-fill" style="color:var(--accent)"></i>
                Community
            </h1>
            <p class="cm-subtitle">Unisciti alla discussione sui generi che ami</p>
        </div>

        <div class="cm-header-actions">
            <div class="cm-filter-tabs">
                <a href="/Pulse/community?filtro=esplora"
                   class="cm-filter-tab <?= $filtro === 'esplora' ? 'active' : '' ?>">
                    <i class="bi bi-compass"></i> Esplora
                </a>
                <a href="/Pulse/community?filtro=mie"
                   class="cm-filter-tab <?= $filtro === 'mie' ? 'active' : '' ?>">
                    <i class="bi bi-bookmark-heart"></i> Le mie
                    <?php if ($cnt_mie > 0): ?>
                        <span class="cm-tab-count"><?= $cnt_mie ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <button class="cm-create-btn" onclick="openCreateModal()">
                <i class="bi bi-plus-lg"></i> Nuova
            </button>
        </div>
    </header>

    <?php if (empty($communities)): ?>
        <div class="cm-empty-state">
            <i class="bi bi-people" style="font-size:48px;color:var(--muted)"></i>
            <?php if ($filtro === 'mie'): ?>
                <p>Non sei ancora iscritto a nessuna community.</p>
                <a href="/Pulse/community?filtro=esplora" class="cm-cta-link">
                    <i class="bi bi-compass"></i> Esplora le community
                </a>
            <?php else: ?>
                <p>Nessuna community esiste ancora.</p>
                <button class="cm-cta-link" onclick="openCreateModal()">
                    <i class="bi bi-plus-circle"></i> Crea la prima community
                </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="cm-grid">
            <?php foreach ($communities as $c): ?>
                <a href="/Pulse/community?id=<?= (int)$c['ID'] ?>" class="cm-card">
                    <div class="cm-card-icon"><?= mb_strtoupper(mb_substr($c['Nome'], 0, 1)) ?></div>
                    <div class="cm-card-body">
                        <div class="cm-card-head">
                            <span class="cm-card-name"><?= htmlspecialchars($c['Nome']) ?></span>
                            <?php if ($c['sono_iscritto']): ?>
                                <span class="cm-card-joined-pill"><i class="bi bi-check-lg"></i></span>
                            <?php endif; ?>
                        </div>
                        <span class="cm-card-genre"><?= htmlspecialchars($c['Genere']) ?></span>
                        <?php if (!empty($c['Descrizione'])): ?>
                            <p class="cm-card-desc"><?= htmlspecialchars(mb_strimwidth($c['Descrizione'], 0, 110, '…')) ?></p>
                        <?php endif; ?>
                        <div class="cm-card-meta">
                            <i class="bi bi-people-fill"></i> <?= (int)$c['membri'] ?> membri
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Modal: crea community -->
    <div class="cm-modal-overlay" id="createModal" onclick="closeCreateModal(event)">
        <div class="cm-modal" onclick="event.stopPropagation()">
            <header class="cm-modal-head">
                <h2><i class="bi bi-plus-circle"></i> Nuova community</h2>
                <button class="cm-modal-close" onclick="closeCreateModal()">×</button>
            </header>
            <div class="cm-modal-body">
                <p class="cm-modal-hint">
                    Ogni community è legata a un genere cinematografico.
                    Scegli un genere ancora libero.
                </p>

                <div class="cm-form-group">
                    <label class="cm-label">Genere</label>
                    <select id="newGenereSelect" class="cm-select">
                        <option value="">Caricamento generi…</option>
                    </select>
                </div>

                <div class="cm-form-group">
                    <label class="cm-label">Nome community</label>
                    <input type="text" id="newNome" class="cm-input"
                           placeholder="es. Cinefili dell'Horror" maxlength="100">
                </div>

                <div class="cm-form-group">
                    <label class="cm-label">Descrizione <span class="cm-label-opt">(opzionale)</span></label>
                    <textarea id="newDesc" class="cm-textarea"
                              placeholder="A chi è dedicata? Cosa la rende speciale?"
                              rows="3" maxlength="500"></textarea>
                </div>
            </div>
            <footer class="cm-modal-foot">
                <button class="cm-btn-ghost" onclick="closeCreateModal()">Annulla</button>
                <button class="cm-btn-accent" id="btnCreaSubmit" onclick="creaCommunity()">
                    <i class="bi bi-check-lg"></i> Crea community
                </button>
            </footer>
        </div>
    </div>
<?php endif; ?>

    </main>
</div>

<div class="cm-toast" id="cmToast"></div>

<!-- Stylesheet già caricato via index.php (CSS/Community.css) -->
<script src="/Pulse/JS/commenti.js"></script>

<script>
const BACKEND_COMM = '/Pulse/backend/GestioneCommunity.php';
const BACKEND_CMT  = '/Pulse/backend/GestioneCommenti.php';

function toast(msg, type = 'ok') {
    const t = document.getElementById('cmToast');
    t.textContent = msg;
    t.className = 'cm-toast show ' + (type === 'error' ? 'error' : '');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.className = 'cm-toast', 2800);
}

async function api(url, payload) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    });
    return res.json();
}

<?php if (!$is_detail): /* ── JS solo per la pagina LISTA ── */ ?>

/* ────── Apri/chiudi modal di creazione ────── */
async function openCreateModal() {
    document.getElementById('createModal').classList.add('open');
    document.body.style.overflow = 'hidden';

    const sel = document.getElementById('newGenereSelect');
    sel.innerHTML = '<option value="">Caricamento generi…</option>';
    const r = await api(BACKEND_COMM, {action:'generi_liberi'});
    if (!r.ok) { sel.innerHTML = '<option>Errore</option>'; return; }
    if (!r.generi.length) {
        sel.innerHTML = '<option value="">Tutti i generi hanno già una community</option>';
        sel.disabled = true;
        return;
    }
    sel.disabled = false;
    sel.innerHTML = '<option value="">— Scegli un genere —</option>' +
        r.generi.map(g => `<option value="${g.ID}">${g.Name}</option>`).join('');
}

function closeCreateModal(e) {
    if (e && e.target.id !== 'createModal' && !e.target.classList.contains('cm-modal-close') &&
        e.type === 'click' && e.target.closest('.cm-modal')) return;
    document.getElementById('createModal').classList.remove('open');
    document.body.style.overflow = '';
}

async function creaCommunity() {
    const idGenere = +document.getElementById('newGenereSelect').value;
    const nome     = document.getElementById('newNome').value.trim();
    const desc     = document.getElementById('newDesc').value.trim();

    if (!idGenere) { toast('Seleziona un genere', 'error'); return; }
    if (!nome)     { toast('Inserisci un nome', 'error'); return; }

    const btn = document.getElementById('btnCreaSubmit');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat cm-spin"></i> Creazione…';

    const r = await api(BACKEND_COMM, {action:'crea_community', idGenere, nome, descrizione:desc});
    if (r.ok) {
        toast('Community creata!');
        setTimeout(() => location.href = '/Pulse/community?id=' + r.community_id, 700);
    } else {
        toast(r.error || 'Errore', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Crea community';
    }
}

<?php else: /* ── JS solo per la pagina DETTAGLIO ── */ ?>

/* ────── Iscrizione / disiscrizione ────── */
document.getElementById('btnIscrizione').addEventListener('click', async function() {
    const btn   = this;
    const cid   = +btn.dataset.id;
    const joined = btn.classList.contains('joined');
    btn.disabled = true;
    const r = await api(BACKEND_COMM, {
        action: joined ? 'disiscriviti' : 'iscriviti',
        community_id: cid
    });
    btn.disabled = false;
    if (!r.ok) { toast(r.error || 'Errore', 'error'); return; }

    if (r.iscritto) {
        btn.classList.add('joined');
        btn.innerHTML = '<i class="bi bi-check-lg"></i> <span>Iscritto</span>';
        toast('Ti sei unito alla community');
        setTimeout(() => location.reload(), 700);
    } else {
        btn.classList.remove('joined');
        btn.innerHTML = '<i class="bi bi-plus-lg"></i> <span>Unisciti</span>';
        toast('Hai lasciato la community');
        setTimeout(() => location.reload(), 700);
    }
});

/* ────── Pubblica nuovo post ────── */
const postText    = document.getElementById('newPostText');
const postCounter = document.getElementById('postCounter');
const btnPubblica = document.getElementById('btnPubblicaPost');

if (postText) {
    postText.addEventListener('input', () => {
        postCounter.textContent = postText.value.length;
        btnPubblica.disabled = postText.value.trim().length === 0;
    });

    btnPubblica.addEventListener('click', async () => {
        const contenuto = postText.value.trim();
        if (!contenuto) return;
        btnPubblica.disabled = true;

        const r = await api(BACKEND_COMM, {
            action: 'crea_post',
            community_id: <?= (int)$cid ?>,
            contenuto
        });

        if (r.ok) {
            toast('Post pubblicato!');
            setTimeout(() => location.reload(), 600);
        } else {
            toast(r.error || 'Errore', 'error');
            btnPubblica.disabled = false;
        }
    });
}

/* ────── Like su post ────── */
document.getElementById('postsFeed')?.addEventListener('click', async (e) => {
    const likeBtn = e.target.closest('.cm-post-like');
    if (likeBtn) {
        e.preventDefault();
        const pid = +likeBtn.dataset.id;
        const r = await api(BACKEND_CMT, {action:'toggle_like', tipo:'post', id:pid});
        if (r.ok) {
            likeBtn.classList.toggle('liked', r.liked);
            likeBtn.querySelector('i').className = r.liked ? 'bi bi-heart-fill' : 'bi bi-heart';
            likeBtn.querySelector('.cm-post-like-count').textContent = r.count;
        }
        return;
    }

    /* ── Mostra/nascondi commenti ── */
    const cToggle = e.target.closest('.cm-post-comm-toggle');
    if (cToggle) {
        const pid = +cToggle.dataset.id;
        const post = cToggle.closest('.cm-post');
        const box  = post.querySelector('.cm-post-commenti');

        if (box.style.display === 'none') {
            box.style.display = '';
            if (!box.dataset.loaded) {
                box.innerHTML = `
                    <?php
                    // Usiamo l'include _commenti.php in modo dinamico via template-literal
                    // Per renderlo dinamicamente, generiamo l'HTML inline.
                    ?>
                    <section class="cm-section" data-target-tipo="post" data-target-id="${pid}">
                        <div class="cm-composer">
                            <img src="<?= htmlspecialchars((function(){
                                $v=$_SESSION['avatar_url']??null;
                                if(!$v) return "https://ui-avatars.com/api/?name=".urlencode($_SESSION['username']??'U')."&background=8b5cf6&color=fff&size=80";
                                return str_starts_with($v,'http')?$v:'/Pulse/IMG/avatars/'.$v;
                            })()) ?>" alt="" class="cm-avatar">
                            <div class="cm-composer-body">
                                <textarea class="cm-textarea cm-main-input"
                                    placeholder="Scrivi un commento…" rows="1" maxlength="2000"></textarea>
                                <div class="cm-composer-footer">
                                    <span class="cm-counter"><span class="cm-counter-val">0</span>/2000</span>
                                    <button type="button" class="cm-send-btn" disabled>
                                        <i class="bi bi-send-fill"></i> Commenta
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="cm-list">
                            <div class="cm-loading"><i class="bi bi-arrow-repeat cm-spin"></i> Caricamento…</div>
                        </div>
                    </section>
                `;
                // Inizializza la sezione appena inserita dinamicamente
                if (window.PulseCommenti && typeof window.PulseCommenti.init === 'function') {
                    window.PulseCommenti.init(box.querySelector('.cm-section'));
                } else {
                    // Fallback: ricarica il listener
                    document.dispatchEvent(new Event('DOMContentLoaded'));
                }
                box.dataset.loaded = '1';
            }
        } else {
            box.style.display = 'none';
        }
        return;
    }

    /* ── Elimina post ── */
    const delBtn = e.target.closest('.cm-post-del');
    if (delBtn) {
        e.preventDefault();
        if (!confirm('Eliminare definitivamente questo post?')) return;
        const pid = +delBtn.dataset.id;
        const r = await api(BACKEND_COMM, {action:'elimina_post', post_id:pid});
        if (r.ok) {
            delBtn.closest('.cm-post').remove();
            toast('Post eliminato');
        } else toast(r.error || 'Errore', 'error');
    }
});

<?php endif; ?>
</script>