<?php
$activePage = 'profit_loss';
$pageTitle = 'Profit & Loss';
include __DIR__ . '/../includes/header.php';

// --- Date filter defaults: current month ---
$defaultFrom = date('Y-m-01');
$defaultTo   = date('Y-m-d');

$dateFrom = $_GET['date_from'] ?? $defaultFrom;
$dateTo   = $_GET['date_to']   ?? $defaultTo;

// Sanitise – keep only valid date strings
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = $defaultFrom;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = $defaultTo;

// -------------------------------------------------------
// INCOME SOURCES
// -------------------------------------------------------

// 1. Member Payments
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM member_payments WHERE payment_date BETWEEN ? AND ?");
$stmt->execute([$dateFrom, $dateTo]);
$memberPayments = (float)$stmt->fetchColumn();

// 2. Canteen Sales (final_amount = after discount, correct column name)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(final_amount),0) AS total FROM canteen_sales WHERE sale_date BETWEEN ? AND ?");
$stmt->execute([$dateFrom, $dateTo]);
$canteenSales = (float)$stmt->fetchColumn();

// 3. Day Pass Revenue
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM day_passes WHERE pass_date BETWEEN ? AND ?");
$stmt->execute([$dateFrom, $dateTo]);
$dayPassRevenue = (float)$stmt->fetchColumn();

$totalIncome = $memberPayments + $canteenSales + $dayPassRevenue;

// -------------------------------------------------------
// EXPENSE SOURCES
// -------------------------------------------------------

// 1. General Expenses (by category for breakdown)
$stmt = $pdo->prepare("
    SELECT ec.name AS category, COALESCE(SUM(e.amount),0) AS total
    FROM expenses e
    LEFT JOIN expense_categories ec ON ec.id = e.category_id
    WHERE e.expense_date BETWEEN ? AND ?
    GROUP BY ec.id, ec.name
    ORDER BY total DESC
");
$stmt->execute([$dateFrom, $dateTo]);
$expensesByCategory = $stmt->fetchAll();
$totalGeneralExpenses = array_sum(array_column($expensesByCategory, 'total'));

// 2. Staff Salaries paid (graceful — table may not exist on all deployments)
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM staff_salaries WHERE payment_date BETWEEN ? AND ?");
    $stmt->execute([$dateFrom, $dateTo]);
    $staffSalaries = (float)$stmt->fetchColumn();
} catch (PDOException $e) {
    $staffSalaries = 0; // table not yet created — run migrations/create_staff_salaries.sql
}

// prev period staff salaries

// 3. Cost of Goods Sold (purchase rate of products actually sold)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(csi.quantity * cp.purchase_price),0) AS total
    FROM canteen_sale_items csi
    JOIN canteen_products cp ON cp.id = csi.product_id
    JOIN canteen_sales cs ON cs.id = csi.sale_id
    WHERE cs.sale_date BETWEEN ? AND ?
");
$stmt->execute([$dateFrom, $dateTo]);
$cogs = (float)$stmt->fetchColumn();

$totalExpenses = $totalGeneralExpenses + $staffSalaries + $cogs;

// -------------------------------------------------------
// GROSS PROFIT on Canteen (Sales - COGS)
// -------------------------------------------------------
$canteenGrossProfit = $canteenSales - $cogs;

// -------------------------------------------------------
// NET PROFIT / LOSS
// -------------------------------------------------------
$netProfit = $totalIncome - $totalExpenses;

// -------------------------------------------------------
// Month-over-month quick stat: previous period same length
// -------------------------------------------------------
$periodDays = (strtotime($dateTo) - strtotime($dateFrom)) / 86400;
$prevFrom = date('Y-m-d', strtotime($dateFrom) - ($periodDays + 1) * 86400);
$prevTo   = date('Y-m-d', strtotime($dateFrom) - 86400);

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM member_payments WHERE payment_date BETWEEN ? AND ?");
$stmt->execute([$prevFrom, $prevTo]);
$prevMemberPayments = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(final_amount),0) FROM canteen_sales WHERE sale_date BETWEEN ? AND ?");
$stmt->execute([$prevFrom, $prevTo]);
$prevCanteenSales = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM day_passes WHERE pass_date BETWEEN ? AND ?");
$stmt->execute([$prevFrom, $prevTo]);
$prevDayPasses = (float)$stmt->fetchColumn();

