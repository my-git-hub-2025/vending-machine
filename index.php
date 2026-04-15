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
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="fa-solid fa-cubes-stacked"></i> Machine Items</h1>
    <span class="badge text-bg-secondary">Rows: <?= h((string)$config['rows']) ?> | Columns: <?= h((string)$config['columns']) ?></span>
</div>

<div class="row g-3">
    <?php for ($col = 1; $col <= (int)$config['columns']; $col++): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header fw-semibold">Column <?= h((string)$col) ?></div>
                <ul class="list-group list-group-flush">
                    <?php for ($slot = 1; $slot <= (int)$config['slots_per_column'][$col]; $slot++):
                        $slotKey = $col . '-' . $slot;
                        $assignment = $assignments[$slotKey] ?? null;
                        $product = $assignment ? ($productMap[(int)$assignment['product_id']] ?? null) : null;
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold">Slot <?= h((string)$slot) ?></div>
                                <?php if ($product): ?>
                                    <div><?= h((string)$product['name']) ?></div>
                                    <small class="text-muted">$<?= h(number_format((float)$product['price'], 2)) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Empty</small>
                                <?php endif; ?>
                            </div>
                            <span class="badge <?= ($assignment && (int)$assignment['quantity'] > 0) ? 'text-bg-success' : 'text-bg-danger' ?>">
                                Qty: <?= h((string)(int)($assignment['quantity'] ?? 0)) ?>
                            </span>
                        </li>
                    <?php endfor; ?>
                </ul>
            </div>
        </div>
    <?php endfor; ?>
</div>
<?php layoutFooter();
