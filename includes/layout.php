<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function layoutHeader(string $title): void
{
    $user = currentUser();
    sendSecurityHeaders();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= h($title) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    </head>
    <body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="/index.php"><i class="fa-solid fa-store"></i> Vending Machine</a>
            <div class="ms-auto text-white d-flex gap-2 align-items-center">
                <?php if ($user): ?>
                    <span class="small">Hi, <?= h($user['username']) ?> (<?= h($user['role']) ?>)</span>
                    <?php if (isAdmin()): ?>
                        <a class="btn btn-sm btn-warning" href="/admin.php"><i class="fa-solid fa-gear"></i> Admin</a>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-outline-light" href="/logout.php">Logout</a>
                <?php else: ?>
                    <a class="btn btn-sm btn-outline-light" href="/login.php">Login</a>
                    <a class="btn btn-sm btn-success" href="/register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="container pb-5">
    <?php
}

function layoutFooter(): void
{
    ?>
    </main>
    </body>
    </html>
    <?php
}
