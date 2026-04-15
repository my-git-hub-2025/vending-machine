<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
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
            header('Location: /login.php');
            exit;
        }
    }
}

layoutHeader('Register');
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3"><i class="fa-solid fa-user-plus"></i> Register</h1>
                <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input class="form-control" type="password" name="password" required>
                    </div>
                    <button class="btn btn-primary w-100">Create account</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php layoutFooter(); ?>
