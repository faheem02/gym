<?php
$activePage = 'canteen_stock';
$pageTitle = 'Stock Report';
include __DIR__ . '/../../includes/header.php';

$filterCategory = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT *, stock_qty AS stock FROM canteen_products WHERE status = 'active'";
$params = [];
if ($filterCategory !== '') { $sql .= " AND category = ?"; $params[] = $filterCategory; }
if ($search !== '') { $sql .= " AND name LIKE ?"; $params[] = '%' . $search . '%'; }
$sql .= " ORDER BY name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT DISTINCT category FROM canteen_products WHERE status = 'active' AND category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$totalStock = 0;
$totalValue = 0;
$lowStockCount = 0;
$outOfStock = 0;
foreach ($products as $p) {
    $totalStock += (int)$p['stock'];
    $totalValue += (float)$p['stock'] * (float)$p['purchase_price'];
    if ((int)$p['stock'] <= (int)$p['min_stock']) $lowStockCount++;
    if ((int)$p['stock'] == 0) $outOfStock++;
}

$recentLogs = $pdo->query("
    SELECT sl.*, cp.name AS product_name, cp.unit
    FROM canteen_stock_log sl
    LEFT JOIN canteen_products cp ON cp.id = sl.product_id
    ORDER BY sl.created_at DESC
    LIMIT 25
")->fetchAll();
?>

<div class="page-header">
    <div></div>
    <a href="add.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-sliders-h me-1"></i>Adjust Stock</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-primary"><i class="fas fa-boxes"></i></div>
            <div><h5 class="mb-0 fw-bold"><?php echo count($products); ?></h5><small class="text-muted">Products</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-info"><i class="fas fa-cubes"></i></div>
            <div><h5 class="mb-0 fw-bold"><?php echo number_format($totalStock, 0); ?></h5><small class="text-muted">Total Units</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-success"><i class="fas fa-coins"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalValue, 0); ?></h5><small class="text-muted">Stock Value</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></div>
            <div><h5 class="mb-0 fw-bold"><?php echo $lowStockCount; ?></h5><small class="text-muted">Low / Out of Stock</small></div>
        </div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card" style="border-top:3px solid #f7b731;">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-clipboard-list me-2" style="color:#f7b731;"></i>Current Stock Levels</h6>
                <form class="row g-2 mb-3" method="GET">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search product..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="category" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filterCategory === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-sm flex-fill fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-filter me-1"></i>Filter</button>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th class="text-end">Pur. Price</th>
                                <th class="text-end">Sale Price</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Min</th>
                                <th class="text-end">Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-box-open me-1"></i>No products found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($products as $i => $p): ?>
                                <?php
                                $stock = (int)$p['stock'];
                                $min = (int)$p['min_stock'];
                                $value = $stock * (float)$p['purchase_price'];
                                $isLow = $stock <= $min;
                                ?>
                                <tr class="<?php echo $isLow ? 'table-danger' : ''; ?>">
                                    <td><?php echo $i + 1; ?></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?></td>
                                    <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($p['category'] ?? '-'); ?></span></td>
                                    <td class="text-end">Rs.<?php echo number_format($p['purchase_price'], 0); ?></td>
                                    <td class="text-end">Rs.<?php echo number_format($p['sale_price'], 0); ?></td>
                                    <td class="text-end fw-bold <?php echo $isLow ? 'text-danger' : ''; ?>"><?php echo $stock; ?> <?php echo $p['unit']; ?></td>
                                    <td class="text-end"><?php echo $min; ?></td>
                                    <td class="text-end fw-bold">Rs.<?php echo number_format($value, 0); ?></td>
                                    <td>
                                        <?php if ($stock == 0): ?>
                                            <span class="badge badge-inactive"><i class="fas fa-times-circle me-1"></i>Out</span>
                                        <?php elseif ($isLow): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i>Low</span>
                                        <?php else: ?>
                                            <span class="badge badge-active"><i class="fas fa-check-circle me-1"></i>OK</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card" style="border-top:3px solid #3b82f6;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-history text-primary me-2"></i>Stock History</h6>
                    <a href="add.php" class="btn btn-sm btn-outline-warning fw-bold">+ Adjust</a>
                </div>
                <div class="table-responsive" style="max-height:600px;overflow-y:auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="sticky-top bg-white">
                            <tr><th>When</th><th>Product</th><th>Type</th><th class="text-end">Qty</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentLogs)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-history me-1"></i>No stock movements yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recentLogs as $l): ?>
                                <tr>
                                    <td class="small"><?php echo date('d M H:i', strtotime($l['created_at'])); ?></td>
                                    <td class="fw-semibold small"><?php echo htmlspecialchars($l['product_name']); ?></td>
                                    <td>
                                        <?php
                                        $typeLabels = [
                                            'purchase' => ['Purchased', 'success', 'fa-shopping-cart'],
                                            'sale' => ['Sold', 'primary', 'fa-cash-register'],
                                            'adjustment_in' => ['In', 'warning', 'fa-arrow-up'],
                                            'adjustment_out' => ['Out', 'danger', 'fa-arrow-down'],
                                        ];
                                        $tl = $typeLabels[$l['type']] ?? ['Unknown', 'secondary', 'fa-question'];
                                        ?>
                                        <span class="badge bg-<?php echo $tl[1]; ?>"><i class="fas <?php echo $tl[2]; ?> me-1"></i><?php echo $tl[0]; ?></span>
                                    </td>
                                    <td class="text-end fw-bold small"><?php echo $l['quantity']; ?> <?php echo $l['unit']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
