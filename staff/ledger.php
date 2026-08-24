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

<div class="card" style="border-top:3px solid #f7b731;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h6 class="fw-bold mb-0"><i class="fas fa-book me-2" style="color:#f7b731;"></i>Ledger &mdash; <?php echo htmlspecialchars($staffMember['name']); ?></h6>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <form method="GET" action="" class="d-flex gap-1 align-items-center no-print">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateFrom); ?>" title="From date">
                    <span class="text-muted small">to</span>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateTo); ?>" title="To date">
                    <button class="btn btn-dark btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
                    <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
                        <a href="ledger.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary btn-sm" title="Clear filter"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
                <a href="salaries.php" class="btn btn-success fw-bold btn-sm"><i class="fas fa-plus me-1"></i>Record Payment</a>
                <button onclick="window.print();" class="btn btn-danger fw-bold btn-sm"><i class="fas fa-print me-1"></i>Print</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="printArea">
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

<style>
@media print {
    body * { visibility: hidden; }
    #printArea, #printArea * { visibility: visible; }
    #printArea { position: absolute; left: 0; top: 0; width: 100%; }
    .no-print { display: none !important; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
