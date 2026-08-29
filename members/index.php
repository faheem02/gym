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

$sql = 'SELECT m.*, t.name AS trainer_name FROM members m LEFT JOIN trainers t ON m.trainer_id = t.id';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE m.name LIKE ? OR m.phone LIKE ? OR m.email LIKE ? OR m.membership_type LIKE ? OR t.name LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like, $like];
}
$sql .= ' ORDER BY m.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

$totalMembers = count($members);
$activeCount = 0;
foreach ($members as $m) {
    if ($m['status'] === 'active') $activeCount++;
}
$inactiveCount = $totalMembers - $activeCount;
?>

<div class="search-bar">
    <div class="row g-2 align-items-center">
        <div class="col-md-5 col-lg-4">
            <form method="GET" action="" class="d-flex align-items-center gap-2">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Search by name, phone or email...">
                <button class="btn btn-dark btn-sm text-nowrap px-3" type="submit"><i class="fas fa-search me-1"></i>Search</button>
            </form>
        </div>
        <div class="col-md-7 col-lg-8 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
            <button type="button" onclick="downloadMemberListPDF();" class="btn btn-primary fw-bold" title="Download PDF"><i class="fas fa-file-pdf me-1"></i>Download PDF</button>
            <button type="button" onclick="window.print();" class="btn btn-danger fw-bold" title="Print member list"><i class="fas fa-print me-1"></i>Print</button>
            <a href="add.php" class="btn btn-warning fw-bold"><i class="fas fa-plus me-1"></i>Add Member</a>
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
                    <th>Trainer</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-users-slash me-1"></i>No members found.</td></tr>
                <?php endif; ?>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?php echo $m['id']; ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($m['name']); ?></td>
                        <td><?php echo htmlspecialchars($m['phone']); ?></td>
                        <td><?php echo !empty($m['gender']) ? ucfirst(htmlspecialchars($m['gender'])) : '<span class="text-muted">-</span>'; ?></td>
                        <td><?php echo !empty($m['membership_type']) ? '<span class="badge text-bg-info">' . htmlspecialchars($m['membership_type']) . '</span>' : '<span class="text-muted">-</span>'; ?></td>
                        <td><?php echo date('d M Y', strtotime($m['join_date'])); ?></td>
                        <td>
                            <?php if (!empty($m['trainer_name'])): ?>
                                <span class="badge text-bg-dark"><i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($m['trainer_name']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
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
            <div class="print-summary-val"><?php echo $inactiveCount; ?></div>
            <div class="print-summary-lbl">Inactive</div>
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
                <th>Trainer</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($members)): ?>
                <tr><td colspan="8" style="text-align:center;padding:20px;color:#666;">No members found.</td></tr>
            <?php endif; ?>
            <?php foreach ($members as $i => $m): ?>
            <tr class="<?php echo $i % 2 === 0 ? 'even' : ''; ?>">
                <td><?php echo $i + 1; ?></td>
                <td><?php echo htmlspecialchars($m['name']); ?></td>
                <td><?php echo htmlspecialchars($m['phone']); ?></td>
                <td><?php echo !empty($m['gender']) ? ucfirst(htmlspecialchars($m['gender'])) : '-'; ?></td>
                <td><?php echo !empty($m['membership_type']) ? htmlspecialchars($m['membership_type']) : '-'; ?></td>
                <td><?php echo date('d M Y', strtotime($m['join_date'])); ?></td>
                <td><?php echo !empty($m['trainer_name']) ? htmlspecialchars($m['trainer_name']) : '-'; ?></td>
                <td><?php echo ucfirst($m['status']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8" class="bold">Total — <?php echo $totalMembers; ?> member(s) &nbsp;|&nbsp; Active: <?php echo $activeCount; ?> &nbsp;|&nbsp; Inactive: <?php echo $inactiveCount; ?></td>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
