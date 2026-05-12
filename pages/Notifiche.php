<?php
// pages/Notifiche.php — Sistema notifiche unificato
// Tipi gestiti: follow | invito_lista

require_once 'Database.php';
$pdo   = getConnection();
$my_id = (int)$_SESSION['user_id'];

// ══════════════════════════════════════════════
//  CARICA NOTIFICHE
//  UNION: follow (da Segui) + inviti lista (da Lista_Invito)
// ══════════════════════════════════════════════
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
    // Se le tabelle non esistono ancora, mostra lista vuota senza errore
    $notifiche = [];
}

// ── Conta inviti pending ──────────────────────
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
    if ($d < 604800)  return floor($d/86400) . ' giorni fa';
    return date('d M Y', strtotime($ts));
}
?>

<div class="app">
    <?php require 'aside.php'; ?>

    <main class="center notif-center">

        <header class="notif-header">
            <h1 class="notif-title">
                <i class="bi bi-bell-fill" style="color:var(--accent)"></i>
                Notifiche
            </h1>
            <?php if ($pending_count > 0): ?>
                <span class="notif-badge-header"><?= $pending_count ?> in attesa</span>
            <?php endif; ?>
        </header>

        <?php if (!$notifiche): ?>
            <div class="notif-empty">
                <i class="bi bi-bell" style="font-size:52px;color:var(--muted)"></i>
                <p class="notif-empty-title">Nessuna notifica</p>
                <p class="notif-empty-sub">Quando qualcuno ti segue o ti invita in una lista, appare qui.</p>
            </div>
        <?php else: ?>

            <div class="notif-list">
                <?php foreach ($notifiche as $n):
                    $avatar = avatarUrl($n['mittente_avatar'], $n['mittente_username']);
                    $ago    = timeAgoNotif($n['data']);
                    $isPending = ($n['tipo'] === 'invito_lista' && $n['invito_stato'] === 'pending');
                    $isAccepted = ($n['tipo'] === 'invito_lista' && $n['invito_stato'] === 'accettato');
                    $isRejected = ($n['tipo'] === 'invito_lista' && $n['invito_stato'] === 'rifiutato');
                ?>
                <div class="notif-item <?= $isPending ? 'notif-item--pending' : '' ?>"
                     data-invito="<?= (int)($n['invito_id'] ?? 0) ?>">

                    <!-- Avatar mittente -->
                    <a href="/Pulse/utente/<?= rawurlencode($n['mittente_username']) ?>"
                       class="notif-avatar-link">
                        <img src="<?= htmlspecialchars($avatar) ?>"
                             alt="@<?= htmlspecialchars($n['mittente_username']) ?>"
                             class="notif-avatar">
                        <!-- Icona tipo sopra avatar -->
                        <span class="notif-type-icon
                            <?= $n['tipo'] === 'follow' ? 'notif-type-follow' : 'notif-type-lista' ?>">
                            <i class="bi <?= $n['tipo'] === 'follow'
                                ? 'bi-person-plus-fill'
                                : 'bi-collection-fill' ?>"></i>
                        </span>
                    </a>

                    <!-- Corpo -->
                    <div class="notif-body">
                        <?php if ($n['tipo'] === 'follow'): ?>
                            <p class="notif-text">
                                <a href="/Pulse/utente/<?= rawurlencode($n['mittente_username']) ?>"
                                   class="notif-user-link">
                                    @<?= htmlspecialchars($n['mittente_username']) ?>
                                </a>
                                ha iniziato a seguirti.
                            </p>
                            <span class="notif-time"><?= $ago ?></span>

                        <?php elseif ($n['tipo'] === 'invito_lista'): ?>
                            <p class="notif-text">
                                <a href="/Pulse/utente/<?= rawurlencode($n['mittente_username']) ?>"
                                   class="notif-user-link">
                                    @<?= htmlspecialchars($n['mittente_username']) ?>
                                </a>
                                ti ha invitato nella lista
                                <a href="/Pulse/lista?id=<?= (int)$n['lista_id'] ?>"
                                   class="notif-lista-link">
                                    "<?= htmlspecialchars($n['lista_titolo']) ?>"
                                </a>
                            </p>
                            <span class="notif-time"><?= $ago ?></span>

                            <!-- Azioni invito -->
                            <?php if ($isPending): ?>
                                <div class="notif-invite-actions">
                                    <button class="notif-btn-accept"
                                        onclick="rispondiInvito(<?= (int)$n['invito_id'] ?>, 'accetta', this)">
                                        <i class="bi bi-check-lg"></i> Accetta
                                    </button>
                                    <button class="notif-btn-reject"
                                        onclick="rispondiInvito(<?= (int)$n['invito_id'] ?>, 'rifiuta', this)">
                                        <i class="bi bi-x-lg"></i> Rifiuta
                                    </button>
                                </div>

                            <?php elseif ($isAccepted): ?>
                                <span class="notif-stato accettato">
                                    <i class="bi bi-check-circle-fill"></i> Accettato
                                </span>

                            <?php elseif ($isRejected): ?>
                                <span class="notif-stato rifiutato">
                                    <i class="bi bi-x-circle-fill"></i> Rifiutato
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

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

async function rispondiInvito(invito_id, azione, btn) {
    const wrap = btn.closest('.notif-invite-actions');
    wrap.querySelectorAll('button').forEach(b => b.disabled = true);

    try {
        const res  = await fetch('/Pulse/backend/gestioneinviti.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: azione, invito_id })
        });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error ?? 'Errore');

        // Sostituisci i bottoni con lo stato
        const stato = azione === 'accetta' ? 'accettato' : 'rifiutato';
        const icon  = azione === 'accetta' ? 'check-circle-fill' : 'x-circle-fill';
        wrap.outerHTML = `<span class="notif-stato ${stato}">
            <i class="bi bi-${icon}"></i>
            ${azione === 'accetta' ? 'Accettato' : 'Rifiutato'}
        </span>`;

        // Togli highlight pending dall'item
        btn.closest('.notif-item')?.classList.remove('notif-item--pending');

        showToast(azione === 'accetta' ? 'Hai accettato l\'invito!' : 'Invito rifiutato.');
        if (azione === 'accetta' && json.lista_id) {
            setTimeout(() => window.location.href = '/Pulse/lista?id=' + json.lista_id, 1200);
        }
    } catch (err) {
        showToast(err.message, 'error');
        wrap.querySelectorAll('button').forEach(b => b.disabled = false);
    }
}
</script>