$prevTotalIncome = $prevMemberPayments + $prevCanteenSales + $prevDayPasses;

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?");
$stmt->execute([$prevFrom, $prevTo]);
$prevExpenses = (float)$stmt->fetchColumn();

try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM staff_salaries WHERE payment_date BETWEEN ? AND ?");
    $stmt->execute([$prevFrom, $prevTo]);
    $prevSalaries = (float)$stmt->fetchColumn();
} catch (PDOException $e) {
    $prevSalaries = 0;
}

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(csi.quantity * cp.purchase_price),0) AS total
    FROM canteen_sale_items csi
    JOIN canteen_products cp ON cp.id = csi.product_id
    JOIN canteen_sales cs ON cs.id = csi.sale_id
    WHERE cs.sale_date BETWEEN ? AND ?
");
$stmt->execute([$prevFrom, $prevTo]);
$prevCogs = (float)$stmt->fetchColumn();

$prevTotalExpenses = $prevExpenses + $prevSalaries + $prevCogs;
$prevNetProfit = $prevTotalIncome - $prevTotalExpenses;

function pctChange($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round(($current - $previous) / $previous * 100, 1);
}
$incomePct  = pctChange($totalIncome, $prevTotalIncome);
$expensePct = pctChange($totalExpenses, $prevTotalExpenses);
$profitPct  = pctChange($netProfit, $prevNetProfit);
?>

<!-- Filter Bar -->
<div class="card mb-4" style="border-top:3px solid #f7b731;">
    <div class="card-body py-2">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            <div class="col-auto d-flex flex-wrap align-items-center gap-1">
                <button type="submit" class="btn btn-dark btn-sm fw-semibold">
                    <i class="fas fa-filter me-1"></i>Apply
                </button>
                <a href="profit_loss.php" class="btn btn-outline-secondary btn-sm" title="Reset filters">
                    <i class="fas fa-times"></i>
                </a>
                <button type="button" onclick="downloadProfitLossPDF();" class="btn btn-primary btn-sm fw-bold">
                    <i class="fas fa-file-pdf me-1"></i>Download PDF
                </button>
                <button type="button" onclick="window.print();" class="btn btn-danger btn-sm fw-bold">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
            <!-- Quick range shortcuts -->
            <div class="col-auto ms-auto d-flex gap-1 flex-wrap">
                <?php
                $shortcuts = [
                    'Today'       => [date('Y-m-d'), date('Y-m-d')],
                    'This Week'   => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
                    'This Month'  => [date('Y-m-01'), date('Y-m-d')],
                    'Last Month'  => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))],
                    'This Year'   => [date('Y-01-01'), date('Y-m-d')],
                ];
                foreach ($shortcuts as $label => [$f, $t]):
                    $active = ($dateFrom === $f && $dateTo === $t);
                ?>
                    <a href="profit_loss.php?date_from=<?php echo $f; ?>&date_to=<?php echo $t; ?>"
                       class="btn btn-sm <?php echo $active ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                        <?php echo $label; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</div>

