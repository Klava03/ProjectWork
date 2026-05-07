<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: /Pulse/login");
    exit();
}

function slugify(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9\s\-]/u', '', $s);
    $s = preg_replace('/[\s\-]+/', '-', $s);
    return trim($s, '-');
}

require 'Database.php';
$pdo = getConnection();

$apiKey = "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJiY2U5ZWZmYjRjMDA5ZTNmNGZmZTM1N2QzOTlhMTk4NSIsIm5iZiI6MTc2OTc4NDc2MC43NzcsInN1YiI6IjY5N2NjNWI4MWVhOGNmMGRlZTI2YWVmNSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.cPNGTwdDeQD6TGYY2Shn-i3Y0i-OjMSey9KPpdrpBAE";

// Iniettati dal router
$tab = $_GET['tab'] ?? 'film';
$q   = trim($_GET['q'] ?? '');

// URI base per ogni tab
$tabUri = [
    'film'      => '/Pulse/cerca',
    'cast'      => '/Pulse/cerca/cast',
    'utenti'    => '/Pulse/cerca/utenti',
    'community' => '/Pulse/cerca/community',
    'liste'     => '/Pulse/cerca/liste',
];

$tabs = [
    'film'      => 'Film',
    'cast'      => 'Cast & Crew',
    'utenti'    => 'Utenti',
    'community' => 'Community',
    'liste'     => 'Liste',
];

// ── Helper TMDB ──────────────────────────────────────────────────
function tmdb_get(string $endpoint, string $apiKey): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => "https://api.themoviedb.org/3/" . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $apiKey",
            "accept: application/json",
        ],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

// ── Risultati ────────────────────────────────────────────────────
$risultati = [];
$errore    = '';

if ($q !== '') {
    switch ($tab) {

        case 'film':
            $data = tmdb_get("search/movie?query=" . urlencode($q) . "&language=it-IT", $apiKey);
            $risultati = $data['results'] ?? [];
            break;

        case 'cast':
            $data = tmdb_get("search/person?query=" . urlencode($q) . "&language=it-IT", $apiKey);
            $risultati = $data['results'] ?? [];
            break;

        case 'utenti':
            try {
                $stmt = $pdo->prepare(
                    "SELECT ID, Username, Bio, Avatar_URL FROM Utente
                     WHERE Username LIKE :q LIMIT 20"
                );
                $stmt->execute(['q' => "%$q%"]);
                $risultati = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $errore = "Errore ricerca utenti.";
                error_log($e->getMessage());
            }
            break;

        case 'community':
            try {
                $stmt = $pdo->prepare(
                    "SELECT C.ID, C.Nome, C.Descrizione, G.Name as Genere
                     FROM Community C JOIN Genere G ON C.IDGenere = G.ID
                     WHERE C.Nome LIKE :q LIMIT 20"
                );
                $stmt->execute(['q' => "%$q%"]);
                $risultati = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $errore = "Errore ricerca community.";
                error_log($e->getMessage());
            }
            break;

        case 'liste':
            try {
                $stmt = $pdo->prepare(
                    "SELECT L.IDLista, L.Titolo, L.Descrizione,
                            U.Username as Autore,
                            COUNT(LF.IDFilm) as TotaleFilm,
                            (SELECT F.Poster_Path FROM Lista_Film LF2
                             JOIN Film F ON LF2.IDFilm = F.ID
                             WHERE LF2.IDLista = L.IDLista LIMIT 1) as AnteprimaPoster
                     FROM Lista L
                     JOIN Utente U ON L.IDUtente = U.ID
                     LEFT JOIN Lista_Film LF ON LF.IDLista = L.IDLista
                     WHERE L.Titolo LIKE :q
                     GROUP BY L.IDLista LIMIT 20"
                );
                $stmt->execute(['q' => "%$q%"]);
                $risultati = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $errore = "Errore ricerca liste.";
                error_log($e->getMessage());
            }
            break;
    }
}

