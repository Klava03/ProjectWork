<?php
// pages/Profilo.php — profilo UNIFICATO (proprio + altrui)
//
// Rotte gestite:
//   /Pulse/profilo           → profilo dell'utente loggato ($is_own = true)
//   /Pulse/utente/{username} → profilo pubblico ($is_own dipende da sessione)

require_once 'Database.php';
$pdo   = getConnection();
$my_id = (int)$_SESSION['user_id'];

// ══════════════════════════════════════════════
//  RISOLVI A QUALE UTENTE APPARTIENE LA PAGINA
// ══════════════════════════════════════════════
global $page, $sub;   // iniettate da index.php

if ($page === 'utente') {
    $username_target = trim($sub ?? '');
    if ($username_target === '') {
        echo '<div style="padding:60px;text-align:center;color:var(--muted)">Utente non specificato.</div>';
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM Utente WHERE Username = ? LIMIT 1");
    $stmt->execute([$username_target]);
    $profile_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile_user) {
        echo '<div style="padding:60px;text-align:center;color:var(--muted)">Utente <strong>@'
            . htmlspecialchars($username_target) . '</strong> non trovato.</div>';
        return;
    }
    $profile_id = (int)$profile_user['ID'];
    $is_own     = ($profile_id === $my_id);

    // Redirect pulito se è il proprio profilo
    if ($is_own) {
        header('Location: /Pulse/profilo');
        exit;
    }
} else {
    // page === 'profilo'
    $stmt = $pdo->prepare("SELECT * FROM Utente WHERE ID = ? LIMIT 1");
    $stmt->execute([$my_id]);
    $profile_user = $stmt->fetch(PDO::FETCH_ASSOC);
    $profile_id   = $my_id;
    $is_own       = true;
}

$tab = $_GET['tab'] ?? 'diario';   // diario | watched | follower | seguiti

// Base URL per i link dei tab
$profile_base = $is_own
    ? '/Pulse/profilo'
    : '/Pulse/utente/' . rawurlencode($profile_user['Username']);

// ══════════════════════════════════════════════
//  STATISTICHE
// ══════════════════════════════════════════════
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Visione WHERE IDUtente = ? AND Is_Watched = 1");
$stmt->execute([$profile_id]);
$cnt_visti = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM Log WHERE IDUtente = ?");
$stmt->execute([$profile_id]);
$cnt_log = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM Segui WHERE IDSeguito = ?");
$stmt->execute([$profile_id]);
$cnt_follower = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM Segui WHERE IDSeguitore = ?");
$stmt->execute([$profile_id]);
$cnt_seguiti = (int)$stmt->fetchColumn();

// Sto seguendo? (solo per profili altrui)
$sto_seguendo = false;
if (!$is_own) {
    $stmt = $pdo->prepare("SELECT 1 FROM Segui WHERE IDSeguitore = ? AND IDSeguito = ? LIMIT 1");
    $stmt->execute([$my_id, $profile_id]);
    $sto_seguendo = (bool)$stmt->fetchColumn();
}

// ══════════════════════════════════════════════
//  PREFERITI (max 5)
// ══════════════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT F.ID, F.TMDB_ID, F.Title, F.Poster_Path, F.Release_Date
    FROM Visione V
    JOIN Film F ON V.IDFilm = F.ID
    WHERE V.IDUtente = ? AND V.Is_Favourite = 1
    ORDER BY F.Title ASC
    LIMIT 5
");
$stmt->execute([$profile_id]);
$pref_slots = array_pad($stmt->fetchAll(PDO::FETCH_ASSOC), 5, null);

