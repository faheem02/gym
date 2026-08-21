<?php
$activePage = 'bankbook';
$pageTitle = 'Bank Book';
include __DIR__ . '/../includes/header.php';

$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$search = trim($_GET['search'] ?? '');

$bankMethods = ['card', 'bank_transfer', 'online', 'easypaisa', 'jazzcash'];
$placeholders = implode(',', array_fill(0, count($bankMethods), '?'));

$transactions = [];

// Canteen Sales (Bank/Card/Online)
$stmt = $pdo->prepare("SELECT id, receipt_no, customer_name, final_amount, received_amount, payment_method, payment_date, created_at FROM canteen_sales WHERE payment_method IN ($placeholders) ORDER BY payment_date DESC, id DESC");
$stmt->execute($bankMethods);
foreach ($stmt->fetchAll() as $s) {
    $transactions[] = [
        'date' => $s['payment_date'],
        'source' => 'Canteen Sale',
        'source_icon' => 'fa-shopping-cart',
        'description' => 'Sale #' . $s['receipt_no'] . ($s['customer_name'] ? ' - ' . $s['customer_name'] : ''),
        'method' => ucfirst(str_replace('_', ' ', $s['payment_method'])),
        'type' => 'income',
        'amount' => (float)$s['received_amount'],
        'time' => $s['created_at'],
    ];
}

// Supplier Payments (Bank/Card)
$stmt = $pdo->prepare("SELECT sp.id, sp.amount, sp.payment_method, sp.notes, sp.payment_date, sp.created_at, cs.name AS supplier_name FROM canteen_supplier_payments sp LEFT JOIN canteen_suppliers cs ON cs.id = sp.supplier_id WHERE sp.payment_method IN ($placeholders) ORDER BY sp.payment_date DESC, sp.id DESC");
$stmt->execute($bankMethods);
foreach ($stmt->fetchAll() as $p) {
    $transactions[] = [
        'date' => $p['payment_date'],
        'source' => 'Supplier Payment',
        'source_icon' => 'fa-truck',
        'description' => 'Paid to ' . ($p['supplier_name'] ?? 'Unknown') . ($p['notes'] ? ' - ' . $p['notes'] : ''),
        'method' => ucfirst(str_replace('_', ' ', $p['payment_method'])),
        'type' => 'expense',
        'amount' => (float)$p['amount'],
        'time' => $p['created_at'],
    ];
}

// Member Payments (Bank/Card/Online)
$stmt = $pdo->prepare("SELECT mp.id, mp.amount, mp.payment_method, mp.payment_for, mp.notes, mp.payment_date, mp.created_at, m.name AS member_name FROM member_payments mp LEFT JOIN members m ON m.id = mp.member_id WHERE mp.payment_method IN ($placeholders) ORDER BY mp.payment_date DESC, mp.id DESC");
$stmt->execute($bankMethods);
foreach ($stmt->fetchAll() as $mp) {
    $transactions[] = [
        'date' => $mp['payment_date'],
        'source' => 'Member Payment',
        'source_icon' => 'fa-user',
        'description' => 'From ' . ($mp['member_name'] ?? 'Unknown') . ($mp['payment_for'] ? ' (' . $mp['payment_for'] . ')' : '') . ($mp['notes'] ? ' - ' . $mp['notes'] : ''),
        'method' => ucfirst(str_replace('_', ' ', $mp['payment_method'])),
        'type' => 'income',
        'amount' => (float)$mp['amount'],
        'time' => $mp['created_at'],
    ];
}

// Expenses (Bank/Card/EasyPaisa/JazzCash)
$stmt = $pdo->prepare("SELECT e.id, e.amount, e.payment_method, e.description, e.receipt_no, e.expense_date, e.created_at, ec.name AS category_name FROM expenses e LEFT JOIN expense_categories ec ON ec.id = e.category_id WHERE e.payment_method IN ($placeholders) ORDER BY e.expense_date DESC, e.id DESC");
$stmt->execute($bankMethods);
foreach ($stmt->fetchAll() as $e) {
    $transactions[] = [
        'date' => $e['expense_date'],
        'source' => 'Expense',
        'source_icon' => 'fa-receipt',
        'description' => ($e['category_name'] ?? 'Unknown') . ($e['description'] ? ' - ' . $e['description'] : ''),
        'method' => ucfirst(str_replace('_', ' ', $e['payment_method'])),
        'type' => 'expense',
        'amount' => (float)$e['amount'],
        'time' => $e['created_at'],
    ];
}

// Sort by date descending, then by time
usort($transactions, function($a, $b) {
    $d = strcmp($b['date'], $a['date']);
    if ($d === 0) return strcmp($b['time'], $a['time']);
    return $d;
});

