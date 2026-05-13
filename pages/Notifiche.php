<?php
// pages/Notifiche.php — Sistema notifiche unificato

require_once 'Database.php';
$pdo   = getConnection();
$my_id = (int)$_SESSION['user_id'];

// ── Carica notifiche ──────────────────────────────────────────────
$notifiche = [];
try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM (
            -- ── Follow ─────────────────────────────────────
            SELECT
                CONCAT('follow_', S.IDSeguitore)    AS uid,
                'follow'                             AS tipo,
                NULL                                 AS invito_id,
                S.IDSeguitore                        AS mittente_id,
                U.Username                           AS mittente_username,
                U.Avatar_URL                         AS mittente_avatar,
                NULL                                 AS lista_id,
                NULL                                 AS lista_titolo,
                NULL                                 AS invito_stato,
                S.Data_Seguimento                    AS data,
                0                                    AS letta
            FROM Segui S
            JOIN Utente U ON S.IDSeguitore = U.ID
            WHERE S.IDSeguito = :my1

            UNION ALL

            -- ── Inviti lista ────────────────────────────────
            SELECT
                CONCAT('invito_', LI.ID)             AS uid,
                'invito_lista'                        AS tipo,
                LI.ID                                AS invito_id,
                LI.IDInvitante                       AS mittente_id,
                U2.Username                          AS mittente_username,
                U2.Avatar_URL                        AS mittente_avatar,
                LI.IDLista                           AS lista_id,
                L.Titolo                             AS lista_titolo,
                LI.Stato                             AS invito_stato,
                LI.Data                              AS data,
                0                                    AS letta
            FROM Lista_Invito LI
            JOIN Utente U2 ON LI.IDInvitante = U2.ID
            JOIN Lista  L  ON LI.IDLista     = L.IDLista
            WHERE LI.IDInvitato = :my2

        ) AS combined
        ORDER BY data DESC
        LIMIT 60
    ");
    $stmt->execute([':my1' => $my_id, ':my2' => $my_id]);
    $notifiche = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notifiche = [];
}

// ── Segui reciproci (per sapere se sto già seguendo qualcuno) ─────
$sto_seguendo = [];
if ($notifiche) {
    $follow_ids = array_unique(array_filter(
        array_map(fn($n) => $n['tipo'] === 'follow' ? (int)$n['mittente_id'] : null, $notifiche)
    ));
    if ($follow_ids) {
        $ph  = implode(',', array_fill(0, count($follow_ids), '?'));
        $s   = $pdo->prepare("SELECT IDSeguito FROM Segui WHERE IDSeguitore = ? AND IDSeguito IN ($ph)");
        $s->execute([$my_id, ...$follow_ids]);
        $sto_seguendo = $s->fetchAll(PDO::FETCH_COLUMN);
    }
}

// ── Conteggi ──────────────────────────────────────────────────────
$pending_count = 0;
foreach ($notifiche as $n) {
    if ($n['tipo'] === 'invito_lista' && $n['invito_stato'] === 'pending') $pending_count++;
}

function avatarUrl(?string $val, string $username): string {
    if ($val && !str_starts_with($val, 'http')) return '/Pulse/IMG/avatars/' . $val;
    return $val ?? "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=8b5cf6&color=fff&size=80";
}

function timeAgoNotif(string $ts): string {
    $d = time() - strtotime($ts);
    if ($d < 60)      return 'adesso';
    if ($d < 3600)    return floor($d/60) . ' min fa';
    if ($d < 86400)   return floor($d/3600) . ' ore fa';
    if ($d < 604800)  return floor($d/86400) . ' gg fa';
    return date('d M Y', strtotime($ts));
}
?>

