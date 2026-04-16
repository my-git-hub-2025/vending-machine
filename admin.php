<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

requireLogin();
requireAdmin();

$errors = [];
$success = '';
$countAdminUsers = static function (array $users): int {
    $adminCount = 0;
    foreach ($users as $user) {
        if ((string)($user['role'] ?? '') === 'admin') {
            $adminCount++;
        }
    }
    return $adminCount;
};
$findUserIndexById = static function (array $users, int $id): ?int {
    foreach ($users as $index => $user) {
        if ((int)($user['id'] ?? 0) === $id) {
            return $index;
        }
    }
    return null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'user');
        $users = getUsers();

        if ($username === '') {
            $errors[] = 'Username is required.';
        } elseif (!in_array($role, ['admin', 'user'], true)) {
            $errors[] = 'Invalid user role.';
        } elseif ($id <= 0 && strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long for new users.';
        } elseif ($id > 0 && $password !== '' && strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        } else {
            foreach ($users as $existingUser) {
                $existingId = (int)($existingUser['id'] ?? 0);
                $existingUsername = (string)($existingUser['username'] ?? '');
                if ($existingId !== $id && strcasecmp($existingUsername, $username) === 0) {
                    $errors[] = 'Username already exists.';
                    break;
                }
            }
        }

        if (count($errors) === 0) {
            $created = false;
            if ($id <= 0) {
                $users[] = [
                    'id' => nextId($users),
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                ];
                $created = true;
            } else {
                $foundIndex = $findUserIndexById($users, $id);

                if ($foundIndex === null) {
                    $errors[] = 'User not found for update.';
                } else {
                    $adminCount = $countAdminUsers($users);

                    $targetUserRole = (string)($users[$foundIndex]['role'] ?? 'user');
                    if ($targetUserRole === 'admin' && $role !== 'admin' && $adminCount <= 1) {
                        $errors[] = 'At least one admin account is required.';
                    } else {
                        $users[$foundIndex]['username'] = $username;
                        $users[$foundIndex]['role'] = $role;
                        if ($password !== '') {
                            $users[$foundIndex]['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                        }

                        if ((int)(currentUser()['id'] ?? 0) === $id) {
                            $_SESSION['user']['username'] = $username;
                            $_SESSION['user']['role'] = $role;
                        }
                    }
                }
            }

            if (count($errors) === 0) {
                saveUsers($users);
                $success = $created ? 'User created.' : 'User updated.';
            }
        }
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        $users = getUsers();
        $foundIndex = $findUserIndexById($users, $id);

        if ($foundIndex === null) {
            $errors[] = 'User not found for deletion.';
        } elseif ((int)(currentUser()['id'] ?? 0) === $id) {
            $errors[] = 'You cannot delete your own account.';
        } else {
            $adminCount = $countAdminUsers($users);

            $targetUserRole = (string)($users[$foundIndex]['role'] ?? 'user');
            if ($targetUserRole === 'admin' && $adminCount <= 1) {
                $errors[] = 'At least one admin account is required.';
            } else {
                array_splice($users, $foundIndex, 1);
                saveUsers($users);
                $success = 'User deleted.';
            }
        }
    }

    if ($action === 'save_config') {
        $rows = max(1, (int)($_POST['rows'] ?? 1));
        $columns = max(1, (int)($_POST['columns'] ?? 1));
        $slotsRaw = trim((string)($_POST['slots_per_column'] ?? ''));

        $parts = array_map('trim', explode(',', $slotsRaw));
        $slotsPerColumn = [];
        for ($i = 1; $i <= $columns; $i++) {
            $slotsPerColumn[$i] = max(1, (int)($parts[$i - 1] ?? $rows));
        }

        saveMachineConfig($rows, $columns, $slotsPerColumn);

        $allowedKeys = [];
        for ($col = 1; $col <= $columns; $col++) {
            for ($slot = 1; $slot <= $slotsPerColumn[$col]; $slot++) {
                $allowedKeys[$col . '-' . $slot] = true;
            }
        }

        $filteredAssignments = [];
        foreach (getAssignments() as $key => $assignment) {
            if (isset($allowedKeys[$key])) {
                $filteredAssignments[$key] = $assignment;
            }
        }
        saveAssignments($filteredAssignments);

        $success = 'Machine configuration updated.';
    }

    if ($action === 'save_product') {
        $id = (int)($_POST['product_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $price = (float)($_POST['price'] ?? 0);
        $stock = max(0, (int)($_POST['stock'] ?? 0));

        if ($name === '' || $price < 0) {
            $errors[] = 'Product name and a valid price are required.';
        } else {
            $products = getProducts();
            $updated = false;
            $created = false;
            if ($id <= 0) {
                $products[] = [
                    'id' => nextId($products),
                    'name' => $name,
                    'price' => $price,
                    'stock' => $stock,
                ];
                $created = true;
            } else {
                foreach ($products as &$product) {
                    if ((int)$product['id'] === $id) {
                        $product['name'] = $name;
                        $product['price'] = $price;
                        $product['stock'] = $stock;
                        $updated = true;
                    }
                }
                unset($product);
                if (!$updated) {
                    $errors[] = 'Product not found for update.';
                }
            }
            if (count($errors) === 0) {
                saveProducts($products);
                $success = $created ? 'Product created.' : 'Product updated.';
            }
        }
    }

    if ($action === 'assign_slot') {
        $column = max(1, (int)($_POST['column'] ?? 1));
        $slot = max(1, (int)($_POST['slot'] ?? 1));
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));

        $config = getMachineConfig();
        if ($column > (int)$config['columns'] || $slot > (int)$config['slots_per_column'][$column]) {
            $errors[] = 'Invalid column or slot.';
        } elseif (!($product = findProductById($productId))) {
            $errors[] = 'Selected product does not exist.';
        } else {
            $assignments = getAssignments();
            $slotKey = $column . '-' . $slot;
            $previous = $assignments[$slotKey] ?? null;

            $totals = getAssignedQuantitiesByProduct($assignments);
            $currentTotal = (int)($totals[$productId] ?? 0);
            if ($previous && (int)$previous['product_id'] === $productId) {
                $currentTotal -= (int)$previous['quantity'];
            }

            if ($currentTotal < 0) {
                $errors[] = 'Stock assignment data is inconsistent. Please review existing slot quantities.';
            } else {
                $availableStock = (int)$product['stock'] - $currentTotal;
                if ($quantity > $availableStock) {
                    $errors[] = 'Assigned quantity exceeds remaining stock for this product.';
                } else {
                    $assignments[$slotKey] = [
                        'column' => $column,
                        'slot' => $slot,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                    ];
                    saveAssignments($assignments);
                    $success = 'Slot assignment saved.';
                }
            }
        }
    }
}