<!-- Summary Stat Cards -->
<div class="row g-3 mb-4" id="printArea">

    <div class="col-6 col-lg">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success"><i class="fas fa-arrow-up"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold text-success">Rs.<?php echo number_format($totalIncome, 0); ?></h5>
                    <small class="text-muted">Total Income</small>
                    <?php if ($incomePct !== 0): ?>
                        <div class="small <?php echo $incomePct >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <i class="fas fa-<?php echo $incomePct >= 0 ? 'caret-up' : 'caret-down'; ?>"></i>
                            <?php echo abs($incomePct); ?>% vs prev period
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger"><i class="fas fa-arrow-down"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold text-danger">Rs.<?php echo number_format($totalExpenses, 0); ?></h5>
                    <small class="text-muted">Total Expenses</small>
                    <?php if ($expensePct !== 0): ?>
                        <div class="small <?php echo $expensePct <= 0 ? 'text-success' : 'text-danger'; ?>">
                            <i class="fas fa-<?php echo $expensePct >= 0 ? 'caret-up' : 'caret-down'; ?>"></i>
                            <?php echo abs($expensePct); ?>% vs prev period
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg">
        <div class="card stat-card h-100" style="<?php echo $netProfit >= 0 ? 'border-left:4px solid #28a745;' : 'border-left:4px solid #dc3545;'; ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:linear-gradient(135deg,<?php echo $netProfit >= 0 ? '#28a745,#20c997' : '#dc3545,#c82333'; ?>);color:#fff;">
                    <i class="fas fa-<?php echo $netProfit >= 0 ? 'chart-line' : 'chart-line'; ?>"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold <?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo $netProfit < 0 ? '-' : ''; ?>Rs.<?php echo number_format(abs($netProfit), 0); ?>
                    </h5>
                    <small class="text-muted"><?php echo $netProfit >= 0 ? 'Net Profit' : 'Net Loss'; ?></small>
                    <?php if ($profitPct !== 0): ?>
                        <div class="small <?php echo $profitPct >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <i class="fas fa-<?php echo $profitPct >= 0 ? 'caret-up' : 'caret-down'; ?>"></i>
                            <?php echo abs($profitPct); ?>% vs prev period
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;">
                    <i class="fas fa-shopping-basket"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold <?php echo $canteenGrossProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                        Rs.<?php echo number_format($canteenGrossProfit, 0); ?>
                    </h5>
                    <small class="text-muted">Canteen Gross Profit</small>
                    <div class="small text-muted">Sales − COGS</div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- P&L Statement Table -->