$noImg = "https://s.ltrbxd.com/static/img/empty-poster-230-nQeuntFa.png";
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pulse • Cerca<?= $q ? ' — ' . htmlspecialchars($q) : '' ?></title>
    <link rel="stylesheet" href="CSS/Cerca.css">
</head>

<body>

    <div class="app">
        <?php require 'aside.php'; ?>

        <main class="center">

            <section class="search-header">

                <!-- TABS: cambiano tab e portano la query nell'URI -->
                <div class="search-tabs">
                    <?php foreach ($tabs as $key => $label):
                        $href = $tabUri[$key] . ($q ? '/' . rawurlencode($q) : '');
                    ?>
                        <a href="<?= $href ?>" class="tab <?= $tab === $key ? 'active' : '' ?>">
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <h2><?php
                    $titoli = [
                        'film'      => 'Cerca un film',
                        'cast'      => 'Cerca attori e registi',
                        'utenti'    => 'Trova persone',
                        'community' => 'Esplora le community',
                        'liste'     => 'Cerca liste',
                    ];
                    echo $titoli[$tab] ?? 'Cerca';
                    ?></h2>

                <!--
                Il form NON usa action+GET classico.
                JS intercetta il submit e naviga verso
                /Pulse/cerca/<query>  oppure  /Pulse/cerca/cast/<query>
            -->
                <form class="search-form" id="searchForm">
                    <input
                        type="text"
                        id="searchInput"
                        class="search-input"
                        placeholder="<?php
                                        $ph = [
                                            'film'      => 'Titolo del film...',
                                            'cast'      => 'Nome attore o regista...',
                                            'utenti'    => 'Cerca username...',
                                            'community' => 'Nome community...',
                                            'liste'     => 'Titolo lista...',
                                        ];
                                        echo $ph[$tab] ?? 'Cerca...';
                                        ?>"
                        value="<?= htmlspecialchars($q) ?>"
                        autofocus>
                    <button type="submit" class="search-btn">Cerca</button>
                </form>
            </section>

            <!-- RISULTATI -->
            <?php if ($errore): ?>
                <p class="no-results"><?= htmlspecialchars($errore) ?></p>

            <?php elseif ($q === ''): ?>
                <p class="no-results">Inizia a digitare per cercare.</p>

            <?php elseif (empty($risultati)): ?>
                <p class="no-results">Nessun risultato per "<?= htmlspecialchars($q) ?>".</p>

            <?php else: ?>

                <?php if ($tab === 'film'): ?>
                    <div class="grid-risultati">
                        <?php foreach ($risultati as $f):
                            $poster = $f['poster_path']
                                ? "https://image.tmdb.org/t/p/w500" . $f['poster_path']
                                : $noImg;
                            $anno = !empty($f['release_date']) ? substr($f['release_date'], 0, 4) : 'N.D.';
                        ?>
                            <div class="film-card"
                                onclick="location.href='/Pulse/film/<?= $f['id'] ?>-<?= slugify($f['title'] ?? $f['original_title'] ?? '') ?>'">
                                <img src="<?= $poster ?>" alt="<?= htmlspecialchars($f['title']) ?>">
                                <div class="film-info">
                                    <strong><?= htmlspecialchars($f['title']) ?></strong>
                                    <small><?= $anno ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($tab === 'cast'): ?>
                    <div class="cast-list">
                        <?php foreach ($risultati as $p):
                            $foto = $p['profile_path']
                                ? "https://image.tmdb.org/t/p/w185" . $p['profile_path']
                                : "https://ui-avatars.com/api/?name=" . urlencode($p['name']) . "&background=1e2535&color=9aa3b2&size=80";
                            $ruoli = array_map(
                                fn($r) => $r['title'] ?? $r['name'] ?? '',
                                array_slice($p['known_for'] ?? [], 0, 2)
                            );
                        ?>
                            <div class="cast-card"
                                onclick="location.href='/Pulse/persona/<?= $p['id'] ?>-<?= slugify($p['name'] ?? '') ?>'">
                                <img src="<?= $foto ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="cast-avatar">
                                <div class="cast-info">
                                    <span class="cast-name"><?= htmlspecialchars($p['name']) ?></span>
                                    <span class="cast-dept"><?= htmlspecialchars($p['known_for_department'] ?? '') ?></span>
                                    <?php if ($ruoli): ?>
                                        <span class="cast-known">Noto per: <?= htmlspecialchars(implode(', ', $ruoli)) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($tab === 'utenti'): ?>
                    <div class="user-list">
                        <?php foreach ($risultati as $user):
                            $avatar = $user['Avatar_URL']
                                ?? "https://ui-avatars.com/api/?name=" . urlencode($user['Username']) . "&background=8b5cf6&color=fff";
                        ?>
                            <div class="user-card"
                                onclick="location.href='/Pulse/utente/<?= htmlspecialchars(urlencode($user['Username'])) ?>'"
                                style="cursor:pointer">
                                <img src="<?= $avatar ?>" alt="Avatar" class="user-avatar">
                                <div class="user-info">
                                    <span class="username">@<?= htmlspecialchars($user['Username']) ?></span>
                                    <p class="bio"><?= htmlspecialchars($user['Bio'] ?? 'Nessuna bio') ?></p>
                                </div>
                                <button class="follow-btn" data-id="<?= $user['ID'] ?>" onclick="handleFollow(this)">
                                    Segui
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($tab === 'community'): ?>
                    <div class="community-list">
                        <?php foreach ($risultati as $c): ?>
                            <div class="community-card" onclick="location.href='/Pulse/community?id=<?= $c['ID'] ?>'">
                                <div class="community-icon"><?= mb_strtoupper(mb_substr($c['Nome'], 0, 1)) ?></div>
                                <div class="community-info">
                                    <span class="community-nome"><?= htmlspecialchars($c['Nome']) ?></span>
                                    <span class="community-genere"><?= htmlspecialchars($c['Genere']) ?></span>
                                    <p class="community-desc"><?= htmlspecialchars($c['Descrizione'] ?? '') ?></p>
                                </div>
                                <button class="follow-btn">Unisciti</button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($tab === 'liste'): ?>
                    <div class="liste-list">
                        <?php foreach ($risultati as $lista):
                            $cover = $lista['AnteprimaPoster']
                                ? "https://image.tmdb.org/t/p/w200" . $lista['AnteprimaPoster']
                                : $noImg;
                        ?>
                            <div class="lista-card" onclick="location.href='/Pulse/lista?id=<?= $lista['IDLista'] ?>'">
                                <img src="<?= $cover ?>" alt="Cover" class="lista-cover">
                                <div class="lista-info">
                                    <span class="lista-titolo"><?= htmlspecialchars($lista['Titolo']) ?></span>
                                    <span class="lista-meta">di @<?= htmlspecialchars($lista['Autore']) ?> · <?= $lista['TotaleFilm'] ?> film</span>
                                    <?php if ($lista['Descrizione']): ?>
                                        <p class="lista-desc"><?= htmlspecialchars($lista['Descrizione']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </main>
    </div>

    <script>
        // URI base del tab corrente (iniettato da PHP)
        const tabBaseUri = <?= json_encode($tabUri[$tab]) ?>;

        // Submit → naviga verso /cerca/<query> o /cerca/cast/<query>
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const q = document.getElementById('searchInput').value.trim();
            if (!q) return;
            window.location.href = tabBaseUri + '/' + encodeURIComponent(q);
        });

        // Cambio tab: porta la query corrente nel nuovo URI
        // (già gestito dagli href PHP sopra)

        // Follow toggle
        function handleFollow(btn) {
            if (btn.classList.contains('following')) {
                btn.textContent = 'Segui';
                btn.classList.remove('following');
            } else {
                btn.textContent = 'Seguito';
                btn.classList.add('following');
            }
        }
    </script>

</body>

</html>