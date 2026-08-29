<?php
$activePage = 'canteen_sales';
$pageTitle = 'Canteen Sales';
include __DIR__ . '/../../includes/header.php';

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$method = trim($_GET['method'] ?? '');
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($dateFrom !== '') {
    $where[] = 's.sale_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 's.sale_date <= ?';
    $params[] = $dateTo;
}
if ($method !== '') {
    $where[] = 's.payment_method = ?';
    $params[] = $method;
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(s.receipt_no LIKE ? OR s.customer_name LIKE ? OR m.name LIKE ?)';
    array_push($params, $like, $like, $like);
}

$sql = "SELECT s.*, m.name AS member_name,
            (SELECT COUNT(*) FROM canteen_sale_items si WHERE si.sale_id = s.id) AS item_count,
            (SELECT GROUP_CONCAT(CONCAT(p.name, ' x', si.quantity) ORDER BY si.id SEPARATOR ', ')
             FROM canteen_sale_items si
             JOIN canteen_products p ON p.id = si.product_id
             WHERE si.sale_id = s.id) AS item_names
        FROM canteen_sales s
        LEFT JOIN members m ON m.id = s.member_id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY s.sale_date DESC, s.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Stats (filtered set)
$filteredCount = count($sales);
$filteredTotal = 0;
foreach ($sales as $sale) {
    $filteredTotal += (float)$sale['final_amount'];
}

