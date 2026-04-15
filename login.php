<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (isRateLimited('login', 5, 900)) {
        $error = 'Too many failed login attempts. Please try again later.';
    } else {
        foreach (getUsers() as $user) {
            if (strcasecmp((string)$user['username'], $username) === 0 && password_verify($password, (string)$user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                ];
                clearRateLimit('login');
                header('Location: /index.php');
                exit;
            }
        }

        registerRateLimitFailure('login');
        $error = 'Invalid username or password.';
    }
}

layoutHeader('Login');
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3"><i class="fa-solid fa-right-to-bracket"></i> Login</h1>
                <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input class="form-control" type="password" name="password" required>
                    </div>
                    <button class="btn btn-primary w-100">Sign in</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php layoutFooter(); ?>
