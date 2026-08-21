<?php
$activePage = 'canteen_stock';
$pageTitle = 'Stock Adjustment';
include __DIR__ . '/../../includes/header.php';

$products = $pdo->query("SELECT id, name, unit, stock_qty FROM canteen_products WHERE status = 'active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $type = $_POST['adjustment_type'] ?? 'in';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($product_id <= 0) {
        $error = 'Please select a product.';
    } elseif ($quantity <= 0) {
        $error = 'Quantity must be greater than 0.';
    } else {
        $logType = $type === 'in' ? 'adjustment_in' : 'adjustment_out';

        if ($type === 'out') {
            $stmt = $pdo->prepare('SELECT stock_qty FROM canteen_products WHERE id = ?');
            $stmt->execute([$product_id]);
            $current = $stmt->fetchColumn();
            if ($current < $quantity) {
                $error = 'Insufficient stock. Available: ' . $current;
            }
        }

        if (empty($error)) {
            $change = $type === 'in' ? $quantity : -$quantity;
            $stmt = $pdo->prepare('UPDATE canteen_products SET stock_qty = stock_qty + ? WHERE id = ?');
            $stmt->execute([$change, $product_id]);

            $label = $type === 'in' ? 'Stock In' : 'Stock Out';
            $stmt = $pdo->prepare('INSERT INTO canteen_stock_log (product_id, type, quantity, notes) VALUES (?, ?, ?, ?)');
            $stmt->execute([$product_id, $logType, $quantity, ($notes ?: $label)]);

            header('Location: /gym/canteen/stock/add.php?msg=adjusted');
            exit;
        }
    }
}

$msg = $_GET['msg'] ?? '';
if ($msg === 'adjusted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Stock adjusted successfully.</div>';
?>

<div class="mb-4">
    <a href="/gym/canteen/stock/" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back to Stock</a>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card" style="border-top:3px solid #f7b731;">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-sliders-h text-warning me-2"></i>Adjust Stock</h6>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-box me-1 text-muted"></i>Select Product *</label>
                        <select name="product_id" class="form-select" required id="adjProduct">
                            <option value="">Choose product...</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>" data-stock="<?php echo $p['stock_qty']; ?>" data-unit="<?php echo $p['unit']; ?>">
                                    <?php echo htmlspecialchars($p['name']); ?> (Current: <?php echo $p['stock_qty']; ?> <?php echo $p['unit']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="adjStockInfo" class="alert alert-light border d-none mb-3"></div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-exchange-alt me-1 text-muted"></i>Adjustment Type *</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="adjustment_type" id="typeIn" value="in" checked>
                                <label class="form-check-label fw-semibold text-success" for="typeIn"><i class="fas fa-arrow-up me-1"></i>Stock In (Add)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="adjustment_type" id="typeOut" value="out">
                                <label class="form-check-label fw-semibold text-danger" for="typeOut"><i class="fas fa-arrow-down me-1"></i>Stock Out (Deduct)</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-sort-numeric-up me-1 text-muted"></i>Quantity *</label>
                        <input type="number" name="quantity" class="form-control form-control-lg" min="1" required placeholder="Enter quantity" id="adjQty">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-sticky-note me-1 text-muted"></i>Reason / Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. Damaged, Expired, Stock taking, Free sample">
                    </div>
                    <button type="submit" class="btn btn-lg fw-bold w-100" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-save me-1"></i>Save Adjustment</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card" style="border-top:3px solid #3b82f6;">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-history text-primary me-2"></i>Recent Adjustments</h6>
                <?php
                $logs = $pdo->query("
                    SELECT sl.*, cp.name AS product_name, cp.unit
                    FROM canteen_stock_log sl
                    LEFT JOIN canteen_products cp ON cp.id = sl.product_id
                    WHERE sl.type IN ('adjustment_in', 'adjustment_out')
                    ORDER BY sl.created_at DESC
                    LIMIT 20
                ")->fetchAll();
                ?>
                <div class="table-responsive" style="max-height:500px;overflow-y:auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="sticky-top bg-white"><tr><th>Date</th><th>Product</th><th>Type</th><th class="text-end">Qty</th><th>Notes</th></tr></thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-sliders-h me-1"></i>No adjustments yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td class="small"><?php echo date('d M Y H:i', strtotime($l['created_at'])); ?></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($l['product_name']); ?></td>
                                    <td>
                                        <?php if ($l['type'] === 'adjustment_in'): ?>
                                            <span class="badge bg-success"><i class="fas fa-arrow-up me-1"></i>In</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-arrow-down me-1"></i>Out</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold"><?php echo $l['quantity']; ?> <?php echo $l['unit']; ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($l['notes'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('adjProduct').addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    var el = document.getElementById('adjStockInfo');
    if (opt.value) {
        var stock = opt.getAttribute('data-stock');
        var unit = opt.getAttribute('data-unit');
        el.innerHTML = '<i class="fas fa-info-circle text-primary me-1"></i>Current stock: <strong>' + stock + ' ' + unit + '</strong>';
        el.classList.remove('d-none');
    } else {
        el.classList.add('d-none');
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
