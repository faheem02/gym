<?php
$activePage = 'canteen_products';
$pageTitle = 'Canteen Products';
include __DIR__ . '/../../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Product added successfully.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Product updated.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Product deleted.</div>';

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM canteen_products';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE name LIKE ? OR category LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like];
}
$sql .= ' ORDER BY id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query('SELECT DISTINCT category FROM canteen_products WHERE category IS NOT NULL AND category != "" ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="search-bar">
    <div class="row g-2 align-items-center">
        <div class="col-md-7 col-lg-8">
            <form method="GET" action="" class="d-flex">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control me-2" placeholder="Search products...">
                <button class="btn btn-dark" type="submit"><i class="fas fa-search me-1"></i>Search</button>
            </form>
        </div>
        <div class="col-md-5 col-lg-4 text-md-end">
            <button type="button" onclick="window.print();" class="btn btn-danger fw-bold me-2" title="Print product list"><i class="fas fa-print me-1"></i>Print</button>
            <a href="add.php" class="btn btn-warning fw-bold"><i class="fas fa-plus me-1"></i>Add Product</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Purchase Price</th>
                    <th>Sale Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-box-open me-1"></i>No products found.</td></tr>
                <?php endif; ?>
                <?php foreach ($products as $p): ?>
                    <?php
                        $stockClass = 'text-success';
                        if ($p['stock_qty'] <= 0) $stockClass = 'text-danger fw-bold';
                        elseif ($p['stock_qty'] <= $p['min_stock']) $stockClass = 'text-warning fw-bold';
                    ?>
                    <tr>
                        <td><?php echo $p['id']; ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?></td>
                        <td><span class="badge text-bg-dark"><?php echo htmlspecialchars($p['category'] ?? '-'); ?></span></td>
                        <td><?php echo htmlspecialchars($p['unit']); ?></td>
                        <td>Rs.<?php echo number_format($p['purchase_price'], 0); ?></td>
                        <td class="fw-bold">Rs.<?php echo number_format($p['sale_price'], 0); ?></td>
                        <td class="<?php echo $stockClass; ?>"><?php echo $p['stock_qty']; ?> <?php echo htmlspecialchars($p['unit']); ?></td>
                        <td>
                            <span class="badge <?php echo $p['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo ucfirst($p['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="view.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                            <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                            <a href="delete.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this product?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===================== PRINT-ONLY SECTION ===================== -->
