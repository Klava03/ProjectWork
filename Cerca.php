eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJiY2U5ZWZmYjRjMDA5ZTNmNGZmZTM1N2QzOTlhMTk4NSIsIm5iZiI6MTc2OTc4NDc2MC43NzcsInN1YiI6IjY5N2NjNWI4MWVhOGNmMGRlZTI2YWVmNSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.cPNGTwdDeQD6TGYY2Shn-i3Y0i-OjMSey9KPpdrpBAE

<?php
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: Login.php");
        exit();
    }

    $apiKey = "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJiY2U5ZWZmYjRjMDA5ZTNmNGZmZTM1N2QzOTlhMTk4NSIsIm5iZiI6MTc2OTc4NDc2MC43NzcsInN1YiI6IjY5N2NjNWI4MWVhOGNmMGRlZTI2YWVmNSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.cPNGTwdDeQD6TGYY2Shn-i3Y0i-OjMSey9KPpdrpBAE"; 

    $risultatiHTML = "";

    if (isset($_GET['q']) && !empty(trim($_GET['q']))) {
        $q = urlencode($_GET['q']);
        $url = "https://api.themoviedb.org/3/search/movie?query=$q&language=it-IT";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $apiKey",
            "accept: application/json"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);

        if (!empty($data['results'])) {
            foreach ($data['results'] as $f) {
                $poster = $f['poster_path'] 
                    ? "https://image.tmdb.org/t/p/w500" . $f['poster_path'] 
                    : "https://via.placeholder.com/500x750?text=No+Poster";
                $anno = !empty($f['release_date']) ? substr($f['release_date'], 0, 4) : "N.D.";
                
                $risultatiHTML .= "
                <div class='film-card' onclick=\"location.href='crea_log.php?tmdb_id={$f['id']}'\">
                    <img src='$poster' alt='Poster'>
                    <div class='film-info'>
                        <strong>" . htmlspecialchars($f['title']) . "</strong>
                        <small>$anno</small>
                    </div>
                </div>";
            }
        } else {
            $risultatiHTML = "<p class='no-results'>Nessun film trovato per questa ricerca.</p>";
        }
    }
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Pulse • Cerca</title>
    <link rel="stylesheet" href="../CSS/Home.css">
    <link rel="stylesheet" href="../CSS/Cerca.css">
</head>
<body>

<div class="app">
    <aside class="left">
        <div class="brand">
            <div class="logo"></div>
            <h1>Pulse</h1>
        </div>
        <nav class="nav">
            <a href="home.php"><span class="ico">⌂</span><span class="label">Home</span></a>
            <a class="active" href="cerca.php"><span class="ico">⌕</span><span class="label">Cerca</span></a>
            <a href="community.php"><span class="ico">👥</span><span class="label">Community</span></a>
            <a href="crea.php"><span class="ico">＋</span><span class="label">Crea</span></a>
            <a href="notifiche.php"><span class="ico">🔔</span><span class="label">Notifiche</span></a>
            <a href="liste.php"><span class="ico">≡</span><span class="label">Liste</span></a>
            <a href="recensioni.php"><span class="ico">✍</span><span class="label">Recensioni</span></a>
            <a href="profilo.php"><span class="ico">☺</span><span class="label">Profilo</span></a>
        </nav>
    </aside>

    <main class="center">
        <section class="search-header">
            <div class="search-tabs">
                <a href="Cerca.php" class="tab active">Film</a>
                <a href="CercaUtenti.php" class="tab">Utenti</a>
            </div>

            <h2>Cerca un film</h2>
            <form action="cerca.php" method="GET" class="search-form">
                <input type="text" name="q" class="search-input" 
                    placeholder="Titolo del film..." 
                    value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" required>
                <button type="submit" class="search-btn">Cerca</button>
            </form>
        </section>

        <div class="grid-risultati">
            <?= $risultatiHTML ?>
        </div>
    </main>
</div>

</body>
</html>