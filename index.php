<?php
session_start();

// ── URI parsing ──────────────────────────────────────────────────
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
$sub  = $segments[1] ?? null;
$id   = $segments[2] ?? null;

// ── Rotte pubbliche ──────────────────────────────────────────────
$publicRoutes = ['login', 'register'];

if (!isset($_SESSION['user_id']) && !in_array($page, $publicRoutes)) {
    header('Location: /Pulse/login');
    exit();
}

// ── Sotto-rotte /cerca/* ─────────────────────────────────────────
$cercaTabs = ['cast', 'utenti', 'community', 'liste'];

if ($page === 'cerca') {
    if ($sub === null) {
        $_GET['tab'] = 'film';
    } elseif (!in_array($sub, $cercaTabs)) {
        $_GET['tab'] = 'film';
        $_GET['q']   = urldecode($sub);
    } else {
        $_GET['tab'] = $sub;
        if ($id !== null) $_GET['q'] = urldecode($id);
    }
}

// ── crea_log → Crea.php con tab log + tmdb_id da query string ────
// Esempio: /Pulse/crea_log?tmdb_id=550
// tmdb_id è già in $_GET, Crea.php lo legge direttamente

// ── Tabella delle rotte ──────────────────────────────────────────
$routes = [
    // Core
    'home'       => 'Home.php',
    'profilo'    => 'Profilo.php',
    'cerca'      => 'Cerca.php',
    'login'      => 'Login.php',
    'register'   => 'Registrazione.php',
    'logout'     => 'Logout.php',
    'community'  => 'Community.php',
    'notifiche'  => 'Notifiche.php',
    'liste'      => 'Liste.php',
    'recensioni' => 'Recensioni.php',

    // Crea
    'crea'       => 'Crea.php',       // /Pulse/crea
    'crea_log'   => 'Crea.php',       // /Pulse/crea_log?tmdb_id=XXX

    // Pagine dettaglio
    'film'       => 'Film.php',       // /Pulse/film/550-fight-club
    'persona'    => 'Persona.php',    // /Pulse/persona/287-brad-pitt
    'utente'     => 'UtentePubblico.php', // /Pulse/utente/mario
];

// ── CSS per pagina (iniettato in head.php) ───────────────────────
$pageCSS = match($page) {
    'film'               => 'CSS/Film.css',
    'persona'            => 'CSS/Persona.css',
    'utente'             => 'CSS/UtentePubblico.css',
    'crea', 'crea_log'   => 'CSS/Crea.css',
    'profilo'            => 'CSS/Profilo.css',
    default              => null,
};

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