<div class="app">
    <?php require 'aside.php'; ?>

    <main class="center notif-center">

        <!-- ── HEADER ── -->
        <header class="notif-header">
            <div class="notif-header-left">
                <h1 class="notif-title">
                    <i class="bi bi-bell-fill"></i>
                    Notifiche
                </h1>
                <?php if ($pending_count > 0): ?>
                    <span class="notif-pending-pill">
                        <?= $pending_count ?> in attesa
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($notifiche): ?>
            <!-- Filtri -->
            <div class="notif-filters" id="notif-filters">
                <button class="notif-filter-btn active" data-filter="all">Tutte</button>
                <button class="notif-filter-btn" data-filter="follow">
                    <i class="bi bi-person-plus"></i> Follow
                </button>
                <button class="notif-filter-btn" data-filter="invito_lista">
                    <i class="bi bi-collection"></i> Inviti
                    <?php if ($pending_count > 0): ?>
                        <span class="notif-filter-dot"></span>
                    <?php endif; ?>
                </button>
            </div>
            <?php endif; ?>
        </header>

        <!-- ── EMPTY STATE ── -->
        <?php if (!$notifiche): ?>
            <div class="notif-empty">
                <div class="notif-empty-icon">
                    <i class="bi bi-bell"></i>
                </div>
                <p class="notif-empty-title">Nessuna notifica</p>
                <p class="notif-empty-sub">
                    Quando qualcuno ti segue o ti invita in una lista condivisa, la trovi qui.
                </p>
            </div>

        <?php else: ?>
        <!-- ── LISTA NOTIFICHE ── -->
        <div class="notif-list" id="notif-list">

            <?php foreach ($notifiche as $n):
                $avatar    = avatarUrl($n['mittente_avatar'], $n['mittente_username']);
                $ago       = timeAgoNotif($n['data']);
                $isPending = ($n['tipo'] === 'invito_lista' && $n['invito_stato'] === 'pending');
                $isAcc     = ($n['tipo'] === 'invito_lista' && $n['invito_stato'] === 'accettato');
                $isRef     = ($n['tipo'] === 'invito_lista' && $n['invito_stato'] === 'rifiutato');
                $giaSeguo  = in_array((int)$n['mittente_id'], array_map('intval', $sto_seguendo));
            ?>
            <div class="notif-row <?= $isPending ? 'notif-row--pending' : '' ?>"
                 data-tipo="<?= htmlspecialchars($n['tipo']) ?>"
                 data-invito="<?= (int)($n['invito_id'] ?? 0) ?>">

                <!-- Avatar + icona tipo -->
                <a href="/Pulse/utente/<?= rawurlencode($n['mittente_username']) ?>"
                   class="notif-avatar-wrap">
                    <img src="<?= htmlspecialchars($avatar) ?>"
                         alt="@<?= htmlspecialchars($n['mittente_username']) ?>"
                         class="notif-avatar">
                    <span class="notif-type-dot notif-dot-<?= htmlspecialchars($n['tipo']) ?>">
                        <i class="bi <?= $n['tipo'] === 'follow' ? 'bi-person-plus-fill' : 'bi-collection-fill' ?>"></i>
                    </span>
                </a>

                <!-- Corpo -->
                <div class="notif-body">
                    <p class="notif-text">
                        <a href="/Pulse/utente/<?= rawurlencode($n['mittente_username']) ?>"
                           class="notif-user-link">
                            @<?= htmlspecialchars($n['mittente_username']) ?>
                        </a>
                        <?php if ($n['tipo'] === 'follow'): ?>
                            ha iniziato a seguirti.
                        <?php else: ?>
                            ti ha invitato nella lista
                            <a href="/Pulse/lista?id=<?= (int)$n['lista_id'] ?>"
                               class="notif-lista-link">
                                "<?= htmlspecialchars($n['lista_titolo']) ?>"
                            </a>.
                        <?php endif; ?>
                    </p>

                    <!-- Azioni -->
                    <div class="notif-actions">
                        <?php if ($n['tipo'] === 'follow'): ?>
                            <button class="notif-btn-follow <?= $giaSeguo ? 'following' : '' ?>"
                                    data-uid="<?= (int)$n['mittente_id'] ?>"
                                    onclick="toggleFollow(<?= (int)$n['mittente_id'] ?>, this)">
                                <?php if ($giaSeguo): ?>
                                    <i class="bi bi-check-lg"></i> Segui anche tu
                                <?php else: ?>
                                    <i class="bi bi-person-plus"></i> Segui anche tu
                                <?php endif; ?>
                            </button>

                        <?php elseif ($isPending): ?>
                            <button class="notif-btn-accept"
                                    onclick="rispondiInvito(<?= (int)$n['invito_id'] ?>, 'accetta', this)">
                                <i class="bi bi-check-lg"></i> Accetta
                            </button>
                            <button class="notif-btn-reject"
                                    onclick="rispondiInvito(<?= (int)$n['invito_id'] ?>, 'rifiuta', this)">
                                <i class="bi bi-x-lg"></i> Rifiuta
                            </button>

                        <?php elseif ($isAcc): ?>
                            <span class="notif-stato accettato">
                                <i class="bi bi-check-circle-fill"></i> Accettato
                            </span>

                        <?php elseif ($isRef): ?>
                            <span class="notif-stato rifiutato">
                                <i class="bi bi-x-circle"></i> Rifiutato
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tempo -->
                <span class="notif-ago"><?= $ago ?></span>
            </div>
            <?php endforeach; ?>

        </div>
        <?php endif; ?>

    </main>
