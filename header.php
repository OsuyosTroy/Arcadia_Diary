<?php
$pageTitle = $pageTitle ?? 'Arcadia Vault';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="styles.css">
    <script src="app.js" defer></script>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="collection.php" aria-label="Arcadia Vault home">
            <div class="brand-logo" aria-hidden="true">AV</div>
            <div class="brand-copy">
                <span class="brand-tag">Game Collection Diary</span>
                <div class="brand-name">Arcadia Vault</div>
            </div>
        </a>
        <div class="topbar-gap"></div>
        <nav class="nav" aria-label="Main navigation">
            <a class="nav-a<?= $activePage === 'collection' ? ' active' : '' ?>" href="collection.php">Vaults</a>
            <a class="btn btn-neon btn-sm" href="create.php">+ New Vault</a>
        </nav>
    </div>
</header>