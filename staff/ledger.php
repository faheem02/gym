<?php
$activePage = 'staff_ledger';
$pageTitle = 'Staff Salary Ledger';
include __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$allStaff = $pdo->query('SELECT id, name, phone, role FROM staff ORDER BY name')->fetchAll();

if ($id <= 0) {
    ?>
    <div class="row justify-content-center">
        <div class="col-lg-6">


<div class="card" style="border-top:3px solid #f7b731;">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="stat-icon mx-auto mb-3" style="width:60px;height:60px;font-size:1.5rem;background:linear-gradient(135deg,#f7b731,#f5a623);box-shadow:0 4px 15px rgba(247,183,49,0.3);color:#fff;"><i class="fas fa-book"></i></div>
                        <h5 class="fw-bold mb-1">Staff Salary Ledger</h5>
                        <p class="text-muted mb-0">Select a staff member to view their salary &amp; payment history</p>
                    </div>
                    <form method="GET" action="">
                        <div class="mb-3">
                            <label class="form-label fw-semibold"><i class="fas fa-id-badge me-1 text-muted"></i>Select Staff</label>
                            <select name="id" class="form-select form-select-lg" required id="ledgerStaff">
                                <option value="">-- Choose Staff --</option>
                                <?php foreach ($allStaff as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?> (<?php echo ucfirst($s['role']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-lg fw-bold w-100" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-right me-1"></i>View Ledger</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ?');
$stmt->execute([$id]);
$staffMember = $stmt->fetch();

if (!$staffMember) {
    echo '<div class="alert alert-warning">Staff member not found. <a href="ledger.php">Back</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$transactions = [];

// Monthly salary earned (debits) from join month up to current month
try {
    $start = new DateTime(date('Y-m-01', strtotime($staffMember['join_date'])));
} catch (Exception $e) {
    $start = new DateTime(date('Y-m-01'));
}
$end = new DateTime(date('Y-m-01'));
for ($d = $start; $d <= $end; $d->modify('+1 month')) {
    $monthStr = $d->format('Y-m');
    $transactions[] = [
        'date' => $d->format('Y-m-d'),
        'type' => 'salary',
        'description' => 'Salary — ' . date('M Y', strtotime($monthStr . '-01')) . ' (' . ucfirst($staffMember['role']) . ')',
        'debit' => (float)$staffMember['salary'],
        'credit' => 0,
        'time' => $monthStr,
    ];
}

// Salary & advance payments made (credits)
$stmt = $pdo->prepare('SELECT amount, payment_type, salary_month, payment_date, payment_method, notes FROM staff_salaries WHERE staff_id = ? ORDER BY payment_date DESC, id DESC');
$stmt->execute([$id]);
foreach ($stmt->fetchAll() as $p) {
    $isAdvance = ($p['payment_type'] ?? 'salary') === 'advance';
    $transactions[] = [
        'date' => $p['payment_date'],
        'type' => $isAdvance ? 'advance' : 'payment',
        'description' => ($isAdvance ? 'Advance Payment' : 'Salary Payment') . ' (' . date('M Y', strtotime($p['salary_month'] . '-01')) . ')'
            . ' — ' . ucfirst(str_replace('_', ' ', $p['payment_method']))
            . ($p['notes'] ? ' - ' . $p['notes'] : ''),
        'debit' => 0,
        'credit' => (float)$p['amount'],
        'time' => $p['payment_date'],
    ];
}

usort($transactions, function($a, $b) {
    $d = strcmp($b['date'], $a['date']);
    if ($d === 0) return strcmp($b['time'], $a['time']);
    return $d;
});

$runningBalance = 0;
$displayTransactions = [];
foreach ($transactions as $t) {
    $runningBalance += $t['debit'] - $t['credit'];
    $displayTransactions[] = array_merge($t, ['balance' => $runningBalance]);
}
$displayTransactions = array_reverse($displayTransactions);

$filtered = $displayTransactions;
if ($dateFrom !== '') {
    $filtered = array_filter($filtered, function($t) use ($dateFrom) {
        return $t['date'] >= $dateFrom;
    });
}
if ($dateTo !== '') {
    $filtered = array_filter($filtered, function($t) use ($dateTo) {
        return $t['date'] <= $dateTo;
    });
}
$filtered = array_values($filtered);

$totalDebit = 0;
$totalCredit = 0;
foreach ($filtered as $f) {
    $totalDebit += $f['debit'];
    $totalCredit += $f['credit'];
}
$balance = $totalDebit - $totalCredit;

$thisMonth = date('Y-m');
$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) AS paid FROM staff_salaries WHERE staff_id = ? AND salary_month = ?');
$stmt->execute([$id, $thisMonth]);
$paidThisMonth = (float)$stmt->fetch()['paid'];
?>

<div class="mb-4">
    <a href="ledger.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary"><i class="fas fa-id-badge"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($staffMember['name']); ?></h5>
                    <small class="text-muted"><?php echo ucfirst($staffMember['role']); ?> &middot; Rs.<?php echo number_format($staffMember['salary'], 0); ?>/mo</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon <?php echo $balance > 0 ? 'bg-danger' : 'bg-success'; ?>"><i class="fas fa-balance-scale"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format(abs($balance), 0); ?></h5>
                    <small class="text-muted"><?php echo $balance > 0 ? 'Outstanding Due' : ($balance < 0 ? 'Advance Paid' : 'Settled'); ?></small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning"><i class="fas fa-file-invoice-dollar"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalDebit, 0); ?></h5>
                    <small class="text-muted">Total Salary Earned</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success"><i class="fas fa-check-circle"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalCredit, 0); ?></h5>
                    <small class="text-muted">Total Paid</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($balance > 0): ?>
<div class="alert alert-danger d-flex align-items-center gap-3 mb-4">
    <i class="fas fa-exclamation-circle fa-2x"></i>
    <div>
        <strong><?php echo htmlspecialchars($staffMember['name']); ?></strong> has an outstanding balance of
        <strong>Rs.<?php echo number_format($balance, 0); ?></strong> &mdash; This month paid:
        <strong>Rs.<?php echo number_format($paidThisMonth, 0); ?></strong> of Rs.<?php echo number_format($staffMember['salary'], 0); ?>
    </div>
</div>
<?php endif; ?>

<!-- Screen Card -->
<div class="card" style="border-top:3px solid #f7b731;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h6 class="fw-bold mb-0"><i class="fas fa-book me-2" style="color:#f7b731;"></i>Ledger &mdash; <?php echo htmlspecialchars($staffMember['name']); ?></h6>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <form method="GET" action="" class="d-flex gap-1 align-items-center">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateFrom); ?>" title="From date">
                    <span class="text-muted small">to</span>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateTo); ?>" title="To date">
                    <button class="btn btn-dark btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
                    <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
                        <a href="ledger.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary btn-sm" title="Clear filter"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
                <a href="salaries.php" class="btn btn-success fw-bold btn-sm no-print"><i class="fas fa-plus me-1"></i>Record Payment</a>
                <button onclick="window.print();" class="btn btn-danger fw-bold btn-sm no-print"><i class="fas fa-print me-1"></i>Print</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th class="text-end">Debit (Earned)</th>
                        <th class="text-end">Credit (Paid)</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filtered)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-book me-1"></i>No transactions found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($filtered as $t): ?>
                        <?php $bal = $t['balance']; ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($t['date'])); ?></td>
                            <td class="fw-semibold">
                                <?php if ($t['type'] === 'salary'): ?>
                                    <i class="fas fa-file-invoice-dollar text-warning me-1"></i>
                                <?php elseif ($t['type'] === 'advance'): ?>
                                    <i class="fas fa-hand-holding-usd text-warning me-1"></i>
                                <?php else: ?>
                                    <i class="fas fa-money-bill-wave text-success me-1"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($t['description']); ?>
                            </td>
                            <td class="text-end"><?php echo $t['debit'] > 0 ? '<span class="text-danger">Rs.' . number_format($t['debit'], 0) . '</span>' : '-'; ?></td>
                            <td class="text-end"><?php echo $t['credit'] > 0 ? '<span class="text-success">Rs.' . number_format($t['credit'], 0) . '</span>' : '-'; ?></td>
                            <td class="text-end fw-bold <?php echo $bal > 0 ? 'text-danger' : 'text-success'; ?>">Rs.<?php echo number_format(abs($bal), 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;">
                        <td colspan="2" class="fw-bold">Totals (<?php echo count($filtered); ?> entries)</td>
                        <td class="text-end fw-bold">Rs.<?php echo number_format($totalDebit, 0); ?></td>
                        <td class="text-end fw-bold">Rs.<?php echo number_format($totalCredit, 0); ?></td>
                        <td class="text-end fw-bold">Rs.<?php echo number_format(abs($balance), 0); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- ===================== PRINT-ONLY SECTION ===================== -->
<div id="printSection">

    <!-- Letterhead -->
    <?php
    $printReportTitle = 'Staff Salary Ledger — ' . $staffMember['name'];
    $metaParts = [
        'Role: <strong>' . ucfirst(htmlspecialchars($staffMember['role'])) . '</strong>',
        'Monthly Salary: <strong>Rs.' . number_format($staffMember['salary'], 0) . '</strong>',
    ];
    if ($dateFrom !== '' || $dateTo !== '') {
        $metaParts[] = 'Period: <strong>' . ($dateFrom !== '' ? date('d M Y', strtotime($dateFrom)) : 'Start') . '</strong> &ndash; <strong>' . ($dateTo !== '' ? date('d M Y', strtotime($dateTo)) : 'Now') . '</strong>';
    }
    $printMeta = implode(' &nbsp;|&nbsp; ', $metaParts);
    include __DIR__ . '/../includes/print_header.php';
    ?>

    <!-- Summary boxes -->
    <div class="print-summary">
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($totalDebit, 0); ?></div>
            <div class="print-summary-lbl">Total Earned</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($totalCredit, 0); ?></div>
            <div class="print-summary-lbl">Total Paid</div>
        </div>
        <div class="print-summary-box highlight">
            <div class="print-summary-val"><?php echo $balance < 0 ? '-' : ''; ?>Rs.<?php echo number_format(abs($balance), 0); ?></div>
            <div class="print-summary-lbl"><?php echo $balance > 0 ? 'Outstanding Due' : ($balance < 0 ? 'Advance Paid' : 'Settled'); ?></div>
        </div>
    </div>

    <!-- Ledger table -->
    <table class="print-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Description</th>
                <th class="text-right">Debit (Earned)</th>
                <th class="text-right">Credit (Paid)</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($filtered)): ?>
                <tr><td colspan="6" style="text-align:center;padding:16px;color:#666;">No transactions found.</td></tr>
            <?php endif; ?>
            <?php foreach ($filtered as $i => $t): ?>
                <?php $bal = $t['balance']; ?>
                <tr class="<?php echo $i % 2 === 0 ? 'even' : ''; ?>">
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo date('d M Y', strtotime($t['date'])); ?></td>
                    <td><?php echo htmlspecialchars($t['description']); ?></td>
                    <td class="text-right bold"><?php echo $t['debit'] > 0 ? number_format($t['debit'], 0) : '-'; ?></td>
                    <td class="text-right bold"><?php echo $t['credit'] > 0 ? number_format($t['credit'], 0) : '-'; ?></td>
                    <td class="text-right bold"><?php echo number_format(abs($bal), 0) . ($bal > 0 ? ' (Due)' : ''); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="bold">Totals — <?php echo count($filtered); ?> entries</td>
                <td class="text-right bold">Rs.<?php echo number_format($totalDebit, 0); ?></td>
                <td class="text-right bold">Rs.<?php echo number_format($totalCredit, 0); ?></td>
                <td class="text-right bold">Rs.<?php echo number_format(abs($balance), 0); ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Footer -->
    <?php include __DIR__ . '/../includes/print_footer.php'; ?>

</div><!-- /printSection -->

<style>
/* ── Screen: hide print section ── */
#printSection { display: none; }

/* ── Print styles ── */
@media print {
    /* Hide all screen UI */
    .sidebar, .sidebar-overlay, .topbar, .hamburger,
    .no-print, .alert, .card, script { display: none !important; }

    body          { background:#fff !important; margin:0; padding:0; font-family: Arial, sans-serif; color:#000; }
    .layout-wrapper  { display:block !important; }
    .main-content    { margin:0 !important; width:100% !important; min-height:unset; }
    .content         { padding:0 !important; }
    .row.g-3.mb-4    { display:none !important; }

    /* Show print section */
    #printSection { display:block !important; padding: 18px 24px; }

    /* ── Letterhead ── */
    .print-header        { text-align:center; border-bottom:3px solid #1a1a2e; padding-bottom:10px; margin-bottom:14px; }
    .print-logo          { font-size:28px; color:#f7b731; margin-bottom:2px; }
    .print-gym-name      { font-size:20px; font-weight:900; letter-spacing:3px; color:#1a1a2e; }
    .print-gym-contact   { font-size:11px; color:#333; margin-top:3px; font-weight:500; }
    .print-gym-address   { font-size:10px; color:#555; margin-top:2px; }
    .print-gym-sub       { font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#555; margin-top:4px; }
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
    .print-table         { width:100%; border-collapse:collapse; font-size:10.5px; }
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