</div>

<div class="toast" id="toast"></div>

<script>
// ── Toast ─────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = `toast ${type} show`;
    setTimeout(() => t.classList.remove('show'), 3200);
}

// ── Filtri ────────────────────────────────────────────────────────
document.querySelectorAll('.notif-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.notif-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const filter = btn.dataset.filter;
        document.querySelectorAll('.notif-row').forEach(row => {
            row.style.display = (filter === 'all' || row.dataset.tipo === filter) ? '' : 'none';
        });
    });
});

// ── Rispondi invito ───────────────────────────────────────────────
async function rispondiInvito(invito_id, azione, btn) {
    const wrap = btn.closest('.notif-actions');
    wrap.querySelectorAll('button').forEach(b => b.disabled = true);
    try {
        const res  = await fetch('/Pulse/backend/gestioneinviti.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: azione, invito_id })
        });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error ?? 'Errore');

        const stato = azione === 'accetta' ? 'accettato' : 'rifiutato';
        const icon  = azione === 'accetta' ? 'check-circle-fill' : 'x-circle';
        wrap.outerHTML = `<div class="notif-actions">
            <span class="notif-stato ${stato}">
                <i class="bi bi-${icon}"></i>
                ${azione === 'accetta' ? 'Accettato' : 'Rifiutato'}
            </span>
        </div>`;

        btn.closest('.notif-row')?.classList.remove('notif-row--pending');
        showToast(azione === 'accetta' ? 'Hai accettato l\'invito!' : 'Invito rifiutato.');

        if (azione === 'accetta' && json.lista_id) {
            setTimeout(() => window.location.href = '/Pulse/lista?id=' + json.lista_id, 1400);
        }
    } catch (err) {
        showToast(err.message, 'error');
        wrap.querySelectorAll('button').forEach(b => b.disabled = false);
    }
}

// ── Segui / smetti di seguire ─────────────────────────────────────
async function toggleFollow(uid, btn) {
    const following = btn.classList.contains('following');
    btn.disabled = true;

    try {
        const res  = await fetch('/Pulse/backend/ToggleFollow.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ target_id: uid })
        });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error ?? 'Errore');

        const nowFollowing = json.following ?? !following;
        btn.classList.toggle('following', nowFollowing);
        btn.innerHTML = nowFollowing
            ? '<i class="bi bi-check-lg"></i> Segui anche tu'
            : '<i class="bi bi-person-plus"></i> Segui anche tu';
        showToast(nowFollowing ? 'Ora lo segui!' : 'Non lo segui più.');
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
    }
}
</script>