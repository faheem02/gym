<?php
$activePage = 'canteen_stock';
$pageTitle = 'Stock Report';
include __DIR__ . '/../../includes/header.php';

$filterCategory = $_GET['category'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT *, stock_qty AS stock FROM canteen_products WHERE status = 'active'";
$params = [];
if ($filterCategory !== '') {
    $sql .= " AND category = ?";
    $params[] = $filterCategory;
}
if ($search !== '') {
    $sql .= " AND name LIKE ?";
    $params[] = '%' . $search . '%';
}
if ($filterStatus === 'low') {
    $sql .= " AND stock_qty <= min_stock AND stock_qty > 0";
} elseif ($filterStatus === 'out') {
    $sql .= " AND stock_qty = 0";
} elseif ($filterStatus === 'ok') {
    $sql .= " AND stock_qty > min_stock";
}
$sql .= " ORDER BY name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT DISTINCT category FROM canteen_products WHERE status = 'active' AND category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Calculate overall summary metrics from all active products
$allProdRows = $pdo->query("SELECT stock_qty AS stock, purchase_price, min_stock FROM canteen_products WHERE status = 'active'")->fetchAll();
$totalStock = 0;
$totalValue = 0;
$lowStockCount = 0;
$outOfStock = 0;
foreach ($allProdRows as $p) {
    $stk = (int)$p['stock'];
    $min = (int)$p['min_stock'];
    $totalStock += $stk;
    $totalValue += $stk * (float)$p['purchase_price'];
    if ($stk <= $min) $lowStockCount++;
    if ($stk == 0) $outOfStock++;
}
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold mb-0"><i class="fas fa-boxes text-warning me-2"></i>Canteen Stock Report</h5>
        <small class="text-muted">Current inventory levels, valuations, and low-stock alerts</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/gym/canteen/stock/history.php" class="btn btn-dark btn-sm fw-bold"><i class="fas fa-history me-1"></i>Stock History</a>
        <a href="/gym/canteen/stock/add.php" class="btn btn-warning btn-sm fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-sliders-h me-1"></i>Adjust Stock</a>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm fw-bold"><i class="fas fa-print me-1"></i>Print</button>
        <button onclick="downloadStockReportPDF()" class="btn btn-outline-danger btn-sm fw-bold"><i class="fas fa-file-pdf me-1"></i>Download PDF</button>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary"><i class="fas fa-boxes"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo count($allProdRows); ?></h5>
                    <small class="text-muted">Total Products</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info"><i class="fas fa-cubes"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo number_format($totalStock, 0); ?></h5>
                    <small class="text-muted">Total In-Stock Units</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success"><i class="fas fa-coins"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalValue, 0); ?></h5>
                    <small class="text-muted">Total Inventory Value</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold text-danger"><?php echo $lowStockCount; ?></h5>
                    <small class="text-muted">Low / Out of Stock</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card mb-4 shadow-sm" style="border-top:3px solid #f7b731;">
    <div class="card-body p-3">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label small fw-bold mb-1">Search Product</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Type product name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filterCategory === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Stock Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="ok" <?php echo $filterStatus === 'ok' ? 'selected' : ''; ?>>In Stock (Healthy)</option>
                    <option value="low" <?php echo $filterStatus === 'low' ? 'selected' : ''; ?>>Low Stock (&le; Min)</option>
                    <option value="out" <?php echo $filterStatus === 'out' ? 'selected' : ''; ?>>Out of Stock (0 Units)</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-warning btn-sm flex-fill fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Clear Filters"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Stock Table Section -->
<div class="card shadow-sm" id="printSection" style="border-top:3px solid #f7b731;">
    <div class="card-body p-4">
        <!-- Letterhead for Print/PDF -->
        <div class="d-none d-print-block mb-3">
            <?php include __DIR__ . '/../../includes/print_header.php'; ?>
            <div class="text-center mb-3">
                <h4 class="fw-bold mb-1">CANTEEN STOCK REPORT</h4>
                <p class="text-muted small mb-0">Generated on <?php echo date('d M Y, h:i A'); ?></p>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0"><i class="fas fa-clipboard-list me-2" style="color:#f7b731;"></i>Current Stock Levels (<?php echo count($products); ?> Products)</h6>
            <div class="d-none d-print-block small text-muted">Total Valuation: <strong>Rs.<?php echo number_format($totalValue, 0); ?></strong></div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th style="width:45px;">#</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th class="text-end">Cost Price</th>
                        <th class="text-end">Sale Price</th>
                        <th class="text-end">Available Stock</th>
                        <th class="text-end">Min Alert</th>
                        <th class="text-end">Stock Value</th>
                        <th class="text-center">Status</th>
                        <th class="text-end d-print-none">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-5"><i class="fas fa-box-open fa-2x mb-2 text-warning"></i><br>No matching products found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($products as $i => $p): ?>
                        <?php
                        $stock = (int)$p['stock'];
                        $min = (int)$p['min_stock'];
                        $value = $stock * (float)$p['purchase_price'];
                        $isOut = $stock == 0;
                        $isLow = $stock <= $min && !$isOut;
                        ?>
                        <tr class="<?php echo $isOut ? 'table-danger' : ($isLow ? 'table-warning' : ''); ?>">
                            <td><?php echo $i + 1; ?></td>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($p['name']); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($p['category'] ?? '-'); ?></span></td>
                            <td class="text-end text-muted">Rs.<?php echo number_format($p['purchase_price'], 0); ?></td>
                            <td class="text-end fw-semibold">Rs.<?php echo number_format($p['sale_price'], 0); ?></td>
                            <td class="text-end fw-bold fs-6 <?php echo $isOut ? 'text-danger' : ($isLow ? 'text-warning-emphasis' : 'text-success'); ?>">
                                <?php echo number_format($stock); ?> <small class="text-muted fw-normal"><?php echo htmlspecialchars($p['unit']); ?></small>
                            </td>
                            <td class="text-end text-muted small"><?php echo $min; ?> <?php echo htmlspecialchars($p['unit']); ?></td>
                            <td class="text-end fw-bold text-dark">Rs.<?php echo number_format($value, 0); ?></td>
                            <td class="text-center">
                                <?php if ($isOut): ?>
                                    <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Out of Stock</span>
                                <?php elseif ($isLow): ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i>Low Stock</span>
                                <?php else: ?>
                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end d-print-none">
                                <div class="d-inline-flex gap-1">
                                    <a href="/gym/canteen/stock/history.php?product_id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="View Movement History">
                                        <i class="fas fa-history"></i>
                                    </a>
                                    <a href="/gym/canteen/products/edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Edit Product">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
#printSection .print-logo img {
    filter: brightness(0);
    -webkit-filter: brightness(0);
}
@media print {
    .page-header, .card.mb-4, .stat-card, .btn, form, .sidebar, .navbar, .d-print-none {
        display: none !important;
    }
    body {
        background: #fff !important;
        font-size: 11px;
    }
    #printSection {
        border: none !important;
        box-shadow: none !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .table-dark {
        background-color: #1a1a2e !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadStockReportPDF() {
    var element = document.getElementById('printSection');
    var opt = {
        margin:       [8, 8, 8, 8],
        filename:     'Canteen_Stock_Report_<?php echo date('Ymd_His'); ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, scrollY: 0 },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
