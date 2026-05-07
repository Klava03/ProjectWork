<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pulse | <?= ucfirst($page) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <base href="/Pulse/">
    <link rel="stylesheet" href="CSS/Home.css">
    <?php if (!empty($pageCSS)): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($pageCSS) ?>">
    <?php endif; ?>
</head>