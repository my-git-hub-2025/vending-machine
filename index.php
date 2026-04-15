<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$config = getMachineConfig();
$products = getProducts();
$assignments = getAssignments();

$productMap = [];
foreach ($products as $product) {
    $productMap[(int)$product['id']] = $product;
}

layoutHeader('Vending Machine');
?>
<section class="hero-panel p-4 p-md-5 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <h1 class="vm-page-title mb-2"><i class="fa-solid fa-cubes-stacked"></i> Smart Vending Display</h1>
            <p class="mb-0 vm-subtle">Browse columns, check slot stock instantly, and manage access with cleaner controls.</p>
        </div>
        <div class="d-flex flex-wrap align-items-start align-content-start gap-2">
            <span class="hero-chip"><i class="fa-solid fa-layer-group"></i> Rows: <?= h((string)$config['rows']) ?></span>
            <span class="hero-chip"><i class="fa-solid fa-table-columns"></i> Columns: <?= h((string)$config['columns']) ?></span>
            <?php if (isLoggedIn()): ?>
                <?php if (isAdmin()): ?>
                    <a class="btn btn-sm btn-vm-primary" href="/admin.php"><i class="fa-solid fa-screwdriver-wrench"></i> Admin panel</a>
                <?php endif; ?>
                <a class="btn btn-sm btn-vm-secondary" href="/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            <?php else: ?>
                <a class="btn btn-sm btn-vm-secondary" href="/login.php"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
                <a class="btn btn-sm btn-vm-primary" href="/register.php"><i class="fa-solid fa-user-plus"></i> Register</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0 fw-semibold">Product Grid</h2>
    <small class="vm-subtle">Status: <span class="text-success">in stock</span> · <span class="text-warning-emphasis">low</span> · <span class="text-danger">out</span></small>
</div>

<div class="row g-3">
    <?php for ($col = 1; $col <= (int)$config['columns']; $col++): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 surface-card vm-column-card">
                <div class="card-header fw-semibold">Column <?= h((string)$col) ?></div>
                <ul class="list-group list-group-flush">
                    <?php for ($slot = 1; $slot <= (int)$config['slots_per_column'][$col]; $slot++):
                        $slotKey = $col . '-' . $slot;
                        $assignment = $assignments[$slotKey] ?? null;
                        $product = $assignment ? ($productMap[(int)$assignment['product_id']] ?? null) : null;
                        $quantity = (int)($assignment['quantity'] ?? 0);
                        $stockClass = $quantity <= 0 ? 'stock-out' : ($quantity <= 2 ? 'stock-low' : 'stock-high');
                        $stockText = $quantity <= 0 ? 'Out' : ($quantity <= 2 ? 'Low' : 'In');
                        ?>
                        <li class="list-group-item vm-slot-item d-flex justify-content-between align-items-start gap-3">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="vm-slot-visual">
                                    <i class="fa-solid <?= $product ? 'fa-bottle-water' : 'fa-box-open' ?>"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold">Slot <?= h((string)$slot) ?></div>
                                <?php if ($product): ?>
                                    <div><?= h((string)$product['name']) ?></div>
                                    <small class="vm-price">$<?= h(number_format((float)$product['price'], 2)) ?></small>
                                <?php else: ?>
                                    <small class="vm-subtle d-inline-block vm-skeleton px-3 py-1">Empty</small>
                                <?php endif; ?>
                                </div>
                            </div>
                            <span class="badge vm-stock-badge <?= h($stockClass) ?>">
                                <?= h($stockText) ?> · <?= h((string)$quantity) ?>
                            </span>
                        </li>
                    <?php endfor; ?>
                </ul>
            </div>
        </div>
    <?php endfor; ?>
</div>
<?php layoutFooter(); ?>