<?php
$listTotal = count($products);
$activeCount = 0;
$outOfStock = 0;
$stockValue = 0;
foreach ($products as $p) {
    if ($p['status'] === 'active') $activeCount++;
    if ((int)$p['stock_qty'] <= 0) $outOfStock++;
    $stockValue += (float)$p['purchase_price'] * (int)$p['stock_qty'];
}
?>
<div id="printSection">

    <!-- Letterhead -->
    <?php
    $printReportTitle = 'Products List';
    include __DIR__ . "/../../includes/print_header.php";
    ?>

    <!-- Summary boxes -->
    <div class="print-summary">
        <div class="print-summary-box">
            <div class="print-summary-val"><?php echo $listTotal; ?></div>
            <div class="print-summary-lbl">Total Products</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val"><?php echo $activeCount; ?></div>
            <div class="print-summary-lbl">Active</div>
        </div>
        <div class="print-summary-box<?php echo $outOfStock > 0 ? ' highlight' : ''; ?>">
            <div class="print-summary-val"><?php echo $outOfStock; ?></div>
            <div class="print-summary-lbl">Out of Stock</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($stockValue, 0); ?></div>
            <div class="print-summary-lbl">Stock Value</div>
        </div>
    </div>

    <!-- Products table -->
    <table class="print-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Category</th>
                <th>Unit</th>
                <th class="text-right">Purchase (Rs.)</th>
                <th class="text-right">Sale (Rs.)</th>
                <th class="text-right">Stock</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="8" style="text-align:center;padding:20px;color:#666;">No products found.</td></tr>
            <?php endif; ?>
            <?php foreach ($products as $i => $p):
                if ((int)$p['stock_qty'] <= 0) { $stockNote = 'OUT'; }
                elseif ((int)$p['stock_qty'] <= (int)$p['min_stock']) { $stockNote = 'LOW'; }
                else { $stockNote = ''; }
            ?>
            <tr class="<?php echo $i % 2 === 0 ? 'even' : ''; ?>">
                <td><?php echo $i + 1; ?></td>
                <td><?php echo htmlspecialchars($p['name']); ?></td>
                <td><?php echo htmlspecialchars($p['category'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($p['unit']); ?></td>
                <td class="text-right"><?php echo number_format($p['purchase_price'], 2); ?></td>
                <td class="text-right"><?php echo number_format($p['sale_price'], 2); ?></td>
                <td class="text-right bold"><?php echo $p['stock_qty'] . ' ' . htmlspecialchars($p['unit']); ?><?php echo $stockNote !== '' ? ' <span class="stock-flag">(' . $stockNote . ')</span>' : ''; ?></td>
                <td><?php echo ucfirst($p['status']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="bold">Totals — <?php echo $listTotal; ?> product(s)</td>
                <td colspan="3" class="text-right bold">Stock Value: Rs.<?php echo number_format($stockValue, 2); ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <!-- Footer -->
    <?php include __DIR__ . "/../../includes/print_footer.php"; ?>

</div><!-- /printSection -->

<style>
/* ── Screen: hide print section ── */
#printSection { display: none; }

/* ── Print styles ── */
@media print {
    /* Hide all screen UI */
    .sidebar, .sidebar-overlay, .topbar, .hamburger,
    .search-bar, .no-print, .alert,
    .card, script { display: none !important; }

    body        { background:#fff !important; margin:0; padding:0; font-family: Arial, sans-serif; color:#000; }
    .layout-wrapper { display:block !important; }
    .main-content   { margin:0 !important; width:100% !important; min-height:unset; }
    .content        { padding:0 !important; }

    /* Show print section */
    #printSection { display:block !important; padding: 18px 24px; }

    /* ── Letterhead ── */
    .print-header        { text-align:center; border-bottom:3px solid #1a1a2e; padding-bottom:10px; margin-bottom:14px; }
    .print-logo          { font-size:28px; color:#f7b731; margin-bottom:2px; }
    .print-gym-name      { font-size:20px; font-weight:900; letter-spacing:3px; color:#1a1a2e; }
    .print-gym-sub       { font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#555; margin-top:2px; }
    .print-gym-meta      { font-size:10px; color:#444; margin-top:6px; }

    /* ── Summary boxes ── */
    .print-summary       { display:flex; gap:0; border:1px solid #1a1a2e; margin-bottom:14px; }
    .print-summary-box   { flex:1; text-align:center; padding:8px 4px; border-right:1px solid #1a1a2e; }
    .print-summary-box:last-child { border-right:none; }
    .print-summary-box.highlight  { background:#1a1a2e; color:#fff; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .print-summary-val   { font-size:14px; font-weight:700; }
    .print-summary-lbl   { font-size:9px; text-transform:uppercase; letter-spacing:1px; color:#666; margin-top:2px; }
    .print-summary-box.highlight .print-summary-lbl { color:#ccc; }

    /* ── Table ── */
    .print-table         { width:100%; border-collapse:collapse; font-size:11px; }
    .print-table thead tr{ background:#1a1a2e; color:#fff; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .print-table thead th{ padding:7px 8px; text-align:left; font-weight:700; font-size:10px; letter-spacing:0.5px; }
    .print-table tbody tr td { padding:6px 8px; border-bottom:1px solid #e0e0e0; vertical-align:middle; }
    .print-table tbody tr.even td { background:#f9f9f9; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .print-table tfoot tr td { padding:7px 8px; background:#f0f0f0; font-weight:700; border-top:2px solid #1a1a2e; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .print-table .text-right { text-align:right; }
    .print-table .bold       { font-weight:700; }
    .print-table .stock-flag { font-size:8.5px; color:#c0392b; font-weight:700; }

    /* ── Footer ── */
    .print-footer { display:flex; justify-content:space-between; font-size:9px; color:#666; margin-top:14px; border-top:1px solid #ccc; padding-top:6px; }

    /* Page setup */
    @page { margin: 12mm 10mm; size: A4 portrait; }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