// Apply filters
if ($search !== '') {
    $transactions = array_filter($transactions, function($t) use ($search) {
        return stripos($t['description'], $search) !== false || stripos($t['source'], $search) !== false || stripos($t['method'], $search) !== false;
    });
}
if ($filterDateFrom !== '') {
    $transactions = array_filter($transactions, function($t) use ($filterDateFrom) {
        return $t['date'] >= $filterDateFrom;
    });
}
if ($filterDateTo !== '') {
    $transactions = array_filter($transactions, function($t) use ($filterDateTo) {
        return $t['date'] <= $filterDateTo;
    });
}
$transactions = array_values($transactions);

// Calculate totals
$totalIncome = 0;
$totalExpense = 0;
foreach ($transactions as $t) {
    if ($t['type'] === 'income') $totalIncome += $t['amount'];
    else $totalExpense += $t['amount'];
}
$netBalance = $totalIncome - $totalExpense;

// Today & This Month
$todayIncome = 0;
$todayExpense = 0;
$thisMonthIncome = 0;
$thisMonthExpense = 0;
$today = date('Y-m-d');
$thisMonth = date('Y-m');
foreach ($transactions as $t) {
    if ($t['date'] === $today) {
        if ($t['type'] === 'income') $todayIncome += $t['amount'];
        else $todayExpense += $t['amount'];
    }
    if (substr($t['date'], 0, 7) === $thisMonth) {
        if ($t['type'] === 'income') $thisMonthIncome += $t['amount'];
        else $thisMonthExpense += $t['amount'];
    }
}
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-success"><i class="fas fa-arrow-up"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalIncome, 0); ?></h5><small class="text-muted">Total Bank In</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-danger"><i class="fas fa-arrow-down"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalExpense, 0); ?></h5><small class="text-muted">Total Bank Out</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;"><i class="fas fa-university"></i></div>
            <div><h5 class="mb-0 fw-bold <?php echo $netBalance >= 0 ? 'text-success' : 'text-danger'; ?>">Rs.<?php echo number_format(abs($netBalance), 0); ?></h5><small class="text-muted">Net Bank Balance</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-info"><i class="fas fa-calendar-day"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($todayIncome - $todayExpense, 0); ?></h5><small class="text-muted">Today's Net</small></div>
        </div></div>
    </div>
</div>

<div class="card" style="border-top:3px solid #f7b731;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h6 class="fw-bold mb-0"><i class="fas fa-university me-2" style="color:#f7b731;"></i>Bank Book — All Bank/Card/Online Transactions</h6>
            <button onclick="window.print();" class="btn btn-danger fw-bold btn-sm"><i class="fas fa-print me-1"></i>Print</button>
        </div>

        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2"><input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterDateFrom); ?>"></div>
            <div class="col-md-2"><input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterDateTo); ?>"></div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm flex-fill fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="printArea">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Source</th>
                        <th>Description</th>
                        <th>Method</th>
                        <th>Type</th>
                        <th class="text-end">Bank In</th>
                        <th class="text-end">Bank Out</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-university me-1"></i>No bank transactions found.</td></tr>
                    <?php endif; ?>
                    <?php
                    $runningBal = 0;
                    foreach ($transactions as $i => $t):
                        if ($t['type'] === 'income') $runningBal += $t['amount'];
                        else $runningBal -= $t['amount'];
                    ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo date('d M Y', strtotime($t['date'])); ?></td>
                            <td><span class="badge" style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;"><i class="fas <?php echo $t['source_icon']; ?> me-1"></i><?php echo $t['source']; ?></span></td>
                            <td class="small"><?php echo htmlspecialchars($t['description']); ?></td>
                            <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($t['method']); ?></span></td>
                            <td>
                                <?php if ($t['type'] === 'income'): ?>
                                    <span class="badge bg-success"><i class="fas fa-arrow-up me-1"></i>Income</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fas fa-arrow-down me-1"></i>Expense</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold text-success"><?php echo $t['type'] === 'income' ? 'Rs.' . number_format($t['amount'], 0) : '-'; ?></td>
                            <td class="text-end fw-bold text-danger"><?php echo $t['type'] === 'expense' ? 'Rs.' . number_format($t['amount'], 0) : '-'; ?></td>
                            <td class="text-end fw-bold <?php echo $runningBal >= 0 ? 'text-success' : 'text-danger'; ?>">Rs.<?php echo number_format(abs($runningBal), 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;">
                        <td colspan="6" class="fw-bold"><?php echo count($transactions); ?> transaction(s)</td>
                        <td class="text-end fw-bold text-success">Rs.<?php echo number_format($totalIncome, 0); ?></td>
                        <td class="text-end fw-bold text-danger">Rs.<?php echo number_format($totalExpense, 0); ?></td>
                        <td class="text-end fw-bold">Rs.<?php echo number_format(abs($netBalance), 0); ?></td>
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
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
