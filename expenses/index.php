<?php
$activePage = 'expenses';
$pageTitle = 'Expenses';
include __DIR__ . '/../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Expense added.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Expense updated.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Expense deleted.</div>';

$filterCat = $_GET['category'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$search = trim($_GET['search'] ?? '');

$categories = $pdo->query("SELECT id, name FROM expense_categories WHERE status = 'active' ORDER BY name")->fetchAll();

$sql = "SELECT e.*, ec.name AS category_name FROM expenses e LEFT JOIN expense_categories ec ON ec.id = e.category_id WHERE 1=1";
$params = [];
if ($filterCat !== '') { $sql .= " AND e.category_id = ?"; $params[] = $filterCat; }
if ($filterDateFrom !== '') { $sql .= " AND e.expense_date >= ?"; $params[] = $filterDateFrom; }
if ($filterDateTo !== '') { $sql .= " AND e.expense_date <= ?"; $params[] = $filterDateTo; }
if ($search !== '') { $sql .= " AND (e.description LIKE ? OR e.receipt_no LIKE ?)"; $params[] = '%' . $search . '%'; $params[] = '%' . $search . '%'; }
$sql .= " ORDER BY e.expense_date DESC, e.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$totalAmount = 0;
foreach ($expenses as $e) $totalAmount += (float)$e['amount'];

$thisMonth = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())")->fetchColumn();
$today = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = CURDATE()")->fetchColumn();
?>

<div class="page-header">
    <div></div>
    <div class="d-flex gap-2">
        <button type="button" onclick="window.print();" class="btn btn-danger fw-bold" title="Print expense list"><i class="fas fa-print me-1"></i>Print</button>
        <a href="categories/" class="btn btn-outline-warning fw-bold"><i class="fas fa-tags me-1"></i>Categories</a>
        <a href="add.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-plus me-1"></i>Add Expense</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-danger"><i class="fas fa-receipt"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalAmount, 0); ?></h5><small class="text-muted">Filtered Total</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-warning"><i class="fas fa-calendar-alt"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($thisMonth, 0); ?></h5><small class="text-muted">This Month</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-success"><i class="fas fa-clock"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($today, 0); ?></h5><small class="text-muted">Today</small></div>
        </div></div>
    </div>
</div>

<div class="card" style="border-top:3px solid #f7b731;">
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $filterCat == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterDateFrom); ?>"></div>
            <div class="col-md-2"><input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterDateTo); ?>"></div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm flex-fill fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>#</th><th>Date</th><th>Category</th><th>Description</th><th>Method</th><th class="text-end">Amount</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-receipt me-1"></i>No expenses found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($expenses as $i => $e): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo date('d M Y', strtotime($e['expense_date'])); ?></td>
                            <td><span class="badge" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;"><?php echo htmlspecialchars($e['category_name'] ?? 'Unknown'); ?></span></td>
                            <td class="small"><?php echo htmlspecialchars($e['description'] ?? '-'); ?></td>
                            <td><span class="badge bg-light text-dark"><?php echo ucfirst(str_replace('_', ' ', $e['payment_method'])); ?></span></td>
                            <td class="text-end fw-bold text-danger">Rs.<?php echo number_format($e['amount'], 0); ?></td>
                            <td class="text-end">
                                <a href="edit.php?id=<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <a href="delete.php?id=<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this expense?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;">
                        <td colspan="5" class="fw-bold"><?php echo count($expenses); ?> expense(s)</td>
                        <td class="text-end fw-bold">Rs.<?php echo number_format($totalAmount, 0); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- ===================== PRINT-ONLY SECTION ===================== -->
<?php
$catLabel = '';
if ($filterCat !== '') {
    foreach ($categories as $c) {
        if ($c['id'] == $filterCat) { $catLabel = $c['name']; break; }
    }
}
?>
<div id="printSection">

    <!-- Letterhead -->
    <div class="print-header">
        <div class="print-logo">&#9889;</div>
        <div class="print-gym-name">FITNESS GYM</div>
        <div class="print-gym-sub">Expenses Report</div>
        <div class="print-gym-meta">
            <?php
            $meta = [];
            if ($filterDateFrom !== '' || $filterDateTo !== '') {
                $meta[] = 'Period: ' . ($filterDateFrom !== '' ? date('d M Y', strtotime($filterDateFrom)) : 'Start') . ' &ndash; ' . ($filterDateTo !== '' ? date('d M Y', strtotime($filterDateTo)) : 'Now');
            } else {
                $meta[] = 'All Records';
            }
            if ($catLabel !== '')          $meta[] = 'Category: ' . htmlspecialchars($catLabel);
            if ($search !== '')            $meta[] = 'Search: &quot;' . htmlspecialchars($search) . '&quot;';
            echo implode(' &nbsp;|&nbsp; ', $meta);
            ?>
        </div>
    </div>

    <!-- Summary boxes -->
    <div class="print-summary">
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($totalAmount, 0); ?></div>
            <div class="print-summary-lbl">Filtered Total</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val"><?php echo count($expenses); ?></div>
            <div class="print-summary-lbl">Entries</div>
        </div>
        <div class="print-summary-box highlight">
            <div class="print-summary-val">Rs.<?php echo number_format($thisMonth, 0); ?></div>
            <div class="print-summary-lbl">This Month</div>
        </div>
    </div>

    <!-- Expenses table -->
    <table class="print-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Category</th>
                <th>Description</th>
                <th>Method</th>
                <th class="text-right">Amount (Rs.)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($expenses)): ?>
                <tr><td colspan="6" style="text-align:center;padding:20px;color:#666;">No expenses found.</td></tr>
            <?php endif; ?>
            <?php foreach ($expenses as $i => $e): ?>
            <tr class="<?php echo $i % 2 === 0 ? 'even' : ''; ?>">
                <td><?php echo $i + 1; ?></td>
                <td><?php echo date('d M Y', strtotime($e['expense_date'])); ?></td>
                <td><?php echo htmlspecialchars($e['category_name'] ?? 'Unknown'); ?></td>
                <td><?php echo htmlspecialchars($e['description'] ?? '-'); ?></td>
                <td><?php echo ucfirst(str_replace('_', ' ', $e['payment_method'])); ?></td>
                <td class="text-right bold"><?php echo number_format($e['amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="bold">Total — <?php echo count($expenses); ?> expense(s)</td>
                <td class="text-right bold">Rs.<?php echo number_format($totalAmount, 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Footer -->
    <div class="print-footer">
        <span>Printed on: <strong><?php echo date('d M Y, h:i A'); ?></strong></span>
        <span>Fitness Gym Management System</span>
    </div>

</div><!-- /printSection -->

<style>
/* ── Screen: hide print section ── */
#printSection { display: none; }

/* ── Print styles ── */
@media print {
    /* Hide all screen UI */
    .sidebar, .sidebar-overlay, .topbar, .hamburger,
    .page-header, .card, script { display: none !important; }

    body        { background:#fff !important; margin:0; padding:0; font-family: Arial, sans-serif; color:#000; }
    .layout-wrapper { display:block !important; }
    .main-content   { margin:0 !important; width:100% !important; min-height:unset; }
    .content        { padding:0 !important; }
    .row.g-3.mb-4   { display:none !important; }

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

    /* ── Footer ── */
    .print-footer { display:flex; justify-content:space-between; font-size:9px; color:#666; margin-top:14px; border-top:1px solid #ccc; padding-top:6px; }

    /* Page setup */
    @page { margin: 12mm 10mm; size: A4 portrait; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
