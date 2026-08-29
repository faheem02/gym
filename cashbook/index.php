<?php
$activePage = 'cashbook';
$pageTitle = 'Cash Book';
include __DIR__ . '/../includes/header.php';

$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$search = trim($_GET['search'] ?? '');

$transactions = [];

// Canteen Sales (Cash)
$stmt = $pdo->query("SELECT id, receipt_no, customer_name, final_amount, received_amount, payment_date, created_at FROM canteen_sales WHERE payment_method = 'cash' ORDER BY payment_date DESC, id DESC");
foreach ($stmt->fetchAll() as $s) {
    $transactions[] = [
        'date' => $s['payment_date'],
        'source' => 'Canteen Sale',
        'source_icon' => 'fa-shopping-cart',
        'source_color' => 'text-primary',
        'description' => 'Sale #' . $s['receipt_no'] . ($s['customer_name'] ? ' - ' . $s['customer_name'] : ''),
        'type' => 'income',
        'amount' => (float)$s['received_amount'],
        'time' => $s['created_at'],
    ];
}

// Supplier Payments (Cash)
$stmt = $pdo->query("SELECT sp.id, sp.amount, sp.notes, sp.payment_date, sp.created_at, cs.name AS supplier_name FROM canteen_supplier_payments sp LEFT JOIN canteen_suppliers cs ON cs.id = sp.supplier_id WHERE sp.payment_method = 'cash' ORDER BY sp.payment_date DESC, sp.id DESC");
foreach ($stmt->fetchAll() as $p) {
    $transactions[] = [
        'date' => $p['payment_date'],
        'source' => 'Supplier Payment',
        'source_icon' => 'fa-truck',
        'source_color' => 'text-warning',
        'description' => 'Paid to ' . ($p['supplier_name'] ?? 'Unknown') . ($p['notes'] ? ' - ' . $p['notes'] : ''),
        'type' => 'expense',
        'amount' => (float)$p['amount'],
        'time' => $p['created_at'],
    ];
}

// Expenses (Cash)
$stmt = $pdo->query("SELECT e.id, e.amount, e.description, e.receipt_no, e.expense_date, e.created_at, ec.name AS category_name FROM expenses e LEFT JOIN expense_categories ec ON ec.id = e.category_id WHERE e.payment_method = 'cash' ORDER BY e.expense_date DESC, e.id DESC");
foreach ($stmt->fetchAll() as $e) {
    $transactions[] = [
        'date' => $e['expense_date'],
        'source' => 'Expense',
        'source_icon' => 'fa-receipt',
        'source_color' => 'text-danger',
        'description' => ($e['category_name'] ?? 'Unknown') . ($e['description'] ? ' - ' . $e['description'] : ''),
        'type' => 'expense',
        'amount' => (float)$e['amount'],
        'time' => $e['created_at'],
    ];
}

// Member Payments (Cash)
$stmt = $pdo->query("SELECT mp.id, mp.amount, mp.payment_for, mp.notes, mp.payment_date, mp.created_at, m.name AS member_name FROM member_payments mp LEFT JOIN members m ON m.id = mp.member_id WHERE mp.payment_method = 'cash' ORDER BY mp.payment_date DESC, mp.id DESC");
foreach ($stmt->fetchAll() as $mp) {
    $transactions[] = [
        'date' => $mp['payment_date'],
        'source' => 'Member Payment',
        'source_icon' => 'fa-user',
        'source_color' => 'text-success',
        'description' => 'From ' . ($mp['member_name'] ?? 'Unknown') . ($mp['payment_for'] ? ' (' . $mp['payment_for'] . ')' : '') . ($mp['notes'] ? ' - ' . $mp['notes'] : ''),
        'type' => 'income',
        'amount' => (float)$mp['amount'],
        'time' => $mp['created_at'],
    ];
}