$config = getMachineConfig();
$products = getProducts();
$assignments = getAssignments();
$users = getUsers();
$slotsCsv = implode(',', array_map(static fn ($v): string => (string)$v, $config['slots_per_column']));

layoutHeader('Admin Panel');
?>
<div class="hero-panel p-4 mb-4">
    <h1 class="vm-page-title mb-2"><i class="fa-solid fa-screwdriver-wrench"></i> Admin Panel</h1>
    <p class="mb-0 vm-subtle">Configure machine shape, manage stock, and map slot assignments from one place.</p>
</div>

<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card surface-card h-100">
            <div class="card-body">
                <h2 class="h5">Machine Configuration</h2>
                <form method="post" class="vstack gap-2">
                    <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="save_config">
                    <div>
                        <label class="form-label">Rows</label>
                        <input type="number" class="form-control" name="rows" min="1" value="<?= h((string)$config['rows']) ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Columns</label>
                        <input type="number" class="form-control" name="columns" min="1" value="<?= h((string)$config['columns']) ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Slots per column (comma-separated)</label>
                        <input class="form-control" name="slots_per_column" value="<?= h($slotsCsv) ?>" required>
                        <small class="text-muted">Example for 4 columns: 5,5,6,4</small>
                    </div>
                    <button class="btn btn-vm-primary">Save configuration</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card surface-card h-100">
            <div class="card-body">
                <h2 class="h5">Stock Products</h2>
                <form method="post" class="vstack gap-2 mb-3">
                    <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="save_product">
                    <input type="hidden" name="product_id" value="0">
                    <input class="form-control" name="name" placeholder="Product name" required>
                    <input type="number" step="0.01" min="0" class="form-control" name="price" placeholder="Price" required>
                    <input type="number" min="0" class="form-control" name="stock" placeholder="Stock quantity" required>
                    <button class="btn btn-vm-primary">Add product</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Item</th><th>Price</th><th>Stock</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td colspan="4">
                                    <form method="post" class="row g-1 align-items-center">
                                        <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                                        <input type="hidden" name="action" value="save_product">
                                        <input type="hidden" name="product_id" value="<?= h((string)$product['id']) ?>">
                                        <div class="col-4"><input class="form-control form-control-sm" name="name" value="<?= h((string)$product['name']) ?>" required></div>
                                        <div class="col-3"><input type="number" min="0" step="0.01" class="form-control form-control-sm" name="price" value="<?= h((string)$product['price']) ?>" required></div>
                                        <div class="col-3"><input type="number" min="0" class="form-control form-control-sm" name="stock" value="<?= h((string)$product['stock']) ?>" required></div>
                                        <div class="col-2"><button class="btn btn-sm btn-vm-secondary w-100">Update</button></div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($products) === 0): ?><tr><td colspan="4" class="text-muted">No products yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card surface-card h-100">
            <div class="card-body">
                <h2 class="h5">Assign Slot</h2>
                <form method="post" class="vstack gap-2">
                    <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="assign_slot">
                    <div>
                        <label class="form-label">Column</label>
                        <input type="number" class="form-control" min="1" max="<?= h((string)$config['columns']) ?>" name="column" required>
                    </div>
                    <div>
                        <label class="form-label">Slot</label>
                        <input type="number" class="form-control" min="1" name="slot" required>
                    </div>
                    <div>
                        <label class="form-label">Product</label>
                        <select class="form-select" name="product_id" required>
                            <option value="">Select</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= h((string)$product['id']) ?>"><?= h((string)$product['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Quantity in slot</label>
                        <input type="number" class="form-control" min="0" name="quantity" required>
                    </div>
                    <button class="btn btn-vm-primary">Save assignment</button>
                </form>

                <hr>
                <h3 class="h6">Current Assignments</h3>
                <ul class="list-group list-group-flush">
                    <?php foreach ($assignments as $key => $assignment): ?>
                        <?php $product = findProductById((int)$assignment['product_id']); ?>
                        <li class="list-group-item px-0">
                            <strong><?= h((string)$key) ?></strong>:
                            <?= h((string)($product['name'] ?? 'Unknown')) ?>,
                            Qty <?= h((string)(int)$assignment['quantity']) ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if (count($assignments) === 0): ?><li class="list-group-item px-0 text-muted">No assignments yet.</li><?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-12">
        <div class="card surface-card">
            <div class="card-body">
                <h2 class="h5">Users</h2>
                <form method="post" class="row g-2 align-items-end mb-3">
                    <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="save_user">
                    <input type="hidden" name="user_id" value="0">
                    <div class="col-md-4">
                        <label class="form-label">Username</label>
                        <input class="form-control" name="username" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" minlength="8" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" required>
                            <option value="user">user</option>
                            <option value="admin">admin</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-vm-primary w-100">Add user</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Username</th><th>Role</th><th>Password</th><th>Update</th><th>Delete</th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <form method="post" class="d-flex gap-2 align-items-center">
                                        <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                                        <input type="hidden" name="action" value="save_user">
                                        <input type="hidden" name="user_id" value="<?= h((string)$user['id']) ?>">
                                        <input class="form-control form-control-sm" name="username" value="<?= h((string)$user['username']) ?>" required>
                                </td>
                                <td>
                                        <select class="form-select form-select-sm" name="role" required>
                                            <option value="user" <?= (string)$user['role'] === 'user' ? 'selected' : '' ?>>user</option>
                                            <option value="admin" <?= (string)$user['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                                        </select>
                                </td>
                                <td>
                                        <input type="password" class="form-control form-control-sm" name="password" placeholder="Leave blank to keep" minlength="8">
                                </td>
                                <td>
                                        <button class="btn btn-sm btn-vm-secondary">Update</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= h((string)$user['id']) ?>">
                                        <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($users) === 0): ?><tr><td colspan="5" class="text-muted">No users yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php layoutFooter(); ?>
