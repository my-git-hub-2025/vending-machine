<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

requireLogin();
requireAdmin();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

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
$slotsCsv = implode(',', array_map(static fn ($v): string => (string)$v, $config['slots_per_column']));

layoutHeader('Admin Panel');
?>
<h1 class="h3 mb-3"><i class="fa-solid fa-screwdriver-wrench"></i> Admin Panel</h1>

<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Machine Configuration</h2>
                <form method="post" class="vstack gap-2">
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
                    <button class="btn btn-primary">Save configuration</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Stock Products</h2>
                <form method="post" class="vstack gap-2 mb-3">
                    <input type="hidden" name="action" value="save_product">
                    <input type="hidden" name="product_id" value="0">
                    <input class="form-control" name="name" placeholder="Product name" required>
                    <input type="number" step="0.01" min="0" class="form-control" name="price" placeholder="Price" required>
                    <input type="number" min="0" class="form-control" name="stock" placeholder="Stock quantity" required>
                    <button class="btn btn-success">Add product</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Item</th><th>Price</th><th>Stock</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td colspan="4">
                                    <form method="post" class="row g-1 align-items-center">
                                        <input type="hidden" name="action" value="save_product">
                                        <input type="hidden" name="product_id" value="<?= h((string)$product['id']) ?>">
                                        <div class="col-4"><input class="form-control form-control-sm" name="name" value="<?= h((string)$product['name']) ?>" required></div>
                                        <div class="col-3"><input type="number" min="0" step="0.01" class="form-control form-control-sm" name="price" value="<?= h((string)$product['price']) ?>" required></div>
                                        <div class="col-3"><input type="number" min="0" class="form-control form-control-sm" name="stock" value="<?= h((string)$product['stock']) ?>" required></div>
                                        <div class="col-2"><button class="btn btn-sm btn-outline-primary w-100">Update</button></div>
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
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Assign Slot</h2>
                <form method="post" class="vstack gap-2">
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
                    <button class="btn btn-warning">Save assignment</button>
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
<?php layoutFooter(); ?>