// ══════════════════════════════════════════════
//  TAB: DIARIO
// ══════════════════════════════════════════════
$logs = [];
if ($tab === 'diario') {
    $stmt = $pdo->prepare("
        SELECT L.ID            AS log_id,
               L.Data          AS data_vis,
               L.Voto,
               L.Recensione,
               L.Data_Pubblicazione,
               F.TMDB_ID       AS tmdb_id,
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
    $stmt->execute([$profile_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ══════════════════════════════════════════════
//  TAB: WATCHED
// ══════════════════════════════════════════════
$watched = [];
if ($tab === 'watched') {
    $stmt = $pdo->prepare("
        SELECT F.TMDB_ID, F.Title, F.Poster_Path, F.Release_Date, V.Rating
        FROM Visione V
        JOIN Film F ON V.IDFilm = F.ID
        WHERE V.IDUtente = ? AND V.Is_Watched = 1
        ORDER BY F.Title ASC
    ");
    $stmt->execute([$profile_id]);
    $watched = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ══════════════════════════════════════════════
//  TAB: FOLLOWER
// ══════════════════════════════════════════════
$follower_list = [];
if ($tab === 'follower') {
    $stmt = $pdo->prepare("
        SELECT U.ID, U.Username, U.Bio, U.Avatar_URL,
               (SELECT 1 FROM Segui S2
                WHERE S2.IDSeguitore = ? AND S2.IDSeguito = U.ID LIMIT 1) AS io_seguo
        FROM Segui S
        JOIN Utente U ON S.IDSeguitore = U.ID
        WHERE S.IDSeguito = ?
        ORDER BY S.Data_Seguimento DESC
    ");
    $stmt->execute([$my_id, $profile_id]);
    $follower_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ══════════════════════════════════════════════
//  TAB: SEGUITI
// ══════════════════════════════════════════════
$seguiti_list = [];
if ($tab === 'seguiti') {
    $stmt = $pdo->prepare("
        SELECT U.ID, U.Username, U.Bio, U.Avatar_URL,
               (SELECT 1 FROM Segui S2
                WHERE S2.IDSeguitore = ? AND S2.IDSeguito = U.ID LIMIT 1) AS io_seguo
        FROM Segui S
        JOIN Utente U ON S.IDSeguito = U.ID
        WHERE S.IDSeguitore = ?
        ORDER BY S.Data_Seguimento DESC
    ");
    $stmt->execute([$my_id, $profile_id]);
    $seguiti_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ══════════════════════════════════════════════
//  HELPERS
// ══════════════════════════════════════════════
function resolveAvatar(?string $val, string $username): string
{
    if (!$val) return "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=8b5cf6&color=fff&size=200";
    return str_starts_with($val, 'http') ? $val : '/Pulse/IMG/avatars/' . $val;
}

function slugify(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9\s\-]/u', '', $s);
    $s = preg_replace('/[\s\-]+/', '-', $s);
    return trim($s, '-');
}

function starsHTML(float $r): string
{
    $html = '<span class="diary-stars">';
    for ($i = 1; $i <= 5; $i++) {
        if ($r >= $i)        $html .= '<span class="sd-star full">★</span>';
        elseif ($r >= $i - .5) $html .= '<span class="sd-star half">★</span>';
        else                 $html .= '<span class="sd-star">★</span>';
    }
    return $html . '</span>';
}

$avatar = resolveAvatar($profile_user['Avatar_URL'], $profile_user['Username']);
$giorni = ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'];
$mesi   = ['', 'Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'];
?>

<div class="app">
    <?php require 'aside.php'; ?>

    <main class="center" style="gap:0; padding:0 0 60px 0;">

        <!-- ══════════════════════════════════════
             HEADER PROFILO
             ══════════════════════════════════════ -->
        <section class="prof-header">
            <div class="prof-top">
                <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="prof-avatar">

                <div class="prof-text">
                    <h2>@<?= htmlspecialchars($profile_user['Username']) ?></h2>

                    <?php if (!empty($profile_user['Bio'])): ?>
                        <p class="prof-bio"><?= htmlspecialchars($profile_user['Bio']) ?></p>
                    <?php endif; ?>

                    <?php if ($is_own): ?>
                        <a href="/Pulse/modifica-profilo" class="btn-edit-prof">
                            <i class="bi bi-pencil"></i> Modifica profilo
                        </a>
                    <?php else: ?>
                        <button class="up-action-btn <?= $sto_seguendo ? 'following' : '' ?>"
                            id="followBtn"
                            data-id="<?= $profile_id ?>"
                            data-following="<?= $sto_seguendo ? '1' : '0' ?>">
                            <?php if ($sto_seguendo): ?>
                                <i class="bi bi-check2"></i><span class="btn-label"> Seguito</span>
                            <?php else: ?>
                                <i class="bi bi-person-plus"></i><span class="btn-label"> Segui</span>
                            <?php endif; ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats -->
            <div class="prof-stats">
                <div class="prof-stat">
                    <span class="prof-num"><?= $cnt_visti ?></span>
                    <span class="prof-lab">Film visti</span>
                </div>
                <div class="prof-stat">
                    <span class="prof-num"><?= $cnt_log ?></span>
                    <span class="prof-lab">Log</span>
                </div>
                <a href="<?= $profile_base ?>?tab=follower" class="prof-stat" style="text-decoration:none;">
                    <span class="prof-num" id="statFollower"><?= $cnt_follower ?></span>
                    <span class="prof-lab">Follower</span>
                </a>
                <a href="<?= $profile_base ?>?tab=seguiti" class="prof-stat" style="text-decoration:none;">
                    <span class="prof-num"><?= $cnt_seguiti ?></span>
                    <span class="prof-lab">Seguiti</span>
                </a>
            </div>

            <!-- Preferiti -->
            <section class="prof-favs">
                <p class="prof-favs-title">Film preferiti</p>
                <div class="prof-favs-grid">
                    <?php foreach ($pref_slots as $f): ?>
                        <?php if ($f): ?>
                            <?php $poster = $f['Poster_Path'] ? "https://image.tmdb.org/t/p/w300" . $f['Poster_Path'] : null; ?>
                            <a class="fav-slot filled"
                                href="/Pulse/film/<?= $f['TMDB_ID'] ?>-<?= slugify($f['Title']) ?>"
                                title="<?= htmlspecialchars($f['Title']) ?>">
                                <?php if ($poster): ?>
                                    <img src="<?= $poster ?>" alt="<?= htmlspecialchars($f['Title']) ?>">
                                <?php else: ?>
                                    <div class="fav-noimg"><span><?= htmlspecialchars($f['Title']) ?></span></div>
                                <?php endif; ?>
                            </a>
                        <?php elseif ($is_own): ?>
                            <a class="fav-slot empty" href="/Pulse/modifica-profilo">
                                <div class="fav-plus"><i class="bi bi-plus-lg" style="font-size:18px;"></i></div>
                            </a>
                        <?php else: ?>
                            <div class="fav-slot empty" style="pointer-events:none;opacity:.35;">
                                <div class="fav-plus"><i class="bi bi-film" style="font-size:16px;color:var(--muted);"></i></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        </section>

        <!-- ══════════════════════════════════════
             TAB NAV
             ══════════════════════════════════════ -->
        <nav class="prof-tabs">
            <a href="<?= $profile_base ?>?tab=diario"
                class="prof-tab <?= $tab === 'diario'   ? 'active' : '' ?>">Diario</a>
            <a href="<?= $profile_base ?>?tab=watched"
                class="prof-tab <?= $tab === 'watched'  ? 'active' : '' ?>">Film Guardati</a>
            <a href="<?= $profile_base ?>?tab=follower"
                class="prof-tab <?= $tab === 'follower' ? 'active' : '' ?>">Follower<?php if ($cnt_follower): ?> <span class="prof-tab-badge"><?= $cnt_follower ?></span><?php endif; ?></a>
            <a href="<?= $profile_base ?>?tab=seguiti"
                class="prof-tab <?= $tab === 'seguiti'  ? 'active' : '' ?>">Seguiti<?php if ($cnt_seguiti): ?> <span class="prof-tab-badge"><?= $cnt_seguiti ?></span><?php endif; ?></a>
            <a href="<?= $profile_base ?>?tab=liste"
                class="prof-tab <?= $tab === 'liste' ? 'active' : '' ?>">Liste</a>
            <a href="#" class="prof-tab soon">Attività</a>
        </nav>

        <!-- ══════════════════════════════════════
             TAB: DIARIO
             ══════════════════════════════════════ -->
        <?php if ($tab === 'diario'): ?>
            <?php if (!$logs): ?>
                <p class="prof-empty">
                    <?= $is_own
                        ? 'Non hai ancora loggato nessun film.<br><a href="/Pulse/crea" style="color:var(--accent);font-weight:700;">Crea il tuo primo log →</a>'
                        : '@' . htmlspecialchars($profile_user['Username']) . ' non ha ancora log.' ?>
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
                        $anno_film = !empty($log['Release_Date']) ? substr($log['Release_Date'], 0, 4) : '';
                        $voto      = $log['Voto'] ? (float)$log['Voto'] : null;
                        $liked     = !empty($log['Liked']);
                        $hasReview = !empty($log['Recensione']);

                        if ($monthKey !== $lastMonth):
                            $lastMonth = $monthKey;
                    ?>
                            <div class="diary-month-sep"><?= $mesi[$meseNum] ?> <?= $annoNum ?></div>
                        <?php endif; ?>

                        <div class="diary-entry">
                            <!-- Data -->
                            <div class="diary-date">
                                <span class="diary-dow"><?= $giorni[$dowNum] ?></span>
                                <span class="diary-day"><?= $giorno ?></span>
                            </div>
                            <!-- Poster -->
                            <a href="/Pulse/film/<?= $log['tmdb_id'] ?>-<?= slugify($log['Title']) ?>" class="diary-poster-wrap">
                                <img src="<?= $poster ?>" alt="" class="diary-poster">
                            </a>
                            <!-- Info -->
                            <div class="diary-info">
                                <a href="/Pulse/film/<?= $log['tmdb_id'] ?>-<?= slugify($log['Title']) ?>" class="diary-title">
                                    <?= htmlspecialchars($log['Title']) ?>
                                    <?php if ($anno_film): ?><span class="diary-year"><?= $anno_film ?></span><?php endif; ?>
                                </a>
                                <div class="diary-meta-row">
                                    <?= $voto ? starsHTML($voto) : '' ?>
                                    <?= $liked ? '<span class="diary-like">♥</span>' : '' ?>
                                </div>
                                <?php if ($hasReview): ?>
                                    <button class="diary-review-toggle" onclick="toggleReview(this)" data-open="0">
                                        <i class="bi bi-chevron-down"></i><span class="rev-txt"> Recensione</span>
                                    </button>
                                    <div class="diary-review-body">
                                        <?= nl2br(htmlspecialchars($log['Recensione'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <!-- Azioni solo se proprio profilo -->
                            <?php if ($is_own): ?>
                                <div class="diary-actions">
                                    <button class="diary-btn"
                                        onclick='openEditModal(<?= htmlspecialchars(json_encode([
                                                                    "log_id"     => (int)$log["log_id"],
                                                                    "data"       => $log["data_vis"],
                                                                    "voto"       => $voto,
                                                                    "recensione" => $log["Recensione"] ?? "",
                                                                    "liked"      => $liked,
                                                                    "title"      => $log["Title"],
                                                                ]), ENT_QUOTES) ?>)'>
                                        <i class="bi bi-pencil"></i> Modifica
                                    </button>
                                    <button class="diary-btn del"
                                        onclick="askDeleteLog(<?= $log['log_id'] ?>, this, '<?= htmlspecialchars(addslashes($log['Title']), ENT_QUOTES) ?>')">
                                        <i class="bi bi-trash"></i> Elimina
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ══════════════════════════════════════
             TAB: FILM GUARDATI
             ══════════════════════════════════════ -->
        <?php elseif ($tab === 'watched'): ?>
            <?php if (!$watched): ?>
                <p class="prof-empty">Nessun film ancora guardato.</p>
            <?php else: ?>
                <div class="watched-grid" style="padding-top:24px;">
                    <?php foreach ($watched as $w):
                        if (empty($w['Poster_Path'])) continue;
                        $poster = "https://image.tmdb.org/t/p/w300" . $w['Poster_Path'];
                    ?>
                        <a href="/Pulse/film/<?= $w['TMDB_ID'] ?>-<?= slugify($w['Title']) ?>"
                            class="watched-card" title="<?= htmlspecialchars($w['Title']) ?>">
                            <img src="<?= $poster ?>" alt="" class="watched-poster">
                            <?php if ($w['Rating']): ?>
                                <span class="watched-rating">★ <?= number_format((float)$w['Rating'], 1) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

<?php elseif ($tab === 'liste'):
    $stmt_lt = $pdo->prepare("
        SELECT L.IDLista, L.Titolo,
               (SELECT COUNT(*) FROM Lista_Film LF WHERE LF.IDLista = L.IDLista) AS TotaleFilm,
               (SELECT F.Poster_Path FROM Lista_Film LF2 JOIN Film F ON LF2.IDFilm=F.ID
                WHERE LF2.IDLista = L.IDLista ORDER BY LF2.Posizione ASC LIMIT 1) AS Cover
        FROM Lista L WHERE L.IDUtente = ? ORDER BY L.IDLista DESC
    ");
    $stmt_lt->execute([$profile_id]);
    $prof_liste = $stmt_lt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php if (!$prof_liste): ?>
    <p class="prof-empty">Nessuna lista ancora.</p>
<?php else: ?>
<div class="watched-grid" style="padding-top:24px;grid-template-columns:repeat(auto-fill,minmax(120px,1fr))">
    <?php foreach ($prof_liste as $pl):
        $cover = $pl['Cover'] ? "https://image.tmdb.org/t/p/w200".$pl['Cover'] : null; ?>
        <a href="/Pulse/lista?id=<?= $pl['IDLista'] ?>" class="watched-card" title="<?= htmlspecialchars($pl['Titolo']) ?>">
            <?php if ($cover): ?>
                <img src="<?= $cover ?>" alt="" class="watched-poster">
            <?php else: ?>
                <div class="watched-poster" style="background:rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center;font-size:28px;color:var(--muted)">≡</div>
            <?php endif; ?>
            <span class="watched-rating" style="color:var(--text);background:rgba(0,0,0,.8)"><?= $pl['TotaleFilm'] ?> film</span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

            <!-- ══════════════════════════════════════
             TAB: FOLLOWER / SEGUITI (template comune)
             ══════════════════════════════════════ -->
        <?php else:
            $ulist = $tab === 'follower' ? $follower_list : $seguiti_list;
            $empty_msg = $tab === 'follower'
                ? ($is_own ? 'Non hai ancora follower.' : '@' . htmlspecialchars($profile_user['Username']) . ' non ha follower.')
                : ($is_own ? 'Non stai seguendo nessuno.' : '@' . htmlspecialchars($profile_user['Username']) . ' non segue nessuno.');
        ?>
            <?php if (!$ulist): ?>
                <p class="prof-empty"><?= $empty_msg ?></p>
            <?php else: ?>
                <div class="prof-user-list">
                    <?php foreach ($ulist as $u):
                        $uAv    = resolveAvatar($u['Avatar_URL'], $u['Username']);
                        $isMe_u = ((int)$u['ID'] === $my_id);
                    ?>
                        <div class="prof-user-card">
                            <a href="/Pulse/utente/<?= urlencode($u['Username']) ?>" class="prof-user-link">
                                <img src="<?= htmlspecialchars($uAv) ?>" alt="" class="prof-user-avatar">
                                <div class="prof-user-info">
                                    <span class="prof-user-name">
                                        @<?= htmlspecialchars($u['Username']) ?>
                                        <?php if ($isMe_u): ?><span class="rec-me-badge">tu</span><?php endif; ?>
                                    </span>
                                    <?php if ($u['Bio']): ?>
                                        <span class="prof-user-bio"><?= htmlspecialchars(mb_substr($u['Bio'], 0, 80)) ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <?php if (!$isMe_u): ?>
                                <button class="prof-follow-btn <?= $u['io_seguo'] ? 'following' : '' ?>"
                                    data-id="<?= $u['ID'] ?>"
                                    data-following="<?= $u['io_seguo'] ? '1' : '0' ?>">
                                    <?= $u['io_seguo']
                                        ? '<i class="bi bi-person-check-fill"></i> Segui già'
                                        : '<i class="bi bi-person-plus"></i> Segui' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </main>
</div>

<!-- ══════════════════════════════════════════
     MODALI — SOLO SE PROPRIO PROFILO
     ══════════════════════════════════════════ -->
<?php if ($is_own): ?>
    <!-- Modale: modifica log -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-box modal-edit">
            <button class="modal-close-x" onclick="closeModal()"><i class="bi bi-x-lg"></i></button>
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
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <span class="log-star" data-val="<?= $s ?>">★</span>
                        <?php endfor; ?>
                        <span class="star-val-label" id="editStarLabel">Nessun voto</span>
                    </div>
                    <input type="hidden" id="editVoto">
                </div>
                <div class="form-field">
                    <label class="form-label">Mi piace</label>
                    <div class="like-toggle" id="editLikeToggle">
                        <span class="heart-icon">♥</span><span>Mi piace</span>
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
    <!-- Modale: conferma eliminazione -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-box confirm-box">
            <div class="confirm-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <h3 class="confirm-title" id="confirmTitle">Eliminare il log?</h3>
            <p class="confirm-text" id="confirmText">Questa azione non può essere annullata.</p>
            <div class="confirm-actions">
                <button class="btn-cancel" onclick="closeConfirm()">Annulla</button>
                <button class="btn-danger" id="confirmOkBtn"><i class="bi bi-trash"></i> Elimina</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="toast" id="toast"></div>

<script>
    const BACKEND_LOG = '/Pulse/backend/GestioneLog.php';
    const BACKEND_SEG = '/Pulse/backend/gestioneutenti.php';

    function showToast(msg, type = 'success') {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = `toast ${type} show`;
        setTimeout(() => t.classList.remove('show'), 3200);
    }

    // ════════════════════════════════════════════
    //  FIX TOGGLE RECENSIONE
    //  Usa .rev-txt per aggiornare il testo in modo
    //  affidabile indipendentemente dal DOM whitespace
    // ════════════════════════════════════════════
    function toggleReview(btn) {
        const body = btn.nextElementSibling;
        if (!body || !body.classList.contains('diary-review-body')) return;
        const isOpen = body.classList.toggle('open');
        btn.dataset.open = isOpen ? '1' : '0';
        btn.querySelector('i').className = isOpen ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
        const txt = btn.querySelector('.rev-txt');
        if (txt) txt.textContent = isOpen ? ' Chiudi' : ' Recensione';
    }

    // ════════════════════════════════════════════
    //  FOLLOW / UNFOLLOW — header profilo altrui
    // ════════════════════════════════════════════
    <?php if (!$is_own): ?>
        const followBtn = document.getElementById('followBtn');
        if (followBtn) {
            followBtn.addEventListener('click', async () => {
                const uid = parseInt(followBtn.dataset.id);
                const following = followBtn.dataset.following === '1';
                followBtn.disabled = true;
                try {
                    const res = await fetch(BACKEND_SEG, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: following ? 'unfollow' : 'follow',
                            user_id: uid
                        })
                    });
                    const json = await res.json();
                    if (!json.ok) throw new Error(json.error ?? 'Errore');
                    if (following) {
                        followBtn.dataset.following = '0';
                        followBtn.classList.remove('following');
                        followBtn.innerHTML = '<i class="bi bi-person-plus"></i><span class="btn-label"> Segui</span>';
                        const cnt = document.getElementById('statFollower');
                        if (cnt) cnt.textContent = Math.max(0, parseInt(cnt.textContent) - 1);
                        showToast('Non segui più questo utente');
                    } else {
                        followBtn.dataset.following = '1';
                        followBtn.classList.add('following');
                        followBtn.innerHTML = '<i class="bi bi-check2"></i><span class="btn-label"> Seguito</span>';
                        const cnt = document.getElementById('statFollower');
                        if (cnt) cnt.textContent = parseInt(cnt.textContent) + 1;
                        showToast('Ora segui questo utente!');
                    }
                } catch (err) {
                    showToast(err.message, 'error');
                } finally {
                    followBtn.disabled = false;
                }
            });
        }
    <?php endif; ?>

    // ════════════════════════════════════════════
    //  FOLLOW nei tab follower/seguiti
    // ════════════════════════════════════════════
    document.querySelectorAll('.prof-follow-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const uid = parseInt(btn.dataset.id);
            const following = btn.dataset.following === '1';
            btn.disabled = true;
            try {
                const res = await fetch(BACKEND_SEG, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: following ? 'unfollow' : 'follow',
                        user_id: uid
                    })
                });
                const json = await res.json();
                if (!json.ok) throw new Error(json.error ?? 'Errore');
                if (following) {
                    btn.dataset.following = '0';
                    btn.classList.remove('following');
                    btn.innerHTML = '<i class="bi bi-person-plus"></i> Segui';
                    showToast('Non segui più questo utente');
                } else {
                    btn.dataset.following = '1';
                    btn.classList.add('following');
                    btn.innerHTML = '<i class="bi bi-person-check-fill"></i> Segui già';
                    showToast('Ora segui questo utente!');
                }
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                btn.disabled = false;
            }
        });
    });

    // ════════════════════════════════════════════
    //  LOG MODAL — solo se proprio profilo
    // ════════════════════════════════════════════
    <?php if ($is_own): ?>
        class StarPicker {
            constructor(pickerId, hiddenId, labelId) {
                this.picker = document.getElementById(pickerId);
                this.hidden = document.getElementById(hiddenId);
                this.label = document.getElementById(labelId);
                this.val = 0;
                if (!this.picker) return;
                this.stars = [...this.picker.querySelectorAll('.log-star')];
                this.stars.forEach(s => {
                    s.addEventListener('click', e => this._click(s, e));
                    s.addEventListener('mousemove', e => this._hover(s, e));
                    s.addEventListener('mouseleave', () => this._render(this.val));
                });
            }
            _halfVal(s, e) {
                const r = s.getBoundingClientRect();
                return parseFloat(s.dataset.val) - ((e.clientX - r.left) < r.width / 2 ? 0.5 : 0);
            }
            _hover(s, e) {
                this._render(this._halfVal(s, e), true);
            }
            _click(s, e) {
                this.set(this._halfVal(s, e));
            }
            _render(v, hover = false) {
                this.stars.forEach(s => {
                    const sv = parseFloat(s.dataset.val);
                    s.className = 'log-star';
                    if (v >= sv) s.classList.add(hover ? 's-hover-full' : 's-full');
                    else if (v >= sv - .5) s.classList.add(hover ? 's-hover-half' : 's-half');
                });
            }
            set(v) {
                this.val = v;
                this.hidden.value = v || '';
                this.label.textContent = v ? v.toFixed(1) + ' ★' : 'Nessun voto';
                this._render(v);
            }
        }
        const editStar = new StarPicker('editStarPicker', 'editVoto', 'editStarLabel');

        document.getElementById('editLikeToggle').addEventListener('click', function() {
            document.getElementById('editLiked').value = this.classList.toggle('liked') ? '1' : '0';
        });

        function openEditModal(log) {
            document.getElementById('editLogId').value = log.log_id;
            document.getElementById('editData').value = log.data;
            document.getElementById('editRecensione').value = log.recensione;
            document.getElementById('modalFilmTitle').textContent = 'Modifica — ' + log.title;
            editStar.set(log.voto || 0);
            document.getElementById('editLiked').value = log.liked ? '1' : '0';
            document.getElementById('editLikeToggle').classList.toggle('liked', !!log.liked);
            document.getElementById('editModal').classList.add('open');
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('open');
        }

        async function saveEdit() {
            const log_id = +document.getElementById('editLogId').value;
            if (!log_id) {
                showToast('Errore: ID log mancante', 'error');
                return;
            }
            const payload = {
                action: 'modifica_log',
                log_id,
                data: document.getElementById('editData').value,
                voto: parseFloat(document.getElementById('editVoto').value) || null,
                recensione: document.getElementById('editRecensione').value.trim(),
                liked: document.getElementById('editLiked').value === '1',
            };
            const btn = document.getElementById('btnSalvaEdit');
            btn.disabled = true;
            try {
                const res = await fetch(BACKEND_LOG, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (json.ok) {
                    showToast('Log aggiornato!');
                    closeModal();
                    setTimeout(() => location.reload(), 800);
                } else showToast('Errore: ' + (json.error ?? 'sconosciuto'), 'error');
            } catch {
                showToast('Errore di rete', 'error');
            } finally {
                btn.disabled = false;
            }
        }

        // Chiudi modale con Escape / click sfondo
        ['editModal', 'confirmModal'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('click', e => {
                if (e.target === el) {
                    closeModal();
                    closeConfirm();
                }
            });
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeModal();
                closeConfirm();
            }
        });

        // Confirm modal
        let _confirmCb = null;

        function showConfirm(title, text, onOk) {
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmText').textContent = text;
            _confirmCb = onOk;
            document.getElementById('confirmModal').classList.add('open');
        }

        function closeConfirm() {
            document.getElementById('confirmModal').classList.remove('open');
            _confirmCb = null;
        }
        document.getElementById('confirmOkBtn').addEventListener('click', () => {
            const cb = _confirmCb;
            closeConfirm();
            cb?.();
        });

        // Elimina log
        function askDeleteLog(id, btn, title) {
            showConfirm('Eliminare il log?',
                `Stai per eliminare il log di "${title}". Questa azione non può essere annullata.`,
                () => doDeleteLog(id, btn));
        }
        async function doDeleteLog(id, btn) {
            try {
                const res = await fetch(BACKEND_LOG, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'elimina_log',
                        log_id: id
                    })
                });
                const json = await res.json();
                if (json.ok) {
                    const row = btn.closest('.diary-entry');
                    row.style.transition = 'opacity .3s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 310);
                    showToast('Log eliminato.');
                } else showToast('Errore: ' + (json.error ?? 'sconosciuto'), 'error');
            } catch {
                showToast('Errore di rete', 'error');
            }
        }
    <?php endif; ?>
</script>