<div class="row g-4">

    <!-- Income -->
    <div class="col-lg-6">
        <div class="card h-100" style="border-top:3px solid #28a745;">
            <div class="card-body">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-arrow-circle-up text-success me-2"></i>Income
                    <small class="text-muted fw-normal ms-1"><?php echo date('d M Y', strtotime($dateFrom)); ?> – <?php echo date('d M Y', strtotime($dateTo)); ?></small>
                </h6>
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Source</th>
                            <th class="text-end">Amount (Rs.)</th>
                            <th class="text-end">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $incomeSources = [
                            ['icon' => 'fa-id-card', 'color' => 'text-primary',   'label' => 'Member Payments',  'amount' => $memberPayments],
                            ['icon' => 'fa-receipt',  'color' => 'text-warning',   'label' => 'Canteen Sales',    'amount' => $canteenSales],
                            ['icon' => 'fa-ticket-alt','color'=> 'text-info',      'label' => 'Day Passes',       'amount' => $dayPassRevenue],
                        ];
                        foreach ($incomeSources as $src):
                            $pct = $totalIncome > 0 ? round($src['amount'] / $totalIncome * 100, 1) : 0;
                        ?>
                        <tr>
                            <td>
                                <i class="fas <?php echo $src['icon']; ?> <?php echo $src['color']; ?> me-2"></i>
                                <?php echo $src['label']; ?>
                            </td>
                            <td class="text-end fw-semibold"><?php echo number_format($src['amount'], 2); ?></td>
                            <td class="text-end">
                                <span class="badge bg-success bg-opacity-10 text-success"><?php echo $pct; ?>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;">
                            <td class="fw-bold">Total Income</td>
                            <td class="text-end fw-bold">Rs.<?php echo number_format($totalIncome, 2); ?></td>
                            <td class="text-end fw-bold">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Expenses -->
    <div class="col-lg-6">
        <div class="card h-100" style="border-top:3px solid #dc3545;">
            <div class="card-body">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-arrow-circle-down text-danger me-2"></i>Expenses
                    <small class="text-muted fw-normal ms-1"><?php echo date('d M Y', strtotime($dateFrom)); ?> – <?php echo date('d M Y', strtotime($dateTo)); ?></small>
                </h6>
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Category</th>
                            <th class="text-end">Amount (Rs.)</th>
                            <th class="text-end">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Staff Salaries -->
                        <?php $salPct = $totalExpenses > 0 ? round($staffSalaries / $totalExpenses * 100, 1) : 0; ?>
                        <tr>
                            <td><i class="fas fa-id-badge text-primary me-2"></i>Staff Salaries</td>
                            <td class="text-end fw-semibold"><?php echo number_format($staffSalaries, 2); ?></td>
                            <td class="text-end">
                                <span class="badge bg-danger bg-opacity-10 text-danger"><?php echo $salPct; ?>%</span>
                            </td>
                        </tr>
                        <!-- Cost of Goods Sold -->
                        <?php $purPct = $totalExpenses > 0 ? round($cogs / $totalExpenses * 100, 1) : 0; ?>
                        <tr>
                            <td><i class="fas fa-shopping-cart text-warning me-2"></i>Cost of Goods Sold</td>
                            <td class="text-end fw-semibold"><?php echo number_format($cogs, 2); ?></td>
                            <td class="text-end">
                                <span class="badge bg-danger bg-opacity-10 text-danger"><?php echo $purPct; ?>%</span>
                            </td>
                        </tr>
                        <!-- General Expenses by Category -->
                        <?php foreach ($expensesByCategory as $cat):
                            $catPct = $totalExpenses > 0 ? round($cat['total'] / $totalExpenses * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><i class="fas fa-tag text-danger me-2"></i><?php echo htmlspecialchars($cat['category'] ?? 'Uncategorised'); ?></td>
                            <td class="text-end fw-semibold"><?php echo number_format($cat['total'], 2); ?></td>
                            <td class="text-end">
                                <span class="badge bg-danger bg-opacity-10 text-danger"><?php echo $catPct; ?>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
<?php if (empty($expensesByCategory) && $staffSalaries == 0 && $cogs == 0): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No expenses recorded for this period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;">
                            <td class="fw-bold">Total Expenses</td>
                            <td class="text-end fw-bold">Rs.<?php echo number_format($totalExpenses, 2); ?></td>
                            <td class="text-end fw-bold">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Net Profit / Loss Summary Banner -->
<div class="card mt-4" style="border-top:4px solid <?php echo $netProfit >= 0 ? '#28a745' : '#dc3545'; ?>;">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h5 class="fw-bold mb-1">
                    <?php if ($netProfit >= 0): ?>
                        <i class="fas fa-check-circle text-success me-2"></i>Net Profit for the period
                    <?php else: ?>
                        <i class="fas fa-exclamation-circle text-danger me-2"></i>Net Loss for the period
                    <?php endif; ?>
                </h5>
                <p class="text-muted mb-0 small">
                    Period: <strong><?php echo date('d M Y', strtotime($dateFrom)); ?></strong> to
                    <strong><?php echo date('d M Y', strtotime($dateTo)); ?></strong>
                    &nbsp;|&nbsp; Total Income: <strong class="text-success">Rs.<?php echo number_format($totalIncome, 2); ?></strong>
                    &nbsp;−&nbsp; Total Expenses: <strong class="text-danger">Rs.<?php echo number_format($totalExpenses, 2); ?></strong>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-2 mt-md-0">
                <h2 class="fw-bold mb-0 <?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo $netProfit < 0 ? '−' : '+'; ?>Rs.<?php echo number_format(abs($netProfit), 2); ?>
                </h2>
                <?php if ($totalIncome > 0): ?>
                    <small class="text-muted">Profit margin: <?php echo round($netProfit / $totalIncome * 100, 1); ?>%</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===================== PRINT-ONLY SECTION ===================== -->
<div id="printSection">

    <!-- Letterhead -->
    <?php
    $printReportTitle = 'Profit & Loss Statement';
    $printMeta = 'Period: <strong>' . date('d M Y', strtotime($dateFrom)) . '</strong> &ndash; <strong>' . date('d M Y', strtotime($dateTo)) . '</strong>';
    include __DIR__ . '/../includes/print_header.php';
    ?>

    <!-- Summary boxes -->
    <div class="print-summary">
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($totalIncome, 0); ?></div>
            <div class="print-summary-lbl">Total Income</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($totalExpenses, 0); ?></div>
            <div class="print-summary-lbl">Total Expenses</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($canteenGrossProfit, 0); ?></div>
            <div class="print-summary-lbl">Canteen Gross Profit</div>
        </div>
        <div class="print-summary-box highlight">
            <div class="print-summary-val"><?php echo $netProfit < 0 ? '-' : ''; ?>Rs.<?php echo number_format(abs($netProfit), 0); ?></div>
            <div class="print-summary-lbl"><?php echo $netProfit >= 0 ? 'Net Profit' : 'Net Loss'; ?></div>
        </div>
    </div>

    <!-- Income -->
    <div class="print-section-title income"><i>&#8593;</i> Income</div>
    <table class="print-table">
        <thead>
            <tr>
                <th>Source</th>
                <th class="text-right">Amount (Rs.)</th>
                <th class="text-right">%</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($incomeSources as $src): ?>
            <tr>
                <td><?php echo htmlspecialchars($src['label']); ?></td>
                <td class="text-right"><?php echo number_format($src['amount'], 2); ?></td>
                <td class="text-right"><?php echo $totalIncome > 0 ? round($src['amount'] / $totalIncome * 100, 1) : 0; ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td class="bold">Total Income</td>
                <td class="text-right bold"><?php echo number_format($totalIncome, 2); ?></td>
                <td class="text-right bold">100%</td>
            </tr>
        </tfoot>
    </table>

    <!-- Expenses -->
    <div class="print-section-title expense"><i>&#8595;</i> Expenses</div>
    <table class="print-table">
        <thead>
            <tr>
                <th>Category</th>
                <th class="text-right">Amount (Rs.)</th>
                <th class="text-right">%</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Staff Salaries</td>
                <td class="text-right"><?php echo number_format($staffSalaries, 2); ?></td>
                <td class="text-right"><?php echo $totalExpenses > 0 ? round($staffSalaries / $totalExpenses * 100, 1) : 0; ?>%</td>
            </tr>
            <tr>
                <td>Cost of Goods Sold</td>
                <td class="text-right"><?php echo number_format($cogs, 2); ?></td>
                <td class="text-right"><?php echo $totalExpenses > 0 ? round($cogs / $totalExpenses * 100, 1) : 0; ?>%</td>
            </tr>
            <?php foreach ($expensesByCategory as $cat): ?>
            <tr>
                <td><?php echo htmlspecialchars($cat['category'] ?? 'Uncategorised'); ?></td>
                <td class="text-right"><?php echo number_format($cat['total'], 2); ?></td>
                <td class="text-right"><?php echo $totalExpenses > 0 ? round($cat['total'] / $totalExpenses * 100, 1) : 0; ?>%</td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($expensesByCategory) && $staffSalaries == 0 && $cogs == 0): ?>
            <tr><td colspan="3" style="text-align:center;padding:14px;color:#666;">No expenses recorded for this period.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td class="bold">Total Expenses</td>
                <td class="text-right bold"><?php echo number_format($totalExpenses, 2); ?></td>
                <td class="text-right bold">100%</td>
            </tr>
        </tfoot>
    </table>

    <!-- Net result banner -->
    <div class="print-net <?php echo $netProfit >= 0 ? 'pos' : 'neg'; ?>">
        <span><?php echo $netProfit >= 0 ? 'NET PROFIT' : 'NET LOSS'; ?></span>
        <span class="amt"><?php echo $netProfit < 0 ? '-' : '+'; ?>Rs.<?php echo number_format(abs($netProfit), 2); ?></span>
        <?php if ($totalIncome > 0): ?>
            <span class="margin">(Margin: <?php echo round($netProfit / $totalIncome * 100, 1); ?>%)</span>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/../includes/print_footer.php'; ?>

