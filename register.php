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

    if (isRateLimited('register', AUTH_RATE_LIMIT_MAX_ATTEMPTS, AUTH_RATE_LIMIT_WINDOW_SECONDS)) {
        $error = 'Too many registration attempts. Please try again later.';
    } elseif ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        $users = getUsers();
        foreach ($users as $user) {
            if (strcasecmp((string)$user['username'], $username) === 0) {
                $error = 'Username already exists.';
                break;
            }
        }

        if ($error === '') {
            $role = count($users) === 0 ? 'admin' : 'user';
            $users[] = [
                'id' => nextId($users),
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
            ];
            saveUsers($users);
            clearRateLimit('register');
            header('Location: /login.php');
            exit;
        }
    }

    if ($error !== '') {
        registerRateLimitFailure('register', AUTH_RATE_LIMIT_WINDOW_SECONDS);
    }
}

layoutHeader('Register');
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card surface-card">
            <div class="card-body">
                <h1 class="vm-page-title h4 mb-3"><i class="fa-solid fa-user-plus"></i> Register</h1>
                <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input id="username" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input id="password" class="form-control" type="password" name="password" required>
                    </div>
                    <button class="btn btn-vm-primary w-100">Create account</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php layoutFooter(); ?>