$stmt = $pdo->query("SELECT COALESCE(SUM(final_amount),0) FROM canteen_sales WHERE sale_date = CURDATE()");
$todayTotal = (float)$stmt->fetchColumn();
$stmt = $pdo->query("SELECT COALESCE(SUM(final_amount),0) FROM canteen_sales WHERE sale_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$monthTotal = (float)$stmt->fetchColumn();

$methodMeta = [
    'cash'   => ['label' => 'Cash',   'badge' => 'text-bg-success'],
    'card'   => ['label' => 'Card',   'badge' => 'text-bg-primary'],
    'online' => ['label' => 'Online', 'badge' => 'text-bg-info'],
];
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card" style="border-top:3px solid #00b894;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(0,184,148,0.1);color:#00b894;"><i class="fas fa-calendar-day"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($todayTotal, 0); ?></h5>
                    <small class="text-muted">Today's Sales</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card" style="border-top:3px solid #6c5ce7;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(108,92,231,0.1);color:#6c5ce7;"><i class="fas fa-calendar-alt"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($monthTotal, 0); ?></h5>
                    <small class="text-muted">This Month</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card" style="border-top:3px solid #f7b731;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(247,183,49,0.1);color:#f7b731;"><i class="fas fa-coins"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($filteredTotal, 0); ?></h5>
                    <small class="text-muted">Filtered Total</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card" style="border-top:3px solid #0984e3;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(9,132,227,0.1);color:#0984e3;"><i class="fas fa-receipt"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $filteredCount; ?></h5>
                    <small class="text-muted">Transactions</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-1"></i>Sale deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-1"></i>Could not delete sale. Please try again.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="search-bar no-print mb-3">
    <form method="GET" action="">
        <div class="row g-2 align-items-center">
            <div class="col-6 col-md-3 col-lg-2">
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateFrom); ?>" title="From date" placeholder="From Date">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateTo); ?>" title="To date" placeholder="To Date">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <select name="method" class="form-select form-select-sm">
                    <option value="">All Methods</option>
                    <?php foreach ($methodMeta as $val => $meta): ?>
                        <option value="<?php echo $val; ?>" <?php echo $method === $val ? 'selected' : ''; ?>><?php echo $meta['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control form-control-sm" placeholder="Search receipt # / customer...">
            </div>
            <div class="col-12 col-lg-4 d-flex flex-wrap align-items-center justify-content-lg-end gap-2">
                <button type="submit" class="btn btn-dark btn-sm fw-semibold d-inline-flex align-items-center px-3">
                    <i class="fas fa-filter me-1"></i>Filter
                </button>
                <a href="/gym/canteen/sales/" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center px-2" title="Reset filters">
                    <i class="fas fa-times me-1"></i>Reset
                </a>
                <button type="button" onclick="downloadSalesListPDF();" class="btn btn-primary btn-sm fw-bold d-inline-flex align-items-center px-3" title="Download PDF">
                    <i class="fas fa-file-pdf me-1"></i>Download PDF
                </button>
                <button type="button" onclick="window.print();" class="btn btn-danger btn-sm fw-bold d-inline-flex align-items-center px-3" title="Print sales list">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Receipt No</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Method</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Discount</th>
                    <th class="text-end">Net Amount</th>
                    <th class="text-end">Received</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4"><i class="fas fa-receipt me-1"></i>No sales found.</td></tr>
                <?php endif; ?>
                <?php foreach ($sales as $i => $sale): ?>
                    <?php
                    $meta = $methodMeta[$sale['payment_method']] ?? ['label' => ucfirst($sale['payment_method']), 'badge' => 'text-bg-secondary'];
                    $change = max(0, (float)$sale['received_amount'] - (float)$sale['final_amount']);
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><span class="fw-semibold font-monospace small"><?php echo htmlspecialchars($sale['receipt_no'] ?? ('#' . $sale['id'])); ?></span></td>
                        <td><?php echo date('d M Y', strtotime($sale['sale_date'])); ?></td>
                        <td>
                            <?php if (!empty($sale['member_id'])): ?>
                                <a href="/gym/members/view.php?id=<?php echo $sale['member_id']; ?>" class="text-decoration-none"><i class="fas fa-user-tag me-1 text-muted"></i><?php echo htmlspecialchars($sale['member_name'] ?? 'Member #' . $sale['member_id']); ?></a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?>
                            <?php endif; ?>
                        </td>
                        <td class="small" style="max-width:280px;white-space:normal;line-height:1.4;">
                            <?php echo !empty($sale['item_names']) ? htmlspecialchars($sale['item_names']) : '<span class="text-muted">-</span>'; ?>
                        </td>
                        <td><span class="badge <?php echo $meta['badge']; ?>"><?php echo $meta['label']; ?></span></td>
                        <td class="text-end">Rs.<?php echo number_format($sale['total_amount'], 0); ?></td>
                        <td class="text-end text-danger"><?php echo (float)$sale['discount'] > 0 ? '-' . number_format($sale['discount'], 0) : '-'; ?></td>
                        <td class="text-end fw-bold text-success">Rs.<?php echo number_format($sale['final_amount'], 0); ?></td>
                        <td class="text-end"><?php echo number_format($sale['received_amount'], 0); ?><?php echo $change > 0 ? ' <small class="text-muted">(change Rs.' . number_format($change, 0) . ')</small>' : ''; ?></td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end flex-nowrap">
                                <a href="/gym/canteen/sales/thermal_receipt.php?id=<?php echo $sale['id']; ?>"
                                   class="btn btn-sm btn-outline-dark" title="Thermal Receipt"
                                   target="_blank">
                                    <i class="fas fa-receipt"></i>
                                </a>
                                <a href="/gym/canteen/sales/view.php?id=<?php echo $sale['id']; ?>"
                                   class="btn btn-sm btn-outline-info" title="View Full Invoice"
                                   target="_blank">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="/gym/canteen/sales/edit.php?id=<?php echo $sale['id']; ?>"
                                   class="btn btn-sm btn-outline-warning" title="Edit"
                                   target="_blank">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button"
                                   class="btn btn-sm btn-outline-secondary" title="Print Invoice"
                                   onclick="window.open('/gym/canteen/sales/thermal_receipt.php?id=<?php echo $sale['id']; ?>&autoprint=1','_blank','width=450,height=700');">
                                    <i class="fas fa-print"></i>
                                </button>
                                <a href="/gym/canteen/sales/delete.php?id=<?php echo $sale['id']; ?>"
                                   class="btn btn-sm btn-outline-danger" title="Delete"
                                   onclick="return confirm('Delete sale <?php echo htmlspecialchars(addslashes($sale['receipt_no'] ?? '#'.$sale['id'])); ?>? This cannot be undone.');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (!empty($sales)): ?>
            <tfoot>
                <tr style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;">
                    <td colspan="8" class="fw-bold">Totals — <?php echo $filteredCount; ?> sales</td>
                    <td class="text-end fw-bold">Rs.<?php echo number_format($filteredTotal, 0); ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- ===================== PRINT-ONLY SECTION ===================== -->
<div id="printSection">

    <!-- Letterhead -->
    <?php
    $printReportTitle = 'Canteen Sales';
    include __DIR__ . "/../../includes/print_header.php";
    ?>

    <!-- Summary boxes -->
    <div class="print-summary">
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($todayTotal, 0); ?></div>
            <div class="print-summary-lbl">Today's Sales</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val">Rs.<?php echo number_format($monthTotal, 0); ?></div>
            <div class="print-summary-lbl">This Month</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val"><?php echo $filteredCount; ?></div>
            <div class="print-summary-lbl">Transactions</div>
        </div>
        <div class="print-summary-box highlight">
            <div class="print-summary-val">Rs.<?php echo number_format($filteredTotal, 0); ?></div>
            <div class="print-summary-lbl">Net Total</div>
        </div>
    </div>

    <!-- Sales table — no Actions column -->
    <table class="print-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Receipt No.</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Method</th>
                <th class="text-right">Total (Rs.)</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Net Amount</th>
                <th class="text-right">Received</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sales)): ?>
                <tr><td colspan="10" style="text-align:center;padding:20px;color:#666;">No sales found for this period.</td></tr>
            <?php endif; ?>
            <?php foreach ($sales as $i => $sale):
                $pmeta  = $methodMeta[$sale['payment_method']] ?? ['label' => ucfirst($sale['payment_method'])];
                $chg    = max(0, (float)$sale['received_amount'] - (float)$sale['final_amount']);
            ?>
            <tr class="<?php echo $i % 2 === 0 ? 'even' : ''; ?>">
                <td><?php echo $i + 1; ?></td>
                <td class="mono"><?php echo htmlspecialchars($sale['receipt_no'] ?? ('#' . $sale['id'])); ?></td>
                <td><?php echo date('d M Y', strtotime($sale['sale_date'])); ?></td>
                <td>
                    <?php
                    if (!empty($sale['member_id']))  echo htmlspecialchars($sale['member_name'] ?? 'Member #'.$sale['member_id']);
                    elseif (!empty($sale['customer_name'])) echo htmlspecialchars($sale['customer_name']);
                    else echo 'Walk-in';
                    ?>
                </td>
                <td style="text-align:center;"><?php echo !empty($sale['item_names']) ? '<span class="items-list">' . htmlspecialchars($sale['item_names']) . '</span>' : '-'; ?></td>
                <td><?php echo $pmeta['label']; ?></td>
                <td class="text-right"><?php echo number_format($sale['total_amount'], 2); ?></td>
                <td class="text-right"><?php echo (float)$sale['discount'] > 0 ? number_format($sale['discount'], 2) : '—'; ?></td>
                <td class="text-right bold"><?php echo number_format($sale['final_amount'], 2); ?></td>
                <td class="text-right"><?php echo number_format($sale['received_amount'], 2); ?><?php echo $chg > 0 ? '<br><span class="chg">chg: '.number_format($chg,0).'</span>' : ''; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="bold">Total — <?php echo $filteredCount; ?> transaction(s)</td>
                <td class="text-right bold"><?php echo number_format(array_sum(array_column($sales,'total_amount')), 2); ?></td>
                <td class="text-right bold"><?php echo number_format(array_sum(array_column($sales,'discount')), 2); ?></td>
                <td class="text-right bold">Rs. <?php echo number_format($filteredTotal, 2); ?></td>
                <td class="text-right bold"><?php echo number_format(array_sum(array_column($sales,'received_amount')), 2); ?></td>
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
#printSection .print-table .mono { font-family: monospace; font-size: 10px; }
#printSection .print-table .chg { font-size: 8.5px; color: #888; }
#printSection .print-table .items-list { font-size: 9.5px; color: #333; display: block; max-width: 200px; white-space: normal; line-height: 1.4; margin: 0 auto; }

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
    .row.g-3.mb-4, .card, script { display: none !important; }

    body { background: #fff !important; margin: 0; padding: 0; font-family: Arial, sans-serif; color: #000; }
    .layout-wrapper { display: block !important; }
    .main-content { margin: 0 !important; width: 100% !important; min-height: unset; }
    .content { padding: 0 !important; }

    #printSection { display: block !important; padding: 18px 24px; }
    @page { margin: 12mm 10mm; size: A4 landscape; }
}
</style>

<!-- Include html2pdf.js for A4 Landscape PDF Download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadSalesListPDF() {
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
        filename:     'Canteen_Sales_Report.pdf',
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
