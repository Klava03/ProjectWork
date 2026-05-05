<?php
session_start();

// ── URI parsing ──────────────────────────────────────────────────
// PATH_INFO è disponibile grazie alla RewriteRule index.php/$1
// Fallback su REQUEST_URI per ambienti che non lo impostano
if (!empty($_SERVER['PATH_INFO'])) {
    $uri = $_SERVER['PATH_INFO'];
} else {
    $uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
}

$uri      = trim($uri, '/');
$segments = $uri !== '' ? explode('/', $uri) : [];

$page = $segments[0] ?? 'home';
$sub  = $segments[1] ?? null;   // es. "cast", "utenti", ecc.
$id   = $segments[2] ?? null;

// ── Rotte pubbliche ──────────────────────────────────────────────
$publicRoutes = ['login', 'register'];

if (!isset($_SESSION['user_id']) && !in_array($page, $publicRoutes)) {
    header('Location: /Pulse/login');
    exit();
}

// ── Sotto-rotte /cerca/* ─────────────────────────────────────────
// /cerca            → tab=film   (default)
// /cerca/cast       → tab=cast
// /cerca/utenti     → tab=utenti
// /cerca/community  → tab=community
// /cerca/liste      → tab=liste
$cercaTabs = ['cast', 'utenti', 'community', 'liste'];

if ($page === 'cerca') {
    // Caso: /cerca
    if ($sub === null) {
        $_GET['tab'] = 'film';
    }
    // Caso: /cerca/<query>
    elseif (!in_array($sub, $cercaTabs)) {
        $_GET['tab'] = 'film';
        $_GET['q']   = urldecode($sub);
    }
    // Caso: /cerca/cast oppure /cerca/cast/<query>
    else {
        $_GET['tab'] = $sub;
        if ($id !== null) {
            $_GET['q'] = urldecode($id);
        }
    }
}

// ── Tabella delle rotte ──────────────────────────────────────────
$routes = [
    'home'       => 'Home.php',
    'film'       => 'Film.php',
    'profilo'    => 'Profilo.php',
    'cerca'      => 'Cerca.php',        // gestisce tutti i tab
    'login'      => 'Login.php',
    'register'   => 'Registrazione.php',
    'logout'     => 'Logout.php',
    'community'  => 'Community.php',
    'crea'       => 'Crea.php',
    'notifiche'  => 'Notifiche.php',
    'liste'      => 'Liste.php',
    'recensioni' => 'Recensioni.php',
];

if (!isset($routes[$page])) {
    http_response_code(404);
    $pageFile = '404.php';
} else {
    $pageFile = $routes[$page];
}
?>
<!doctype html>
<html lang="it">
<?php require 'pages/head.php'; ?>

<body>
    <?php require 'pages/' . $pageFile; ?>
</body>

</html>