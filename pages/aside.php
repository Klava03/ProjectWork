<?php 

// usa la variabile del router (definita in index.php)
global $page;

$pagine = [
    "home" => [
        "label" => "Home",
        "icon" => "bi-house"
    ],
    "cerca" => [
        "label" => "Cerca",
        "icon" => "bi-search"
    ],
    "community" => [
        "label" => "Community",
        "icon" => "bi-people"
    ],
    "crea" => [
        "label" => "Crea",
        "icon" => "bi-plus-circle"
    ],
    "notifiche" => [
        "label" => "Notifiche",
        "icon" => "bi-bell"
    ],
    "liste" => [
        "label" => "Liste",
        "icon" => "bi-list"
    ],
    "recensioni" => [
        "label" => "Recensioni",
        "icon" => "bi-pencil"
    ],
    "profilo" => [
        "label" => "Profilo",
        "icon" => "bi-person"
    ]
];

?>

<aside class="left">
    <div class="brand">
        <div class="logo"></div>
        <h1>Pulse</h1>
    </div>

    <nav class="nav">
        <?php foreach ($pagine as $route => $info): ?>
            
            <a 
                href="/Pulse/<?= $route ?>" 
                class="<?= ($page === $route) ? 'active' : '' ?>"
            >
                <i class="ico bi <?= $info['icon'] ?>"></i>
                <span class="label"><?= $info['label'] ?></span>
            </a>

        <?php endforeach; ?>
    </nav>

    <div style="flex:1"></div>

    <div class="me">
        <img class="avatar" src="<?= htmlspecialchars($_SESSION['avatar_url']) ?>" alt="Avatar">
        <div class="meta">
            <strong>@<?= htmlspecialchars($_SESSION['username']) ?></strong>
            <a href="/Pulse/logout" style="font-size: 11px; color: var(--danger)">Log out</a>
        </div>
    </div>
</aside>