// Day Passes (Cash)
$stmt = $pdo->query("SELECT id, visitor_name, amount, pass_date, created_at FROM day_passes WHERE amount > 0 ORDER BY pass_date DESC, id DESC");
foreach ($stmt->fetchAll() as $d) {
    $transactions[] = [
        'date' => $d['pass_date'],
        'source' => 'Day Pass',
        'source_icon' => 'fa-ticket-alt',
        'source_color' => 'text-info',
        'description' => 'Day Pass - ' . $d['visitor_name'],
        'type' => 'income',
        'amount' => (float)$d['amount'],
        'time' => $d['created_at'],
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
        return stripos($t['description'], $search) !== false || stripos($t['source'], $search) !== false;
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
            <div class="stat-icon bg-success"><i class="fas fa-arrow-down"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalIncome, 0); ?></h5><small class="text-muted">Total Cash In</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-danger"><i class="fas fa-arrow-up"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalExpense, 0); ?></h5><small class="text-muted">Total Cash Out</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;"><i class="fas fa-wallet"></i></div>
            <div><h5 class="mb-0 fw-bold <?php echo $netBalance >= 0 ? 'text-success' : 'text-danger'; ?>">Rs.<?php echo number_format(abs($netBalance), 0); ?></h5><small class="text-muted">Net Cash Balance</small></div>
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
            <h6 class="fw-bold mb-0"><i class="fas fa-money-bill-wave me-2" style="color:#f7b731;"></i>Cash Book — All Cash Transactions</h6>
            <div class="d-flex gap-2">
                <button type="button" onclick="downloadCashbookPDF();" class="btn btn-primary fw-bold btn-sm"><i class="fas fa-file-pdf me-1"></i>Download PDF</button>
                <button onclick="window.print();" class="btn btn-danger fw-bold btn-sm"><i class="fas fa-print me-1"></i>Print</button>
            </div>
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
                        <th>Type</th>
                        <th class="text-end">Cash In</th>
                        <th class="text-end">Cash Out</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-money-bill-wave me-1"></i>No cash transactions found.</td></tr>
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
                            <td>
                                <?php if ($t['type'] === 'income'): ?>
                                    <span class="badge bg-success"><i class="fas fa-arrow-down me-1"></i>Income</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fas fa-arrow-up me-1"></i>Expense</span>
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
                        <td colspan="5" class="fw-bold"><?php echo count($transactions); ?> transaction(s)</td>
                        <td class="text-end fw-bold text-success">Rs.<?php echo number_format($totalIncome, 0); ?></td>
                        <td class="text-end fw-bold text-danger">Rs.<?php echo number_format($totalExpense, 0); ?></td>
                        <td class="text-end fw-bold">Rs.<?php echo number_format(abs($netBalance), 0); ?></td>
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
    $printReportTitle = 'Cash Book';
    $meta = [];
    if ($filterDateFrom !== '' || $filterDateTo !== '') {
        $meta[] = 'Period: <strong>' . ($filterDateFrom !== '' ? date('d M Y', strtotime($filterDateFrom)) : 'Start') . '</strong> &ndash; <strong>' . ($filterDateTo !== '' ? date('d M Y', strtotime($filterDateTo)) : 'Now') . '</strong>';
    } else {
        $meta[] = 'All Records';
    }
    if ($search !== '') $meta[] = 'Search: &quot;' . htmlspecialchars($search) . '&quot;';
    $printMeta = implode(' &nbsp;|&nbsp; ', $meta);
    include __DIR__ . '/../includes/print_header.php';
    ?>

    <!-- Summary boxes -->
    <div class="print-summary">
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($totalIncome, 0); ?></div>
            <div class="print-summary-lbl">Total Cash In</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($totalExpense, 0); ?></div>
            <div class="print-summary-lbl">Total Cash Out</div>
        </div>
        <div class="print-summary-box highlight">
            <div class="print-summary-val"><?php echo $netBalance < 0 ? '-' : ''; ?>Rs.<?php echo number_format(abs($netBalance), 0); ?></div>
            <div class="print-summary-lbl">Net Cash Balance</div>
        </div>
    </div>

    <!-- Transactions table -->
    <table class="print-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Source</th>
                <th>Description</th>
                <th>Type</th>
                <th class="text-right">Cash In (Rs.)</th>
                <th class="text-right">Cash Out (Rs.)</th>
                <th class="text-right">Balance (Rs.)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transactions)): ?>
                <tr><td colspan="8" style="text-align:center;padding:20px;color:#666;">No cash transactions found.</td></tr>
            <?php endif; ?>
            <?php
            $runningBal = 0;
            foreach ($transactions as $i => $t):
                if ($t['type'] === 'income') $runningBal += $t['amount'];
                else $runningBal -= $t['amount'];
            ?>
            <tr class="<?php echo $i % 2 === 0 ? 'even' : ''; ?>">
                <td><?php echo $i + 1; ?></td>
                <td><?php echo date('d M Y', strtotime($t['date'])); ?></td>
                <td><?php echo htmlspecialchars($t['source']); ?></td>
                <td><?php echo htmlspecialchars($t['description']); ?></td>
                <td><?php echo $t['type'] === 'income' ? 'Income' : 'Expense'; ?></td>
                <td class="text-right bold"><?php echo $t['type'] === 'income' ? number_format($t['amount'], 2) : '-'; ?></td>
                <td class="text-right bold"><?php echo $t['type'] === 'expense' ? number_format($t['amount'], 2) : '-'; ?></td>
                <td class="text-right bold"><?php echo number_format(abs($runningBal), 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="bold">Totals — <?php echo count($transactions); ?> transaction(s)</td>
                <td class="text-right bold">In: <?php echo number_format($totalIncome, 2); ?></td>
                <td class="text-right bold">Out: <?php echo number_format($totalExpense, 2); ?></td>
                <td class="text-right bold"><?php echo number_format(abs($netBalance), 2); ?></td>
            </tr>
        </tfoot>
    </table>

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
#printSection .print-summary-val { font-size: 16px; font-weight: 700; }
#printSection .print-summary-lbl {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #666666;
    margin-top: 3px;
}
#printSection .print-summary-box.highlight .print-summary-lbl { color: #dddddd !important; }

