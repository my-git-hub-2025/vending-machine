<?php

declare(strict_types=1);

session_set_cookie_params([
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

const DATA_DIR = __DIR__ . '/../data';
const USERS_FILE = DATA_DIR . '/users.txt';
const CONFIG_FILE = DATA_DIR . '/machine_config.txt';
const PRODUCTS_FILE = DATA_DIR . '/products.txt';
const ASSIGNMENTS_FILE = DATA_DIR . '/slot_assignments.txt';
const AUTH_RATE_LIMIT_WINDOW_SECONDS = 900;
const AUTH_RATE_LIMIT_MAX_ATTEMPTS = 5;

initializeDataFiles();

function initializeDataFiles(): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0750, true);
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
    $encoded = json_encode($data, JSON_PRETTY_PRINT);
    if ($encoded === false) {
        throw new RuntimeException('Failed to encode JSON data.');
    }

    $result = file_put_contents($path, $encoded, LOCK_EX);
    if ($result === false) {
        throw new RuntimeException('Failed to write data file: ' . $path);
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function sendSecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

function getCsrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function requireValidCsrfToken(): void
{
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    $providedToken = (string)($_POST['csrf_token'] ?? '');

    if ($sessionToken === '' || $providedToken === '' || !hash_equals($sessionToken, $providedToken)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
}

function isRateLimited(string $key, int $maxAttempts, int $windowSeconds): bool
{
    $store = $_SESSION['rate_limit'][$key] ?? null;
    if (!is_array($store)) {
        return false;
    }

    $windowStart = (int)($store['window_start'] ?? 0);
    $count = (int)($store['count'] ?? 0);
    if ((time() - $windowStart) > $windowSeconds) {
        return false;
    }

    return $count >= $maxAttempts;
}

function registerRateLimitFailure(string $key, int $windowSeconds): void
{
    $existing = $_SESSION['rate_limit'][$key] ?? null;
    if (!is_array($existing) || (time() - (int)($existing['window_start'] ?? 0)) > $windowSeconds) {
        $_SESSION['rate_limit'][$key] = [
            'window_start' => time(),
            'count' => 1,
        ];
        return;
    }

    $_SESSION['rate_limit'][$key]['count'] = (int)($_SESSION['rate_limit'][$key]['count'] ?? 0) + 1;
}

function clearRateLimit(string $key): void
{
    unset($_SESSION['rate_limit'][$key]);
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

function getAssignedQuantitiesByProduct(array $assignments): array
{
    $totals = [];
    foreach ($assignments as $assignment) {
        $productId = (int)($assignment['product_id'] ?? 0);
        $quantity = max(0, (int)($assignment['quantity'] ?? 0));
        if ($productId <= 0) {
            continue;
        }
        $totals[$productId] = ($totals[$productId] ?? 0) + $quantity;
    }
    return $totals;
}

function nextId(array $rows): int
{
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int)($row['id'] ?? 0));
    }
    return $max + 1;
}
