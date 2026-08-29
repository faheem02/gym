<?php
$activePage = 'canteen_stock';
$pageTitle = 'Stock Movement History';
include __DIR__ . '/../../includes/header.php';

$filterProduct = (int)($_GET['product_id'] ?? 0);
$filterType = $_GET['type'] ?? '';
$filterFrom = $_GET['date_from'] ?? '';
$filterTo = $_GET['date_to'] ?? '';
$search = trim($_GET['search'] ?? '');

$allProducts = $pdo->query("SELECT id, name, unit, category FROM canteen_products ORDER BY name")->fetchAll();

$sql = "
    SELECT sl.*, cp.name AS product_name, cp.unit, cp.category
    FROM canteen_stock_log sl
    LEFT JOIN canteen_products cp ON cp.id = sl.product_id
    WHERE 1=1
";
$params = [];

if ($filterProduct > 0) {
    $sql .= " AND sl.product_id = ?";
    $params[] = $filterProduct;
}
if ($filterType !== '') {
    $sql .= " AND sl.type = ?";
    $params[] = $filterType;
}
if ($filterFrom !== '') {
    $sql .= " AND DATE(sl.created_at) >= ?";
    $params[] = $filterFrom;
}
if ($filterTo !== '') {
    $sql .= " AND DATE(sl.created_at) <= ?";
    $params[] = $filterTo;
}
if ($search !== '') {
    $sql .= " AND (cp.name LIKE ? OR sl.notes LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$sql .= " ORDER BY sl.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Stats calculation
$totalIn = 0;
$totalOut = 0;
$totalMovements = count($logs);

foreach ($logs as $l) {
    $qty = (int)$l['quantity'];
    if (in_array($l['type'], ['opening', 'purchase', 'adjustment_in'])) {
        $totalIn += $qty;
    } else {
        $totalOut += $qty;
    }
}

$typeLabels = [
    'opening' => ['Opening Stock', 'dark', 'fa-box', '+'],
    'purchase' => ['Supplier Purchase', 'success', 'fa-truck', '+'],
    'sale' => ['Canteen Sale', 'primary', 'fa-cash-register', '-'],
    'adjustment_in' => ['Manual Stock In', 'info', 'fa-arrow-up', '+'],
    'adjustment_out' => ['Manual Stock Out', 'danger', 'fa-arrow-down', '-'],
];
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <a href="/gym/canteen/stock/" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Stock Levels</a>
        <h5 class="fw-bold mb-0"><i class="fas fa-history text-primary me-2"></i>Stock Movement History</h5>
    </div>
    <div class="d-flex gap-2">
        <a href="/gym/canteen/stock/add.php" class="btn btn-warning btn-sm fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-sliders-h me-1"></i>Adjust Stock</a>
        <button onclick="window.print()" class="btn btn-dark btn-sm fw-bold"><i class="fas fa-print me-1"></i>Print Report</button>
        <button onclick="downloadStockHistoryPDF()" class="btn btn-outline-danger btn-sm fw-bold"><i class="fas fa-file-pdf me-1"></i>Download PDF</button>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary"><i class="fas fa-list"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo number_format($totalMovements); ?></h5>
                    <small class="text-muted">Total Movements</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success"><i class="fas fa-arrow-circle-down"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold text-success">+<?php echo number_format($totalIn); ?></h5>
                    <small class="text-muted">Total Units Added (In)</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger"><i class="fas fa-arrow-circle-up"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold text-danger">-<?php echo number_format($totalOut); ?></h5>
                    <small class="text-muted">Total Units Sold/Removed (Out)</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info"><i class="fas fa-exchange-alt"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo ($totalIn - $totalOut >= 0 ? '+' : '') . number_format($totalIn - $totalOut); ?></h5>
                    <small class="text-muted">Net Change in Units</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card mb-4 shadow-sm" style="border-top:3px solid #3b82f6;">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Product</label>
                <select name="product_id" class="form-select form-select-sm">
                    <option value="">All Products</option>
                    <?php foreach ($allProducts as $pr): ?>
                        <option value="<?php echo $pr['id']; ?>" <?php echo $filterProduct == $pr['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pr['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Movement Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="opening" <?php echo $filterType === 'opening' ? 'selected' : ''; ?>>Opening Stock</option>
                    <option value="purchase" <?php echo $filterType === 'purchase' ? 'selected' : ''; ?>>Supplier Purchase</option>
                    <option value="sale" <?php echo $filterType === 'sale' ? 'selected' : ''; ?>>Canteen Sale</option>
                    <option value="adjustment_in" <?php echo $filterType === 'adjustment_in' ? 'selected' : ''; ?>>Manual Stock In</option>
                    <option value="adjustment_out" <?php echo $filterType === 'adjustment_out' ? 'selected' : ''; ?>>Manual Stock Out</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">From Date</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterFrom); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">To Date</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterTo); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Search Note</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Notes / keyword..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold"><i class="fas fa-filter"></i></button>
                <a href="history.php" class="btn btn-outline-secondary btn-sm" title="Clear Filters"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- History Log Section -->
<div class="card shadow-sm" id="printSection" style="border-top:3px solid #3b82f6;">
    <div class="card-body p-4">
        <!-- Letterhead for Print/PDF -->
        <div class="d-none d-print-block mb-3">
            <?php include __DIR__ . '/../../includes/print_header.php'; ?>
            <div class="text-center mb-3">
                <h4 class="fw-bold mb-1">STOCK MOVEMENT AUDIT HISTORY</h4>
                <p class="text-muted small mb-0">Generated on <?php echo date('d M Y, h:i A'); ?></p>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0"><i class="fas fa-list-alt text-primary me-2"></i>Stock Movement Records (<?php echo count($logs); ?>)</h6>
            <span class="badge bg-light text-dark border">Sorted by newest first</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th style="width:45px;">#</th>
                        <th>Date &amp; Time</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Movement Type</th>
                        <th class="text-end">Quantity Change</th>
                        <th>Notes / Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5"><i class="fas fa-history fa-2x mb-2 text-primary"></i><br>No stock movement logs found for the selected criteria.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($logs as $idx => $l): ?>
                        <?php
                        $tInfo = $typeLabels[$l['type']] ?? ['Movement', 'secondary', 'fa-question', ''];
                        $isPlus = $tInfo[3] === '+';
                        ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td>
                                <strong class="d-block text-dark"><?php echo date('d M Y', strtotime($l['created_at'])); ?></strong>
                                <small class="text-muted"><?php echo date('h:i:s A', strtotime($l['created_at'])); ?></small>
                            </td>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($l['product_name'] ?? 'Unknown Item'); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($l['category'] ?? '-'); ?></span></td>
                            <td>
                                <span class="badge bg-<?php echo $tInfo[1]; ?> px-2 py-1">
                                    <i class="fas <?php echo $tInfo[2]; ?> me-1"></i><?php echo $tInfo[0]; ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold fs-6 <?php echo $isPlus ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $isPlus ? '+' : '-'; ?><?php echo number_format($l['quantity']); ?> <small class="text-muted fw-normal"><?php echo htmlspecialchars($l['unit'] ?? ''); ?></small>
                            </td>
                            <td class="small text-muted">
                                <?php if (!empty($l['notes'])): ?>
                                    <i class="fas fa-comment-dots me-1 text-primary"></i><?php echo htmlspecialchars($l['notes']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
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
    .page-header, .card.mb-4, .stat-card, .btn, form, .sidebar, .navbar {
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
function downloadStockHistoryPDF() {
    var element = document.getElementById('printSection');
    var opt = {
        margin:       [8, 8, 8, 8],
        filename:     'Stock_Movement_History_<?php echo date('Ymd_His'); ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, scrollY: 0 },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
