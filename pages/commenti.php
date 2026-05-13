<?php
// pages/_commenti.php
// Include riutilizzabile per la sezione commenti.
// Variabili attese:
//   $tipo (string 'log'|'post')   - tipo di entità
//   $id   (int)                    - ID dell'entità
//   $my_username (string opz.)     - username utente loggato (per @mention futuri)
//   $my_avatar (string opz.)       - URL avatar dell'utente loggato

$_avatarMio = $my_avatar ?? (function() {
    $v = $_SESSION['avatar_url'] ?? null;
    if (!$v) return "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['username'] ?? 'U') . "&background=8b5cf6&color=fff&size=80";
    if (str_starts_with($v, 'http')) return $v;
    return '/Pulse/IMG/avatars/' . $v;
})();
?>

<section class="cm-section" data-target-tipo="<?= htmlspecialchars($tipo) ?>" data-target-id="<?= (int)$id ?>">

    <!-- Composer nuovo commento -->
    <div class="cm-composer">
        <img src="<?= htmlspecialchars($_avatarMio) ?>" alt="" class="cm-avatar">
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

    <!-- Lista commenti -->
    <div class="cm-list">
        <div class="cm-loading">
            <i class="bi bi-arrow-repeat cm-spin"></i> Caricamento commenti…
        </div>
    </div>
</section>