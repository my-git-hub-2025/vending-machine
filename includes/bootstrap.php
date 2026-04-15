<?php

declare(strict_types=1);

session_start();

const DATA_DIR = __DIR__ . '/../data';
const USERS_FILE = DATA_DIR . '/users.txt';
const CONFIG_FILE = DATA_DIR . '/machine_config.txt';
const PRODUCTS_FILE = DATA_DIR . '/products.txt';
const ASSIGNMENTS_FILE = DATA_DIR . '/slot_assignments.txt';

initializeDataFiles();

function initializeDataFiles(): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0777, true);
    }

    if (!file_exists(USERS_FILE)) {
        writeJsonFile(USERS_FILE, []);
    }

    if (!file_exists(CONFIG_FILE)) {
        writeJsonFile(CONFIG_FILE, [
            'rows' => 4,
            'columns' => 4,
            'slots_per_column' => [1 => 4, 2 => 4, 3 => 4, 4 => 4],
        ]);
    }

    if (!file_exists(PRODUCTS_FILE)) {
        writeJsonFile(PRODUCTS_FILE, []);
    }

    if (!file_exists(ASSIGNMENTS_FILE)) {
        writeJsonFile(ASSIGNMENTS_FILE, []);
    }
}

function readJsonFile(string $path, mixed $default): mixed
{
    $content = @file_get_contents($path);
    if ($content === false || trim($content) === '') {
        return $default;
    }

    $decoded = json_decode($content, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
}

function writeJsonFile(string $path, mixed $data): void
{
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function isAdmin(): bool
{
    return isLoggedIn() && (currentUser()['role'] ?? '') === 'admin';
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        header('Location: /index.php');
        exit;
    }
}

function getUsers(): array
{
    return readJsonFile(USERS_FILE, []);
}

function saveUsers(array $users): void
{
    writeJsonFile(USERS_FILE, $users);
}

function getMachineConfig(): array
{
    $config = readJsonFile(CONFIG_FILE, []);
    $rows = max(1, (int)($config['rows'] ?? 4));
    $columns = max(1, (int)($config['columns'] ?? 4));
    $slots = $config['slots_per_column'] ?? [];

    $normalizedSlots = [];
    for ($i = 1; $i <= $columns; $i++) {
        $normalizedSlots[$i] = max(1, (int)($slots[$i] ?? $rows));
    }

    return [
        'rows' => $rows,
        'columns' => $columns,
        'slots_per_column' => $normalizedSlots,
    ];
}

function saveMachineConfig(int $rows, int $columns, array $slotsPerColumn): void
{
    $normalized = [];
    for ($i = 1; $i <= $columns; $i++) {
        $normalized[$i] = max(1, (int)($slotsPerColumn[$i] ?? $rows));
    }

    writeJsonFile(CONFIG_FILE, [
        'rows' => max(1, $rows),
        'columns' => max(1, $columns),
        'slots_per_column' => $normalized,
    ]);
}

function getProducts(): array
{
    return readJsonFile(PRODUCTS_FILE, []);
}

function saveProducts(array $products): void
{
    writeJsonFile(PRODUCTS_FILE, $products);
}

function getAssignments(): array
{
    return readJsonFile(ASSIGNMENTS_FILE, []);
}

function saveAssignments(array $assignments): void
{
    writeJsonFile(ASSIGNMENTS_FILE, $assignments);
}

function findProductById(int $id): ?array
{
    foreach (getProducts() as $product) {
        if ((int)($product['id'] ?? 0) === $id) {
            return $product;
        }
    }
    return null;
}

function nextId(array $rows): int
{
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int)($row['id'] ?? 0));
    }
    return $max + 1;
}
