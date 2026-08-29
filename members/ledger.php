<?php
$activePage = 'member_ledger';
$pageTitle = 'Member Ledger';
include __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$allMembers = $pdo->query("SELECT id, name, phone FROM members ORDER BY name")->fetchAll();

if ($id <= 0) {
    ?>
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card" style="border-top:3px solid #f7b731;">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="stat-icon mx-auto mb-3" style="width:60px;height:60px;font-size:1.5rem;background:linear-gradient(135deg,#f7b731,#f5a623);box-shadow:0 4px 15px rgba(247,183,49,0.3);color:#fff;"><i class="fas fa-book"></i></div>
                        <h5 class="fw-bold mb-1">Member Ledger</h5>
                        <p class="text-muted mb-0">Search member name or phone to view their payment history &amp; balance</p>
                    </div>
                    <form method="GET" action="" id="memberLedgerForm">
                        <div class="mb-3 position-relative">
                            <label class="form-label fw-semibold"><i class="fas fa-search me-1 text-muted"></i>Search Member *</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-user"></i></span>
                                <input type="text" id="memberSearchInput" class="form-control" placeholder="Type first letter or name..." autocomplete="off" spellcheck="false" autofocus>
                                <button type="button" class="btn btn-outline-secondary" id="clearMemberSearch" style="display:none;"><i class="fas fa-times"></i></button>
                            </div>
                            <input type="hidden" name="id" id="selectedMemberId" value="" required>

                            <!-- Dropdown Results Box -->
                            <div id="memberSearchResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:260px; overflow-y:auto; display:none; border-radius:8px;"></div>
                        </div>

                        <button type="submit" id="viewLedgerBtn" class="btn btn-lg fw-bold w-100" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;" disabled><i class="fas fa-arrow-right me-1"></i>View Ledger</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var members = <?php echo json_encode($allMembers); ?>;
        var searchInput = document.getElementById('memberSearchInput');
        var hiddenInput = document.getElementById('selectedMemberId');
        var resultsBox = document.getElementById('memberSearchResults');
        var clearBtn = document.getElementById('clearMemberSearch');
        var viewBtn = document.getElementById('viewLedgerBtn');
        var form = document.getElementById('memberLedgerForm');

        function renderList(query) {
            var q = (query || '').trim().toLowerCase();
            resultsBox.innerHTML = '';

            if (q.length < 1) {
                resultsBox.style.display = 'none';
                return;
            }

            var filtered = members.filter(function(m) {
                return m.name.toLowerCase().includes(q) || (m.phone && m.phone.toLowerCase().includes(q));
            });

            if (filtered.length === 0) {
                resultsBox.innerHTML = '<div class="list-group-item text-muted py-3 text-center"><i class="fas fa-user-slash me-1"></i>No matching members found</div>';
                resultsBox.style.display = 'block';
                return;
            }

            filtered.slice(0, 50).forEach(function(m) {
                var a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3';
                a.innerHTML = '<div><strong class="text-dark">' + escapeHtml(m.name) + '</strong><br><small class="text-muted"><i class="fas fa-phone me-1"></i>' + (m.phone || 'No Phone') + '</small></div><span class="badge bg-light text-dark border">Select</span>';
                
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    searchInput.value = m.name + (m.phone ? ' (' + m.phone + ')' : '');
                    hiddenInput.value = m.id;
                    resultsBox.style.display = 'none';
                    clearBtn.style.display = 'inline-block';
                    viewBtn.disabled = false;
                    form.submit();
                });
                resultsBox.appendChild(a);
            });

            resultsBox.style.display = 'block';
        }

        function escapeHtml(text) {
            var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return (text || '').replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        searchInput.addEventListener('focus', function() {
            if (this.value.trim().length >= 1) {
                renderList(this.value);
            }
        });

        searchInput.addEventListener('input', function() {
            hiddenInput.value = '';
            viewBtn.disabled = true;
            if (this.value.trim().length > 0) {
                clearBtn.style.display = 'inline-block';
            } else {
                clearBtn.style.display = 'none';
            }
            renderList(this.value);
        });

        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            hiddenInput.value = '';
            clearBtn.style.display = 'none';
            viewBtn.disabled = true;
            resultsBox.style.display = 'none';
            resultsBox.innerHTML = '';
            searchInput.focus();
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#memberLedgerForm')) {
                resultsBox.style.display = 'none';
            }
        });
    })();
    </script>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    echo '<div class="alert alert-warning">Member not found. <a href="ledger.php">Back</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$search = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$transactions = [];