</div><!-- /printSection -->

<style>
/* ── Screen: hide print section ── */
#printSection {
    display: none;
    background: #ffffff;
    color: #111111;
    font-family: Arial, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* ── Print & PDF Styles ── */
#printSection .print-header {
    text-align: center;
    border-bottom: 2px solid #1a1a2e;
    padding-bottom: 12px;
    margin-bottom: 16px;
}
#printSection .print-logo { margin-bottom: 6px; }
#printSection .print-logo img {
    height: 55px;
    width: auto;
    display: inline-block;
    object-fit: contain;
    filter: brightness(0);
    -webkit-filter: brightness(0);
}
#printSection .print-gym-name {
    font-size: 20px;
    font-weight: 800;
    letter-spacing: 2px;
    color: #1a1a2e;
    text-transform: uppercase;
    margin-top: 2px;
}
#printSection .print-gym-contact { font-size: 11px; color: #333333; margin-top: 3px; }
#printSection .print-gym-address { font-size: 10.5px; color: #555555; margin-top: 2px; }
#printSection .print-gym-sub {
    font-size: 12.5px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: #1a1a2e;
    font-weight: 700;
    margin-top: 8px;
    padding: 3px 0;
    border-top: 1px dashed #cccccc;
    border-bottom: 1px dashed #cccccc;
}
#printSection .print-gym-meta { font-size: 11px; color: #333333; margin-top: 5px; }

