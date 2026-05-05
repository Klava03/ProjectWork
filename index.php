<?php
session_start();

$uri = $_SERVER['PATH_INFO'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = dirname($_SERVER['SCRIPT_NAME']);
if ($base !== '/' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
}
$uri = trim($uri, '/');
$segments = $uri ? explode('/', $uri) : [];


$page = $segments[0] ?? 'home';
$id   = $segments[1] ?? null;
$publicRoutes = ['login', 'register'];

if (!isset($_SESSION['user_id']) && !in_array($page, $publicRoutes)) {
    $page = 'login';
}


$routes = [
    'home' => 'Home.php',
    'film' => 'film.php',
    'profilo' => 'profilo.php',
    'cerca' => 'cerca.php',
    'login' => 'login.php',
    'register' => 'registrazione.php',
    'logout' => 'Logout.php',
    'community' => 'community.php',
    'crea' => 'crea.php',
    'notifiche' => 'notifiche.php',
    'liste' => 'liste.php',
    'recensioni' => 'recensioni.php'
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