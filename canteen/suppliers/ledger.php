<?php
$activePage = 'canteen_ledger';
$pageTitle = 'Supplier Ledger';
include __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$allSuppliers = $pdo->query("SELECT id, name, balance FROM canteen_suppliers WHERE status = 'active' ORDER BY name")->fetchAll();

if ($id <= 0) {
    ?>
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card" style="border-top:3px solid var(--primary-gradient); border-image: linear-gradient(135deg, #f7b731, #f5a623) 1;">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="stat-icon mx-auto mb-3" style="width:60px;height:60px;font-size:1.5rem;background:linear-gradient(135deg,#f7b731,#f5a623);box-shadow:0 4px 15px rgba(247,183,49,0.3);color:#fff;"><i class="fas fa-book"></i></div>
                        <h5 class="fw-bold mb-1">Supplier Ledger</h5>
                        <p class="text-muted mb-0">Search supplier name to view their transaction history</p>
                    </div>
                    <form method="GET" action="" id="supplierLedgerForm">
                        <div class="mb-3 position-relative">
                            <label class="form-label fw-semibold"><i class="fas fa-search me-1 text-muted"></i>Search Supplier *</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-truck"></i></span>
                                <input type="text" id="supplierSearchInput" class="form-control" placeholder="Type first letter or name..." autocomplete="off" spellcheck="false" autofocus>
                                <button type="button" class="btn btn-outline-secondary" id="clearSupplierSearch" style="display:none;"><i class="fas fa-times"></i></button>
                            </div>
                            <input type="hidden" name="id" id="selectedSupplierId" value="" required>

                            <!-- Dropdown Results Box -->
                            <div id="supplierSearchResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:260px; overflow-y:auto; display:none; border-radius:8px;"></div>
                        </div>
                        <div class="alert alert-light border text-center mb-3 d-none" id="ledgerBalInfo"></div>
                        <button type="submit" id="viewSupplierBtn" class="btn btn-lg fw-bold w-100" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;" disabled><i class="fas fa-arrow-right me-1"></i>View Ledger</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function() {
        var suppliers = <?php echo json_encode($allSuppliers); ?>;
        var searchInput = document.getElementById('supplierSearchInput');
        var hiddenInput = document.getElementById('selectedSupplierId');
        var resultsBox = document.getElementById('supplierSearchResults');
        var clearBtn = document.getElementById('clearSupplierSearch');
        var viewBtn = document.getElementById('viewSupplierBtn');
        var balEl = document.getElementById('ledgerBalInfo');
        var form = document.getElementById('supplierLedgerForm');

        function renderList(query) {
            var q = (query || '').trim().toLowerCase();
            resultsBox.innerHTML = '';

            if (q.length < 1) {
                resultsBox.style.display = 'none';
                return;
            }

            var filtered = suppliers.filter(function(s) {
                return s.name.toLowerCase().includes(q);
            });

            if (filtered.length === 0) {
                resultsBox.innerHTML = '<div class="list-group-item text-muted py-3 text-center"><i class="fas fa-truck me-1"></i>No matching suppliers found</div>';
                resultsBox.style.display = 'block';
                return;
            }

            filtered.forEach(function(s) {
                var a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3';
                var bal = parseFloat(s.balance || 0);
                var balBadge = '';
                if (bal > 0) {
                    balBadge = '<span class="badge text-bg-danger">Due: Rs.' + Math.round(bal).toLocaleString() + '</span>';
                } else if (bal < 0) {
                    balBadge = '<span class="badge text-bg-success">Adv: Rs.' + Math.round(Math.abs(bal)).toLocaleString() + '</span>';
                } else {
                    balBadge = '<span class="badge text-bg-light border">Settled</span>';
                }
                a.innerHTML = '<div><strong class="text-dark">' + escapeHtml(s.name) + '</strong></div>' + balBadge;
                
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    searchInput.value = s.name;
                    hiddenInput.value = s.id;
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
            balEl.classList.add('d-none');
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
            balEl.classList.add('d-none');
            resultsBox.style.display = 'none';
            resultsBox.innerHTML = '';
            searchInput.focus();
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#supplierLedgerForm')) {
                resultsBox.style.display = 'none';
            }
        });
    })();
    </script>
    <?php
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM canteen_suppliers WHERE id = ?');
$stmt->execute([$id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    echo '<div class="alert alert-warning">Supplier not found. <a href="ledger.php">Back</a></div>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

$search = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$balance = (float)$supplier['balance'];

$transactions = [];

$purchases = $pdo->prepare("SELECT id, total_amount, paid_amount, purchase_date, created_at FROM canteen_purchases WHERE supplier_id = ? ORDER BY purchase_date DESC, id DESC");
$purchases->execute([$id]);
foreach ($purchases->fetchAll() as $p) {
    $transactions[] = [
        'date' => $p['purchase_date'],
        'type' => 'purchase',
        'description' => 'Purchase #' . $p['id'],
        'debit' => $p['total_amount'],
        'credit' => $p['paid_amount'],
        'time' => $p['created_at'],
    ];
}

$payments = $pdo->prepare("SELECT id, amount, payment_method, notes, payment_date, created_at FROM canteen_supplier_payments WHERE supplier_id = ? ORDER BY payment_date DESC, id DESC");
$payments->execute([$id]);
foreach ($payments->fetchAll() as $p) {
    $transactions[] = [
        'date' => $p['payment_date'],
        'type' => 'payment',
        'description' => 'Payment' . ($p['notes'] ? ' - ' . $p['notes'] : ''),
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
?>

<div class="mb-4">
    <a href="ledger.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary"><i class="fas fa-truck"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($supplier['name']); ?></h5>
                    <small class="text-muted"><?php echo htmlspecialchars($supplier['phone'] ?? ''); ?></small>
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
                    <small class="text-muted"><?php echo $balance > 0 ? 'Total Due' : ($balance < 0 ? 'Advance' : 'Settled'); ?></small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning"><i class="fas fa-arrow-down"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalDebit, 0); ?></h5>
                    <small class="text-muted">Total Purchases</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success"><i class="fas fa-arrow-up"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalCredit, 0); ?></h5>
                    <small class="text-muted">Total Paid</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card" id="printArea" style="border-top:3px solid var(--primary-gradient); border-image: linear-gradient(135deg, #f7b731, #f5a623) 1;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h6 class="fw-bold mb-0"><i class="fas fa-book me-2" style="color:#f7b731;"></i>Ledger — <?php echo htmlspecialchars($supplier['name']); ?></h6>
            <div class="d-flex gap-2">
                <button type="button" onclick="downloadSupplierLedgerPDF();" class="btn btn-primary fw-bold btn-sm"><i class="fas fa-file-pdf me-1"></i>Download PDF</button>
                <button onclick="window.print();" class="btn btn-danger fw-bold btn-sm"><i class="fas fa-print me-1"></i>Print</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
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
                                <?php if ($t['type'] === 'purchase'): ?>
                                    <i class="fas fa-shopping-cart text-primary me-1"></i>
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
    $printReportTitle = 'Supplier Ledger';
    include __DIR__ . "/../../includes/print_header.php";
    ?>

    <!-- Supplier summary boxes -->
    <div class="print-summary">
        <div class="print-summary-box<?php echo $balance > 0 ? ' highlight' : ''; ?>">
            <div class="print-summary-val">Rs.<?php echo number_format(abs($balance), 0); ?></div>
            <div class="print-summary-lbl"><?php echo $balance > 0 ? 'Outstanding Due' : ($balance < 0 ? 'Advance Paid' : 'Settled'); ?></div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($totalDebit, 0); ?></div>
            <div class="print-summary-lbl">Total Purchases</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($totalCredit, 0); ?></div>
            <div class="print-summary-lbl">Total Paid</div>
        </div>
    </div>

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
                <td><?php echo $t['type'] === 'purchase' ? 'Purchase' : 'Payment'; ?></td>
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
    <?php include __DIR__ . "/../../includes/print_footer.php"; ?>

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
    .page-header, .card, script { display: none !important; }

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
function downloadSupplierLedgerPDF() {
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
        filename:     'Supplier_Ledger_<?php echo preg_replace('/[^A-Za-z0-9_-]/', '_', $supplier['name']); ?>.pdf',
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