/* ── Summary boxes ── */
#printSection .print-summary {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
}
#printSection .print-summary-box {
    flex: 1;
    text-align: center;
    padding: 10px 8px;
    border: 1px solid #1a1a2e;
    border-radius: 4px;
    background: #fdfdfd;
}
#printSection .print-summary-box.highlight {
    background: #1a1a2e !important;
    color: #ffffff !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
#printSection .print-summary-val { font-size: 14px; font-weight: 700; }
#printSection .print-summary-lbl {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #666666;
    margin-top: 3px;
}
#printSection .print-summary-box.highlight .print-summary-lbl { color: #dddddd !important; }

/* ── Section titles ── */
#printSection .print-section-title {
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 6px 10px;
    margin: 14px 0 8px;
    border-left: 4px solid #999999;
    background: #f3f4f6;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
#printSection .print-section-title.income { border-left-color: #28a745; }
#printSection .print-section-title.expense { border-left-color: #dc3545; }

/* ── Table ── */
#printSection .print-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    margin-bottom: 16px;
}
#printSection .print-table thead tr {
    background: #1a1a2e !important;
    color: #ffffff !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
#printSection .print-table thead th {
    padding: 8px 10px;
    text-align: left;
    font-weight: 700;
    font-size: 10.5px;
    letter-spacing: 0.5px;
    border: 1px solid #1a1a2e;
    color: #ffffff;
}
#printSection .print-table tbody tr td {
    padding: 7px 10px;
    border: 1px solid #e0e0e0;
    vertical-align: middle;
}
#printSection .print-table tbody tr.even td {
    background: #f9fafb !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
#printSection .print-table tfoot tr td {
    padding: 8px 10px;
    background: #f3f4f6 !important;
    font-weight: 700;
    border: 1px solid #d1d5db;
    border-top: 2px solid #1a1a2e;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
#printSection .print-table .text-right { text-align: right; }
#printSection .print-table .bold { font-weight: 700; }

/* ── Net result banner ── */
#printSection .print-net {
    display: flex;
    align-items: center;
    gap: 10px;
    border: 2px solid #28a745;
    padding: 10px 14px;
    margin-top: 18px;
    font-size: 13px;
    font-weight: 900;
    letter-spacing: 1px;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
#printSection .print-net.neg { border-color: #dc3545; color: #dc3545; }
#printSection .print-net.pos { border-color: #28a745; color: #28a745; }
#printSection .print-net .amt { font-size: 16px; }
#printSection .print-net .margin { font-size: 9.5px; font-weight: 400; color: #555; letter-spacing: 0; }

/* ── Footer ── */
#printSection .print-footer {
    display: flex;
    justify-content: space-between;
    font-size: 9.5px;
    color: #666666;
    margin-top: 16px;
    border-top: 1px solid #cccccc;
    padding-top: 8px;
}

/* ── Print Media ── */
@media print {
    .sidebar, .sidebar-overlay, .topbar, .hamburger,
    .search-bar, .no-print, .alert,
    form, .row.g-3.mb-4, .card, script { display: none !important; }

    body { background: #fff !important; margin: 0; padding: 0; font-family: Arial, sans-serif; color: #000; }
    .layout-wrapper { display: block !important; }
    .main-content { margin: 0 !important; width: 100% !important; min-height: unset; }
    .content { padding: 0 !important; }

    #printSection { display: block !important; padding: 18px 24px; }
    @page { margin: 12mm 10mm; size: A4 portrait; }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadProfitLossPDF() {
    var printSection = document.getElementById('printSection');
    if (!printSection) return;

    var btn = event && event.target ? event.target.closest('button') : null;
    var originalHTML = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating PDF...';
    }

    printSection.style.display = 'block';

    var opt = {
        margin:       [8, 8, 8, 8],
        filename:     'Profit_Loss_Statement_<?php echo $dateFrom; ?>_to_<?php echo $dateTo; ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, letterRendering: true, scrollY: 0 },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(printSection).save().then(function() {
        printSection.style.display = 'none';
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    }).catch(function(err) {
        console.error('PDF error:', err);
        printSection.style.display = 'none';
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
