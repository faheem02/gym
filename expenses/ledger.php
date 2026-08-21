<?php
$activePage = 'expense_ledger';
$pageTitle = 'Expense Ledger';
include __DIR__ . '/../includes/header.php';

$categories = $pdo->query("SELECT id, name FROM expense_categories WHERE status = 'active' ORDER BY name")->fetchAll();
$selectedCat = (int)($_GET['cat'] ?? 0);
$filterMonth = $_GET['month'] ?? '';
$filterYear = $_GET['year'] ?? date('Y');

if ($selectedCat > 0) {
    $catStmt = $pdo->prepare('SELECT * FROM expense_categories WHERE id = ?');
    $catStmt->execute([$selectedCat]);
    $catInfo = $catStmt->fetch();
}

$allCatsTotal = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE YEAR(expense_date) = " . (int)$filterYear)->fetchColumn();

$catSummary = $pdo->prepare("
    SELECT ec.id, ec.name, COALESCE(SUM(e.amount), 0) AS total, COUNT(e.id) AS cnt
    FROM expense_categories ec
    LEFT JOIN expenses e ON e.category_id = ec.id AND YEAR(e.expense_date) = ?
    WHERE ec.status = 'active'
    GROUP BY ec.id
    ORDER BY total DESC
");
$catSummary->execute([$filterYear]);
$catSummary = $catSummary->fetchAll();

$detailExpenses = [];
$detailTotal = 0;
if ($selectedCat > 0) {
    $sql = "SELECT * FROM expenses WHERE category_id = ? AND YEAR(expense_date) = ?";
    $params = [$selectedCat, $filterYear];
    if ($filterMonth !== '') { $sql .= " AND MONTH(expense_date) = ?"; $params[] = $filterMonth; }
    $sql .= " ORDER BY expense_date DESC, id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $detailExpenses = $stmt->fetchAll();
    foreach ($detailExpenses as $de) $detailTotal += (float)$de['amount'];
}

$years = $pdo->query("SELECT DISTINCT YEAR(expense_date) AS y FROM expenses ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($years)) $years = [date('Y')];

$print = $_GET['print'] ?? '';
?>

<div class="mb-4">
    <a href="/gym/expenses/" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-danger"><i class="fas fa-receipt"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($allCatsTotal, 0); ?></h5><small class="text-muted"><?php echo $filterYear; ?> Total</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-warning"><i class="fas fa-tags"></i></div>
            <div><h5 class="mb-0 fw-bold"><?php echo count($catSummary); ?></h5><small class="text-muted">Categories</small></div>
        </div></div>
    </div>
    <?php if ($selectedCat > 0 && isset($catInfo)): ?>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-primary"><i class="fas fa-tag"></i></div>
            <div><h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($catInfo['name']); ?></h5><small class="text-muted">Selected</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-success"><i class="fas fa-coins"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($detailTotal, 0); ?></h5><small class="text-muted">Category Total</small></div>
        </div></div>
    </div>
    <?php endif; ?>
</div>

<div class="card mb-4" style="border-top:3px solid #f7b731;">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="fas fa-filter me-2" style="color:#f7b731;"></i>Filters</h6>
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Category</label>
                <select name="cat" class="form-select form-select-sm">
                    <option value="">Overview (All Categories)</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedCat == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Month</label>
                <select name="month" class="form-select form-select-sm">
                    <option value="">All Months</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $filterMonth == $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Year</label>
                <select name="year" class="form-select form-select-sm">
                    <?php foreach ($years as $y): ?>
                        <option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm flex-fill fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-filter me-1"></i>Apply</button>
                <a href="ledger.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
                <button type="button" onclick="window.print();" class="btn btn-danger btn-sm fw-bold"><i class="fas fa-print"></i></button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedCat <= 0): ?>
<div class="card" style="border-top:3px solid #f7b731;" id="printArea">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="fas fa-chart-pie me-2" style="color:#f7b731;"></i>Category Summary — <?php echo $filterYear; ?></h6>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>#</th><th>Category</th><th class="text-end">Entries</th><th class="text-end">Total (Rs.)</th><th class="text-end">% of Total</th><th></th></tr>
                </thead>
                <tbody>
                    <?php if (empty($catSummary)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-chart-bar me-1"></i>No data found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($catSummary as $i => $cs): ?>
                        <?php $pct = $allCatsTotal > 0 ? ($cs['total'] / $allCatsTotal * 100) : 0; ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td class="fw-semibold"><i class="fas fa-tag me-1" style="color:#f7b731;"></i><?php echo htmlspecialchars($cs['name']); ?></td>
                            <td class="text-end"><?php echo $cs['cnt']; ?></td>
                            <td class="text-end fw-bold text-danger">Rs.<?php echo number_format($cs['total'], 0); ?></td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <div class="progress flex-grow-0" style="width:80px;height:8px;">
                                        <div class="progress-bar" style="width:<?php echo $pct; ?>%;background:linear-gradient(135deg,#f7b731,#f5a623);"></div>
                                    </div>
                                    <span class="small fw-bold"><?php echo number_format($pct, 1); ?>%</span>
                                </div>
                            </td>
                            <td><a href="?cat=<?php echo $cs['id']; ?>&year=<?php echo $filterYear; ?>" class="btn btn-sm btn-outline-warning fw-bold">View <i class="fas fa-arrow-right ms-1"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;">
                        <td colspan="2" class="fw-bold">Total</td>
                        <td class="text-end fw-bold"><?php echo array_sum(array_column($catSummary, 'cnt')); ?></td>
                        <td class="text-end fw-bold">Rs.<?php echo number_format($allCatsTotal, 0); ?></td>
                        <td class="text-end fw-bold">100%</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card" style="border-top:3px solid #f7b731;" id="printArea">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h6 class="fw-bold mb-0"><i class="fas fa-list me-2" style="color:#f7b731;"></i><?php echo htmlspecialchars($catInfo['name'] ?? ''); ?> — <?php echo $filterYear; ?><?php echo $filterMonth ? ' / ' . date('F', mktime(0,0,0,$filterMonth,1)) : ''; ?></h6>
            <div class="fw-bold text-danger">Total: Rs.<?php echo number_format($detailTotal, 0); ?></div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>#</th><th>Date</th><th>Description</th><th>Method</th><th>Receipt</th><th class="text-end">Amount</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($detailExpenses)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-receipt me-1"></i>No expenses found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($detailExpenses as $i => $de): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo date('d M Y', strtotime($de['expense_date'])); ?></td>
                            <td class="small"><?php echo htmlspecialchars($de['description'] ?? '-'); ?></td>
                            <td><span class="badge bg-light text-dark"><?php echo ucfirst(str_replace('_', ' ', $de['payment_method'])); ?></span></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($de['receipt_no'] ?? '-'); ?></td>
                            <td class="text-end fw-bold text-danger">Rs.<?php echo number_format($de['amount'], 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;">
                        <td colspan="5" class="fw-bold"><?php echo count($detailExpenses); ?> expense(s)</td>
                        <td class="text-end fw-bold">Rs.<?php echo number_format($detailTotal, 0); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
@media print {
    body * { visibility: hidden; }
    #printArea, #printArea * { visibility: visible; }
    #printArea { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
