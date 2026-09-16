<?php
$activePage = 'members';
$pageTitle = 'Members';
include __DIR__ . '/../includes/header.php';

$search = trim($_GET['q'] ?? '');
$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Member added successfully.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Member updated successfully.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Member deleted.</div>';
if ($msg === 'delete_failed') echo '<div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i>Member could not be deleted because related records exist. Delete their payments/subscriptions first.</div>';

$sql = "SELECT m.*, t.name AS trainer_name,
    (SELECT mp.weight FROM member_payments mp WHERE mp.member_id = m.id AND mp.weight IS NOT NULL ORDER BY mp.id DESC LIMIT 1) AS latest_weight
    FROM members m LEFT JOIN trainers t ON m.trainer_id = t.id";
$params = [];
if ($search !== '') {
    $sql .= ' WHERE m.name LIKE ? OR m.phone LIKE ? OR m.membership_type LIKE ? OR t.name LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY m.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

$financialSummaries = getAllMembersFinancialSummary($pdo);

$totalMembers = count($members);
$activeCount = 0;
$totalOutstandingDues = 0;
$membersWithDuesCount = 0;

foreach ($members as $m) {
    if ($m['status'] === 'active') $activeCount++;
    $mBal = (float)($financialSummaries[$m['id']]['balance'] ?? 0);
    if ($mBal > 0) {
        $totalOutstandingDues += $mBal;
        $membersWithDuesCount++;
    }
}
$inactiveCount = $totalMembers - $activeCount;

$dueFilter = ($_GET['due'] ?? '') === '1';
if ($dueFilter) {
    $members = array_values(array_filter($members, function($m) use ($financialSummaries) {
        return (float)($financialSummaries[$m['id']]['balance'] ?? 0) > 0;
    }));
}
?>

<div class="search-bar">
    <div class="row g-2 align-items-center">
        <div class="col-md-5 col-lg-4">
            <form method="GET" action="" class="d-flex align-items-center gap-2">
                <?php if ($dueFilter): ?>
                    <input type="hidden" name="due" value="1">
                <?php endif; ?>
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Search by name or phone...">
                <button class="btn btn-dark btn-sm text-nowrap px-3" type="submit"><i class="fas fa-search me-1"></i>Search</button>
                <?php if ($search !== '' || $dueFilter): ?>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Clear filters"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>
        <div class="col-md-7 col-lg-8 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
            <?php if ($totalOutstandingDues > 0): ?>
                <span class="badge py-2 px-3 fw-bold" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-size:0.85rem;" title="Total unpaid balance across all members">
                    <i class="fas fa-exclamation-circle me-1"></i>Total Due: Rs.<?php echo number_format($totalOutstandingDues, 0); ?>
                </span>
            <?php endif; ?>
            <div class="btn-group btn-group-sm">
                <a href="index.php<?php echo $search ? '?q=' . urlencode($search) : ''; ?>" class="btn <?php echo !$dueFilter ? 'btn-dark' : 'btn-outline-dark'; ?>">
                    All (<?php echo $totalMembers; ?>)
                </a>
                <a href="index.php?due=1<?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="btn <?php echo $dueFilter ? 'btn-danger' : 'btn-outline-danger'; ?>">
                    <i class="fas fa-exclamation-circle me-1"></i>With Dues (<?php echo $membersWithDuesCount; ?>)
                </a>
            </div>
            <button type="button" onclick="downloadMemberListPDF();" class="btn btn-primary fw-bold btn-sm" title="Download PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>
            <button type="button" onclick="window.print();" class="btn btn-danger fw-bold btn-sm" title="Print member list"><i class="fas fa-print me-1"></i>Print</button>
            <a href="add.php" class="btn btn-warning fw-bold btn-sm"><i class="fas fa-plus me-1"></i>Add Member</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Gender</th>
                    <th>Membership</th>
                    <th>Join Date</th>
                    <th>Weight</th>
                    <th>Trainer</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4"><i class="fas fa-users-slash me-1"></i>No members found.</td></tr>
                <?php endif; ?>
                <?php foreach ($members as $m): 
                    $fin = $financialSummaries[$m['id']] ?? null;
                    $bal = $fin ? (float)$fin['balance'] : 0;
                ?>
                    <tr>
                        <td><?php echo $m['id']; ?></td>
                        <td class="fw-semibold">
                            <a href="view.php?id=<?php echo $m['id']; ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($m['name']); ?></a>
                        </td>
                        <td><?php echo htmlspecialchars($m['phone']); ?></td>
                        <td><?php echo !empty($m['gender']) ? ucfirst(htmlspecialchars($m['gender'])) : '<span class="text-muted">-</span>'; ?></td>
                        <td>
                            <?php 
                            $at = $m['access_type'] ?? 'gym';
                            $atClass = $at === 'kids_play' ? 'text-bg-success' : ($at === 'both' ? 'text-bg-warning' : 'text-bg-primary');
                            $atIcon = $at === 'kids_play' ? 'fa-child' : ($at === 'both' ? 'fa-users' : 'fa-dumbbell');
                            $atLabel = $at === 'kids_play' ? 'Kids' : ($at === 'both' ? 'Gym+Kids' : 'Gym');
                            ?>
                            <span class="badge <?php echo $atClass; ?> me-1"><i class="fas <?php echo $atIcon; ?> me-1"></i><?php echo $atLabel; ?></span>
                            <?php echo !empty($m['membership_type']) ? '<span class="badge text-bg-light border text-dark">' . htmlspecialchars($m['membership_type']) . '</span>' : ''; ?>
                            <?php if ((float)($m['monthly_fee'] ?? 0) > 0): ?>
                                <div class="small fw-semibold text-primary mt-1"><i class="fas fa-calendar-check me-1"></i>Rs. <?php echo number_format((float)$m['monthly_fee'], 0); ?>/mo</div>
                            <?php endif; ?>
                            <?php if ((float)($m['kids_fee'] ?? 0) > 0): ?>
                                <div class="small fw-semibold text-success mt-1"><i class="fas fa-child me-1"></i>Rs. <?php echo number_format((float)$m['kids_fee'], 0); ?>/mo</div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d M Y', strtotime($m['join_date'])); ?></td>
                        <td>
                            <?php
                            $dispW = !empty($m['latest_weight']) ? $m['latest_weight'] : $m['weight'];
                            if (!empty($dispW)): ?>
                                <span class="fw-semibold"><i class="fas fa-weight-hanging me-1 text-muted"></i><?php echo number_format((float)$dispW, 1); ?> kg</span>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($m['trainer_name'])): ?>
                                <span class="badge text-bg-dark"><i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($m['trainer_name']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($bal > 0): ?>
                                <a href="ledger.php?id=<?php echo $m['id']; ?>" class="text-decoration-none" title="Gross: Rs.<?php echo number_format($fin['gross_total'], 0); ?> | Paid: Rs.<?php echo number_format($fin['total_paid'], 0); ?> &bull; Click to open ledger">
                                    <span class="badge text-bg-danger fw-bold d-inline-flex align-items-center gap-1" style="font-size:0.82rem;">
                                        <i class="fas fa-exclamation-circle"></i>Due: Rs.<?php echo number_format($bal, 0); ?>
                                    </span>
                                </a>
                                <a href="payments.php?member_id=<?php echo $m['id']; ?>&record=1" class="btn btn-xs btn-outline-success py-0 px-2 mt-1 d-block text-nowrap fw-semibold" style="font-size:0.72rem;">
                                    <i class="fas fa-hand-holding-usd me-1"></i>Receive
                                </a>
                            <?php elseif ($bal < 0): ?>
                                <a href="ledger.php?id=<?php echo $m['id']; ?>" class="text-decoration-none">
                                    <span class="badge text-bg-info text-dark fw-semibold" title="Advance Payment">
                                        <i class="fas fa-arrow-down me-1"></i>Adv: Rs.<?php echo number_format(abs($bal), 0); ?>
                                    </span>
                                </a>
                            <?php else: ?>
                                <span class="badge text-bg-success fw-normal">
                                    <i class="fas fa-check-circle me-1"></i>Settled
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $m['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo ucfirst($m['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group-actions d-inline-flex gap-1">
                                <a href="slip.php?id=<?php echo $m['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Slip"><i class="fas fa-print"></i></a>
                                <a href="weight_history.php?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-warning" title="Weight History"><i class="fas fa-weight-hanging"></i></a>
                                <a href="view.php?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="fas fa-eye"></i></a>
                                <a href="edit.php?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                                <a href="ledger.php?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-dark" title="Ledger"><i class="fas fa-book"></i></a>
                                <a href="delete.php?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this member? This also deletes their subscriptions.');"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===================== PRINT-ONLY SECTION ===================== -->
<div id="printSection">

    <!-- Letterhead -->
    <?php
    $printReportTitle = 'Members List';
    include __DIR__ . "/../includes/print_header.php";
    ?>

    <!-- Summary boxes -->
    <div class="print-summary">
        <div class="print-summary-box">
            <div class="print-summary-val"><?php echo $totalMembers; ?></div>
            <div class="print-summary-lbl">Total Members</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val"><?php echo $activeCount; ?></div>
            <div class="print-summary-lbl">Active</div>
        </div>
        <div class="print-summary-box highlight">
            <div class="print-summary-val">Rs.<?php echo number_format($totalOutstandingDues, 0); ?></div>
            <div class="print-summary-lbl">Total Outstanding Due (<?php echo $membersWithDuesCount; ?>)</div>
        </div>
    </div>

    <!-- Members table -->
    <table class="print-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Gender</th>
                <th>Membership</th>
                <th>Join Date</th>
                <th>Weight</th>
                <th>Trainer</th>
                <th>Balance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($members)): ?>
                <tr><td colspan="10" style="text-align:center;padding:20px;color:#666;">No members found.</td></tr>
            <?php endif; ?>
            <?php foreach ($members as $i => $m): 
                $fin = $financialSummaries[$m['id']] ?? null;
                $bal = $fin ? (float)$fin['balance'] : 0;
            ?>
            <tr class="<?php echo $i % 2 === 0 ? 'even' : ''; ?>">
                <td><?php echo $i + 1; ?></td>
                <td><?php echo htmlspecialchars($m['name']); ?></td>
                <td><?php echo htmlspecialchars($m['phone']); ?></td>
                <td><?php echo !empty($m['gender']) ? ucfirst(htmlspecialchars($m['gender'])) : '-'; ?></td>
                <td><?php echo !empty($m['membership_type']) ? htmlspecialchars($m['membership_type']) : '-'; ?></td>
                <td><?php echo date('d M Y', strtotime($m['join_date'])); ?></td>
                <td><?php $dispW = !empty($m['latest_weight']) ? $m['latest_weight'] : $m['weight']; echo !empty($dispW) ? number_format((float)$dispW, 1) . ' kg' : '-'; ?></td>
                <td><?php echo !empty($m['trainer_name']) ? htmlspecialchars($m['trainer_name']) : '-'; ?></td>
                <td>
                    <?php if ($bal > 0): ?>
                        <strong>Due: Rs.<?php echo number_format($bal, 0); ?></strong>
                    <?php elseif ($bal < 0): ?>
                        Adv: Rs.<?php echo number_format(abs($bal), 0); ?>
                    <?php else: ?>
                        Settled
                    <?php endif; ?>
                </td>
                <td><?php echo ucfirst($m['status']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="10" class="bold">Total &mdash; <?php echo $totalMembers; ?> member(s) &nbsp;|&nbsp; Active: <?php echo $activeCount; ?> &nbsp;|&nbsp; Total Due: Rs.<?php echo number_format($totalOutstandingDues, 0); ?></td>
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

<script src="/gym/assets/vendor/html2pdf/html2pdf.bundle.min.js"></script>
<script>
function downloadMemberListPDF() {
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
        filename:     'Members_List_Report.pdf',
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