// Subscription assignments (debits - plan price)
$stmt = $pdo->prepare("SELECT s.id, s.start_date, s.end_date, s.created_at, p.name AS plan_name, p.price FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.member_id = ? ORDER BY s.start_date DESC, s.id DESC");
$stmt->execute([$id]);
foreach ($stmt->fetchAll() as $s) {
    $transactions[] = [
        'date' => $s['start_date'],
        'type' => 'subscription',
        'description' => $s['plan_name'] . ' Plan (' . date('d M Y', strtotime($s['start_date'])) . ' - ' . date('d M Y', strtotime($s['end_date'])) . ')',
        'debit' => (float)$s['price'],
        'credit' => 0,
        'time' => $s['created_at'],
    ];
}

// Payments received (credits)
$stmt = $pdo->prepare("SELECT id, amount, payment_method, payment_for, notes, payment_date, created_at FROM member_payments WHERE member_id = ? ORDER BY payment_date DESC, id DESC");
$stmt->execute([$id]);
foreach ($stmt->fetchAll() as $p) {
    $transactions[] = [
        'date' => $p['payment_date'],
        'type' => 'payment',
        'description' => 'Payment' . ($p['payment_for'] ? ' (' . $p['payment_for'] . ')' : '') . ($p['notes'] ? ' - ' . $p['notes'] : ''),
        'debit' => 0,
        'credit' => $p['amount'],
        'time' => $p['created_at'],
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
if ($search !== '') {
    $filtered = array_filter($filtered, function($t) use ($search) {
        return stripos($t['description'], $search) !== false || stripos($t['type'], $search) !== false;
    });
}
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

// Get active subscription info
$stmt = $pdo->prepare("SELECT s.*, p.name AS plan_name, p.price, p.duration_days FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.member_id = ? AND s.status = 'active' ORDER BY s.end_date DESC LIMIT 1");
$stmt->execute([$id]);
$activeSub = $stmt->fetch();
?>

<div class="mb-4">
    <a href="ledger.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary"><i class="fas fa-user"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($member['name']); ?></h5>
                    <small class="text-muted"><?php echo htmlspecialchars($member['phone']); ?></small>
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
                <div class="stat-icon bg-warning"><i class="fas fa-clipboard-list"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalDebit, 0); ?></h5>
                    <small class="text-muted">Total Plan Value</small>
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

<?php if ($activeSub): ?>
<div class="alert alert-info d-flex align-items-center gap-3 mb-4">
    <i class="fas fa-id-card fa-2x"></i>
    <div>
        <strong>Active Plan:</strong> <?php echo htmlspecialchars($activeSub['plan_name']); ?> (Rs.<?php echo number_format($activeSub['price'], 0); ?>) &mdash;
        <?php echo date('d M Y', strtotime($activeSub['start_date'])); ?> to <?php echo date('d M Y', strtotime($activeSub['end_date'])); ?>
        <?php
        $daysLeft = (int)((strtotime($activeSub['end_date']) - time()) / 86400);
        if ($daysLeft > 0): ?>
            <span class="badge bg-success ms-1"><?php echo $daysLeft; ?> days left</span>
        <?php else: ?>
            <span class="badge bg-danger ms-1">Expired</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card" style="border-top:3px solid #f7b731;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h6 class="fw-bold mb-0"><i class="fas fa-book me-2" style="color:#f7b731;"></i>Ledger &mdash; <?php echo htmlspecialchars($member['name']); ?></h6>
            <div class="d-flex gap-2">
                <a href="payments.php?member_id=<?php echo $id; ?>" class="btn btn-success fw-bold btn-sm"><i class="fas fa-plus me-1"></i>Record Payment</a>
                <button type="button" onclick="downloadMemberLedgerPDF();" class="btn btn-primary fw-bold btn-sm"><i class="fas fa-file-pdf me-1"></i>Download PDF</button>
                <button onclick="window.print();" class="btn btn-danger fw-bold btn-sm"><i class="fas fa-print me-1"></i>Print</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="printArea">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
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
                                <?php if ($t['type'] === 'subscription'): ?>
                                    <i class="fas fa-clipboard-list text-warning me-1"></i>
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
    $printReportTitle = 'Member Ledger';
    include __DIR__ . "/../includes/print_header.php";
    ?>

    <!-- Member summary boxes -->
    <div class="print-summary">
        <div class="print-summary-box<?php echo $balance > 0 ? ' highlight' : ''; ?>">
            <div class="print-summary-val">Rs.<?php echo number_format(abs($balance), 0); ?></div>
            <div class="print-summary-lbl"><?php echo $balance > 0 ? 'Outstanding Due' : ($balance < 0 ? 'Advance Paid' : 'Settled'); ?></div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($totalDebit, 0); ?></div>
            <div class="print-summary-lbl">Total Plan Value</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($totalCredit, 0); ?></div>
            <div class="print-summary-lbl">Total Paid</div>
        </div>
    </div>

    <?php if ($activeSub): ?>
    <div class="print-plan-line">
        Active Plan: <strong><?php echo htmlspecialchars($activeSub['plan_name']); ?></strong>
        (<?php echo date('d M Y', strtotime($activeSub['start_date'])); ?> &ndash; <?php echo date('d M Y', strtotime($activeSub['end_date'])); ?>)
    </div>
    <?php endif; ?>

    <!-- Transactions table -->
    <table class="print-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Type</th>
                <th class="text-right">Debit (Rs.)</th>
                <th class="text-right">Credit (Rs.)</th>
                <th class="text-right">Balance (Rs.)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($filtered)): ?>
                <tr><td colspan="6" style="text-align:center;padding:20px;color:#666;">No transactions found.</td></tr>
            <?php endif; ?>
            <?php foreach ($filtered as $i => $t): $bal = $t['balance']; ?>
            <tr class="<?php echo $i % 2 === 0 ? 'even' : ''; ?>">
                <td><?php echo date('d M Y', strtotime($t['date'])); ?></td>
                <td><?php echo htmlspecialchars($t['description']); ?></td>
                <td><?php echo $t['type'] === 'subscription' ? 'Subscription' : 'Payment'; ?></td>
                <td class="text-right"><?php echo $t['debit'] > 0 ? number_format($t['debit'], 2) : '-'; ?></td>
                <td class="text-right"><?php echo $t['credit'] > 0 ? number_format($t['credit'], 2) : '-'; ?></td>
                <td class="text-right bold"><?php echo number_format(abs($bal), 2) . ($bal > 0 ? ' Dr' : ($bal < 0 ? ' Cr' : '')); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="bold">Totals — <?php echo count($filtered); ?> entr(y/ies)</td>
                <td class="text-right bold"><?php echo number_format($totalDebit, 2); ?></td>
                <td class="text-right bold"><?php echo number_format($totalCredit, 2); ?></td>
                <td class="text-right bold"><?php echo number_format(abs($balance), 2) . ($balance > 0 ? ' Dr' : ($balance < 0 ? ' Cr' : '')); ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Footer -->
    <?php include __DIR__ . "/../includes/print_footer.php"; ?>

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

#printSection .print-plan-line {
    font-size: 10.5px;
    color: #333333;
    border: 1px dashed #999999;
    padding: 6px 10px;
    margin-bottom: 14px;
    background: #fafafa;
}

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

    #printSection { display: block !important; padding: 18px 24px; }
    @page { margin: 12mm 10mm; size: A4 portrait; }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadMemberLedgerPDF() {
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
        filename:     'Member_Ledger_<?php echo preg_replace('/[^A-Za-z0-9_-]/', '_', $member['name']); ?>.pdf',
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