/* ── Table ── */
#printSection .print-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10.5px;
    margin-bottom: 16px;
}
#printSection .print-table thead tr {
    background: #1a1a2e !important;
    color: #ffffff !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
#printSection .print-table thead th {
    padding: 8px 8px;
    text-align: left;
    font-weight: 700;
    font-size: 10px;
    letter-spacing: 0.5px;
    border: 1px solid #1a1a2e;
    color: #ffffff;
}
#printSection .print-table tbody tr td {
    padding: 6px 8px;
    border: 1px solid #e0e0e0;
    vertical-align: middle;
}
#printSection .print-table tbody tr.even td {
    background: #f9fafb !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
#printSection .print-table tfoot tr td {
    padding: 8px 8px;
    background: #f3f4f6 !important;
    font-weight: 700;
    border: 1px solid #d1d5db;
    border-top: 2px solid #1a1a2e;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
#printSection .print-table .text-right { text-align: right; }
#printSection .print-table .bold { font-weight: 700; }

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
    .card, script { display: none !important; }

    body { background: #fff !important; margin: 0; padding: 0; font-family: Arial, sans-serif; color: #000; }
    .layout-wrapper { display: block !important; }
    .main-content { margin: 0 !important; width: 100% !important; min-height: unset; }
    .content { padding: 0 !important; }
    .row.g-3.mb-4 { display: none !important; }

    #printSection { display: block !important; padding: 18px 24px; }
    @page { margin: 12mm 10mm; size: A4 landscape; }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadCashbookPDF() {
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
        filename:     'Cash_Book_Report.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, letterRendering: true, scrollY: 0 },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
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
