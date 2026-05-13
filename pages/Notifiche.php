<?php
// pages/Notifiche.php — Sistema notifiche unificato

require_once 'Database.php';
$pdo   = getConnection();
$my_id = (int)$_SESSION['user_id'];

$notifiche = [];
try {
    $stmt = $pdo->prepare("
    SELECT *
    FROM (
        /* ── Follow ─────────────────────────────── */
        SELECT
            CONCAT('follow_', S.IDSeguitore)  AS uid,
            'follow'                           AS tipo,
            NULL AS invito_id,
            NULL AS post_id,
            NULL AS community_id,
            S.IDSeguitore                      AS mittente_id,
            U.Username                         AS mittente_username,
            U.Avatar_URL                       AS mittente_avatar,
            NULL AS lista_id,
            NULL AS lista_titolo,
            NULL AS invito_stato,
            NULL AS community_nome,
            NULL AS ref_titolo,
            NULL AS ref_lista_titolo,
            NULL AS ref_id,
            NULL AS commento_id,
            S.Data_Seguimento                  AS data
        FROM Segui S
        JOIN Utente U ON S.IDSeguitore = U.ID
        WHERE S.IDSeguito = :my1

        UNION ALL

        /* ── Inviti lista ────────────────────────── */
        SELECT
            CONCAT('invito_', LI.ID)           AS uid,
            'invito_lista'                     AS tipo,
            LI.ID                              AS invito_id,
            NULL                               AS post_id,
            NULL                               AS community_id,
            LI.IDInvitante                     AS mittente_id,
            U2.Username                        AS mittente_username,
            U2.Avatar_URL                      AS mittente_avatar,
            LI.IDLista                         AS lista_id,
            L.Titolo                           AS lista_titolo,
            LI.Stato                           AS invito_stato,
            NULL                               AS community_nome,
            NULL                               AS ref_titolo,
            NULL                               AS ref_lista_titolo,
            NULL                               AS ref_id,
            NULL                               AS commento_id,
            LI.Data                            AS data
        FROM Lista_Invito LI
        JOIN Utente U2 ON LI.IDInvitante = U2.ID
        JOIN Lista  L  ON LI.IDLista     = L.IDLista
        WHERE LI.IDInvitato = :my2

        UNION ALL

        /* ── Nuovi post nelle community di cui sono membro ── */
        SELECT
            CONCAT('post_', P.ID)              AS uid,
            'nuovo_post'                       AS tipo,
            NULL                               AS invito_id,
            P.ID                               AS post_id,
            C.ID                               AS community_id,
            U3.ID                              AS mittente_id,
            U3.Username                        AS mittente_username,
            U3.Avatar_URL                      AS mittente_avatar,
            NULL                               AS lista_id,
            NULL                               AS lista_titolo,
            NULL                               AS invito_stato,
            C.Nome                             AS community_nome,
            NULL                               AS ref_titolo,
            NULL                               AS ref_lista_titolo,
            NULL                               AS ref_id,
            NULL                               AS commento_id,
            P.Data_Pubblicazione               AS data
        FROM Post P
        JOIN Utente U3 ON P.IDUtente = U3.ID
        JOIN Community C ON P.IDCommunity = C.ID
        WHERE P.IDCommunity IN (
            SELECT IDCommunity FROM Iscrizione_Community WHERE IDUtente = :my3
        )
        AND P.IDUtente != :my4
        AND P.Data_Pubblicazione >= DATE_SUB(NOW(), INTERVAL 7 DAY)

        UNION ALL

        /* ── Nuovi iscritti alle community che ho creato ── */
        SELECT
            CONCAT('iscritto_', IC.IDUtente, '_', IC.IDCommunity) AS uid,
            'iscrizione_community'             AS tipo,
            NULL                               AS invito_id,
            NULL                               AS post_id,
            C2.ID                              AS community_id,
            IC.IDUtente                        AS mittente_id,
            U4.Username                        AS mittente_username,
            U4.Avatar_URL                      AS mittente_avatar,
            NULL                               AS lista_id,
            NULL                               AS lista_titolo,
            NULL                               AS invito_stato,
            C2.Nome                            AS community_nome,
            NULL                               AS ref_titolo,
            NULL                               AS ref_lista_titolo,
            NULL                               AS ref_id,
            NULL                               AS commento_id,
            NOW()                              AS data
        FROM Iscrizione_Community IC
        JOIN Utente U4 ON IC.IDUtente = U4.ID
        JOIN Community C2 ON IC.IDCommunity = C2.ID
        WHERE C2.ID IN (
            SELECT IDCommunity FROM Iscrizione_Community WHERE IDUtente = :my5
        )
        AND IC.IDUtente != :my6

        UNION ALL

        /* ── Commenti al MIO Log ── */
        SELECT
            CONCAT('commento_log_', C.ID)      AS uid,
            'commento_log'                     AS tipo,
            NULL                               AS invito_id,
            NULL                               AS post_id,
            NULL                               AS community_id,
            C.IDUtente                         AS mittente_id,
            Uc1.Username                       AS mittente_username,
            Uc1.Avatar_URL                     AS mittente_avatar,
            NULL                               AS lista_id,
            NULL                               AS lista_titolo,
            NULL                               AS invito_stato,
            NULL                               AS community_nome,
            F.Title                            AS ref_titolo,
            NULL                               AS ref_lista_titolo,
            F.TMDB_ID                          AS ref_id,
            C.ID                               AS commento_id,
            C.Data                             AS data
        FROM Commento C
        JOIN Log    L  ON C.IDLog    = L.ID
        JOIN Film   F  ON L.IDFilm   = F.ID
        JOIN Utente Uc1 ON C.IDUtente = Uc1.ID
        WHERE L.IDUtente  = :my_log
          AND C.IDUtente != :my_log2
          AND C.IDLog IS NOT NULL

        UNION ALL

        /* ── Commenti al MIO Post ── */
        SELECT
            CONCAT('commento_post_', C.ID)     AS uid,
            'commento_post'                    AS tipo,
            NULL                               AS invito_id,
            P.ID                               AS post_id,
            P.IDCommunity                      AS community_id,
            C.IDUtente                         AS mittente_id,
            Uc2.Username                       AS mittente_username,
            Uc2.Avatar_URL                     AS mittente_avatar,
            NULL                               AS lista_id,
            NULL                               AS lista_titolo,
            NULL                               AS invito_stato,
            Cm.Nome                            AS community_nome,
            NULL                               AS ref_titolo,
            NULL                               AS ref_lista_titolo,
            P.ID                               AS ref_id,
            C.ID                               AS commento_id,
            C.Data                             AS data
        FROM Commento C
        JOIN Post    P   ON C.IDPost   = P.ID
        JOIN Utente  Uc2 ON C.IDUtente = Uc2.ID
        LEFT JOIN Community Cm ON P.IDCommunity = Cm.ID
        WHERE P.IDUtente  = :my_post
          AND C.IDUtente != :my_post2
          AND C.IDPost IS NOT NULL

        UNION ALL

        /* ── Commenti alla MIA Lista ── */
        SELECT
            CONCAT('commento_lista_', C.ID)    AS uid,
            'commento_lista'                   AS tipo,
            NULL                               AS invito_id,
            NULL                               AS post_id,
            NULL                               AS community_id,
            C.IDUtente                         AS mittente_id,
            Uc3.Username                       AS mittente_username,
            Uc3.Avatar_URL                     AS mittente_avatar,
            Li.IDLista                         AS lista_id,
            Li.Titolo                          AS lista_titolo,
            NULL                               AS invito_stato,
            NULL                               AS community_nome,
            NULL                               AS ref_titolo,
            Li.Titolo                          AS ref_lista_titolo,
            Li.IDLista                         AS ref_id,
            C.ID                               AS commento_id,
            C.Data                             AS data
        FROM Commento C
        JOIN Lista  Li  ON C.IDLista   = Li.IDLista
        JOIN Utente Uc3 ON C.IDUtente  = Uc3.ID
        WHERE Li.IDUtente  = :my_lista
          AND C.IDUtente  != :my_lista2
          AND C.IDLista IS NOT NULL

    ) AS combined
    ORDER BY data DESC
    LIMIT 80
    ");
    $stmt->execute([
        ':my1'       => $my_id,
        ':my2'       => $my_id,
        ':my3'       => $my_id,
        ':my4'       => $my_id,
        ':my5'       => $my_id,
        ':my6'       => $my_id,
        ':my_log'    => $my_id,
        ':my_log2'   => $my_id,
        ':my_post'   => $my_id,
        ':my_post2'  => $my_id,
        ':my_lista'  => $my_id,
        ':my_lista2' => $my_id,
    ]);
    $notifiche = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[Notifiche] ' . $e->getMessage());
    $notifiche = [];
}

// ── Segui reciproci ───────────────────────────────────────────────
$sto_seguendo = [];
if ($notifiche) {
    $follow_ids = array_unique(array_filter(
        array_map(fn($n) => $n['tipo'] === 'follow' ? (int)$n['mittente_id'] : null, $notifiche)
    ));
    if ($follow_ids) {
        $ph = implode(',', array_fill(0, count($follow_ids), '?'));
        $s  = $pdo->prepare("SELECT IDSeguito FROM Segui WHERE IDSeguitore = ? AND IDSeguito IN ($ph)");
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
    if ($d < 60)     return 'adesso';
    if ($d < 3600)   return floor($d / 60) . ' min fa';
    if ($d < 86400)  return floor($d / 3600) . ' ore fa';
    if ($d < 604800) return floor($d / 86400) . ' gg fa';
    return date('d M Y', strtotime($ts));
}
?>

<div class="app">
    <?php require 'aside.php'; ?>

    <main class="center notif-center">

        <header class="notif-header">
            <div class="notif-header-left">
                <h1 class="notif-title">
                    <i class="bi bi-bell-fill"></i> Notifiche
                </h1>
                <?php if ($pending_count > 0): ?>
                    <span class="notif-pending-pill"><?= $pending_count ?> in attesa</span>
                <?php endif; ?>
            </div>

            <?php if ($notifiche): ?>
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
                <button class="notif-filter-btn" data-filter="nuovo_post">
                    <i class="bi bi-card-text"></i> Post
                </button>
                <button class="notif-filter-btn" data-filter="iscrizione_community">
                    <i class="bi bi-people"></i> Iscritti
                </button>
                <button class="notif-filter-btn" data-filter="commento">
                    <i class="bi bi-chat-dots"></i> Commenti
                </button>
            </div>
            <?php endif; ?>
        </header>

        <?php if (!$notifiche): ?>
            <div class="notif-empty">
                <div class="notif-empty-icon"><i class="bi bi-bell"></i></div>
                <p class="notif-empty-title">Nessuna notifica</p>
                <p class="notif-empty-sub">Quando qualcuno ti segue, commenta o pubblica, lo trovi qui.</p>
            </div>

        <?php else: ?>
        <div class="notif-list" id="notif-list">

            <?php foreach ($notifiche as $n):
                $avatar    = avatarUrl($n['mittente_avatar'], $n['mittente_username']);
                $ago       = timeAgoNotif($n['data']);
                $isPending = ($n['tipo'] === 'invito_lista' && $n['invito_stato'] === 'pending');
                $isAcc     = ($n['tipo'] === 'invito_lista' && $n['invito_stato'] === 'accettato');
                $isRef     = ($n['tipo'] === 'invito_lista' && $n['invito_stato'] === 'rifiutato');
                $giaSeguo  = in_array((int)$n['mittente_id'], array_map('intval', $sto_seguendo));
                $isCommento = in_array($n['tipo'], ['commento_log', 'commento_post', 'commento_lista']);
            ?>
            <div class="notif-row <?= $isPending ? 'notif-row--pending' : '' ?>"
                 data-tipo="<?= htmlspecialchars($n['tipo']) ?>"
                 data-invito="<?= (int)($n['invito_id'] ?? 0) ?>">

                <!-- Avatar + dot icona tipo -->
                <a href="/Pulse/utente/<?= rawurlencode($n['mittente_username']) ?>"
                   class="notif-avatar-wrap">
                    <img src="<?= htmlspecialchars($avatar) ?>"
                         alt="@<?= htmlspecialchars($n['mittente_username']) ?>"
                         class="notif-avatar">
                    <span class="notif-type-dot notif-dot-<?= htmlspecialchars($n['tipo']) ?>">
                        <?php
                        $icon = match($n['tipo']) {
                            'follow'               => 'bi-person-plus-fill',
                            'invito_lista'         => 'bi-collection-fill',
                            'nuovo_post'           => 'bi-card-text-fill',
                            'iscrizione_community' => 'bi-people-fill',
                            'commento_log',
                            'commento_post',
                            'commento_lista'       => 'bi-chat-dots-fill',
                            default                => 'bi-bell-fill',
                        };
                        ?>
                        <i class="bi <?= $icon ?>"></i>
                    </span>
                </a>

                <!-- Corpo notifica -->
                <div class="notif-body">

                    <!-- ── TESTO ── -->
                    <p class="notif-text">
                        <a href="/Pulse/utente/<?= rawurlencode($n['mittente_username']) ?>"
                           class="notif-user-link">
                            @<?= htmlspecialchars($n['mittente_username']) ?>
                        </a>

                        <?php if ($n['tipo'] === 'follow'): ?>
                            ha iniziato a seguirti.

                        <?php elseif ($n['tipo'] === 'invito_lista'): ?>
                            ti ha invitato nella lista
                            <a href="/Pulse/lista?id=<?= (int)$n['lista_id'] ?>" class="notif-lista-link">
                                "<?= htmlspecialchars($n['lista_titolo'] ?? '') ?>"
                            </a>.

                        <?php elseif ($n['tipo'] === 'nuovo_post'): ?>
                            ha pubblicato un post in
                            <a href="/Pulse/community?id=<?= (int)$n['community_id'] ?>" class="notif-lista-link">
                                <?= htmlspecialchars($n['community_nome'] ?? '') ?>
                            </a>.

                        <?php elseif ($n['tipo'] === 'iscrizione_community'): ?>
                            si è unito alla community
                            <a href="/Pulse/community?id=<?= (int)$n['community_id'] ?>" class="notif-lista-link">
                                <?= htmlspecialchars($n['community_nome'] ?? '') ?>
                            </a>.

                        <?php elseif ($n['tipo'] === 'commento_log'): ?>
                            ha commentato il tuo log di
                            <a href="/Pulse/film/<?= (int)$n['ref_id'] ?>" class="notif-lista-link">
                                <?= htmlspecialchars($n['ref_titolo'] ?? 'un film') ?>
                            </a>.

                        <?php elseif ($n['tipo'] === 'commento_post'): ?>
                            ha commentato il tuo post
                            <?php if (!empty($n['community_nome'])): ?>
                                in <a href="/Pulse/community?id=<?= (int)$n['community_id'] ?>" class="notif-lista-link">
                                    <?= htmlspecialchars($n['community_nome']) ?>
                                </a>
                            <?php endif; ?>.

                        <?php elseif ($n['tipo'] === 'commento_lista'): ?>
                            ha commentato la tua lista
                            <a href="/Pulse/lista?id=<?= (int)$n['lista_id'] ?>" class="notif-lista-link">
                                "<?= htmlspecialchars($n['lista_titolo'] ?? '') ?>"
                            </a>.

                        <?php endif; ?>
                    </p>

                    <!-- ── AZIONI ── -->
                    <div class="notif-actions">
                        <?php if ($n['tipo'] === 'follow'): ?>
                            <button class="notif-btn-follow <?= $giaSeguo ? 'following' : '' ?>"
                                    data-uid="<?= (int)$n['mittente_id'] ?>"
                                    onclick="toggleFollow(<?= (int)$n['mittente_id'] ?>, this)">
                                <i class="bi <?= $giaSeguo ? 'bi-check-lg' : 'bi-person-plus' ?>"></i>
                                Segui anche tu
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

                        <?php elseif ($n['tipo'] === 'nuovo_post'): ?>
                            <a href="/Pulse/community?id=<?= (int)$n['community_id'] ?>"
                               class="notif-btn-accept" style="text-decoration:none">
                                <i class="bi bi-box-arrow-up-right"></i> Vai alla community
                            </a>

                        <?php elseif ($n['tipo'] === 'iscrizione_community'): ?>
                            <a href="/Pulse/utente/<?= rawurlencode($n['mittente_username']) ?>"
                               class="notif-btn-follow" style="text-decoration:none">
                                <i class="bi bi-person"></i> Profilo
                            </a>

                        <?php elseif ($isCommento): ?>
                            <a href="<?php
                                if ($n['tipo'] === 'commento_log')
                                    echo '/Pulse/film/' . (int)$n['ref_id'];
                                elseif ($n['tipo'] === 'commento_post')
                                    echo '/Pulse/community?id=' . (int)$n['community_id'];
                                else
                                    echo '/Pulse/lista?id=' . (int)$n['lista_id'];
                            ?>" class="notif-btn-follow" style="text-decoration:none">
                                <i class="bi bi-arrow-right-circle"></i> Vai
                            </a>

                        <?php endif; ?>
                    </div>
                </div>

                <span class="notif-ago"><?= $ago ?></span>
            </div>
            <?php endforeach; ?>

        </div>
        <?php endif; ?>

    </main>
</div>

<div class="toast" id="toast"></div>

<script>
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = `toast ${type} show`;
    setTimeout(() => t.classList.remove('show'), 3200);
}

// ── Filtri (unico listener, gestisce anche commento_*) ────────────
document.querySelectorAll('.notif-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.notif-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const filter = btn.dataset.filter;
        document.querySelectorAll('.notif-row').forEach(row => {
            const tipo  = row.dataset.tipo;
            const match = filter === 'all'
                || tipo === filter
                || (filter === 'commento' && tipo.startsWith('commento_'));
            row.style.display = match ? '' : 'none';
        });
    });
});

// ── Rispondi invito lista ─────────────────────────────────────────
async function rispondiInvito(invito_id, azione, btn) {
    const wrap = btn.closest('.notif-actions');
    wrap.querySelectorAll('button').forEach(b => b.disabled = true);
    try {
        const res  = await fetch('/Pulse/backend/gestioneinviti.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: azione, invito_id })
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
        showToast(azione === 'accetta' ? "Hai accettato l'invito!" : 'Invito rifiutato.');
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
        const res  = await fetch('/Pulse/backend/gestioneutenti.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: following ? 'unfollow' : 'follow', user_id: uid })
        });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error ?? 'Errore');

        const nowFollowing = json.following;
        btn.classList.toggle('following', nowFollowing);
        btn.innerHTML = `<i class="bi ${nowFollowing ? 'bi-check-lg' : 'bi-person-plus'}"></i> Segui anche tu`;
        showToast(nowFollowing ? 'Ora lo segui!' : 'Non lo segui più.');
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
    }
}
</script>