<?php
$activePage = 'member_fee_due';
$pageTitle = 'Fee Due Report';
include __DIR__ . '/../includes/header.php';

$pdo->exec("UPDATE subscriptions SET status = 'expired' WHERE end_date < CURDATE() AND status = 'active'");

$today = date('Y-m-d');
$in7 = date('Y-m-d', strtotime('+7 days'));

// Gather all active members who have a subscription (active or expired) plus their monthly fee
$rows = $pdo->query(
    "SELECT m.id, m.name, m.phone, m.monthly_fee, m.status AS member_status,
            s.start_date, s.end_date, s.status AS sub_status,
            s.id AS sub_id,
            p.name AS plan_name
     FROM members m
     JOIN subscriptions s ON s.member_id = m.id
     LEFT JOIN plans p ON p.id = s.plan_id
     WHERE m.status = 'active'
     ORDER BY s.end_date ASC"
)->fetchAll();

$overdue = [];
$dueSoon = [];
$current = [];

foreach ($rows as $r) {
    $fee = (float)($r['monthly_fee'] ?? 0);
    $end = $r['end_date'];
    $cat = null;

    if ($r['sub_status'] === 'active' && $end >= $today && $end <= $in7) {
        $cat = 'due_soon';
    } elseif ($r['sub_status'] === 'active' && $end > $in7) {
        $cat = 'current';
    } else {
        $cat = 'overdue'; // expired or inactive subscription
    }

    $item = [
        'id'       => $r['id'],
        'name'     => $r['name'],
        'phone'    => $r['phone'],
        'monthly_fee' => $fee,
        'plan'     => $r['plan_name'],
        'end_date' => $end,
        'days_left' => (int)((strtotime($end) - time()) / 86400),
        'sub_id'   => $r['sub_id'],
    ];

    if ($cat === 'overdue') $overdue[] = $item;
    elseif ($cat === 'due_soon') $dueSoon[] = $item;
    else $current[] = $item;
}

$sectionConfig = [
    'overdue'   => ['label' => 'Overdue / Expired', 'icon' => 'fa-exclamation-triangle', 'class' => 'danger', 'rows' => $overdue],
    'due_soon'  => ['label' => 'Due Within 7 Days', 'icon' => 'fa-hourglass-half', 'class' => 'warning', 'rows' => $dueSoon],
    'current'   => ['label' => 'Currently Paid Up', 'icon' => 'fa-check-circle', 'class' => 'success', 'rows' => $current],
];

$totalDue = 0;
foreach ($overdue as $o) $totalDue += $o['monthly_fee'];
foreach ($dueSoon as $s) $totalDue += $s['monthly_fee'];

// Search / filter
$search = trim($_GET['q'] ?? '');
$catFilter = $_GET['cat'] ?? '';
function feeDueMatch($item, $search) {
    return $search === '' ||
        stripos($item['name'], $search) !== false ||
        stripos($item['phone'] ?? '', $search) !== false ||
        stripos($item['plan'] ?? '', $search) !== false;
}
if ($search !== '') {
    $overdue = array_values(array_filter($overdue, fn($i) => feeDueMatch($i, $search)));
    $dueSoon = array_values(array_filter($dueSoon, fn($i) => feeDueMatch($i, $search)));
    $current = array_values(array_filter($current, fn($i) => feeDueMatch($i, $search)));
}
if ($catFilter !== '') {
    $allowed = ['overdue', 'due_soon', 'current'];
    if (in_array($catFilter, $allowed)) {
        $keep = [$catFilter => true];
        foreach ($allowed as $a) if (!isset($keep[$a])) ${$a} = [];
    }
}
?>

<div class="search-bar">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h6 class="fw-bold mb-0"><i class="fas fa-calendar-check me-2 text-warning"></i>Monthly Fee Due Report</h6>
            <small class="text-muted">Members whose subscription is ending or has ended &mdash; these are the members due for their monthly fee.</small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" onclick="window.print();" class="btn btn-danger fw-bold btn-sm"><i class="fas fa-print me-1"></i>Print</button>
            <button type="button" class="btn btn-primary fw-bold btn-sm" onclick="exportElementToPDF('printArea','Fee_Due_Report.pdf')"><i class="fas fa-file-pdf me-1"></i>Download PDF</button>
        </div>
    </div>
</div>

<!-- Search + category filter -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="" class="d-flex flex-wrap gap-2 align-items-center">
            <div class="input-group flex-grow-1" style="min-width:220px;">
                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Search by name, phone or diet plan..." value="<?php echo htmlspecialchars($search); ?>">
                <?php if ($catFilter !== ''): ?><input type="hidden" name="cat" value="<?php echo htmlspecialchars($catFilter); ?>"><?php endif; ?>
            </div>
            <select name="cat" class="form-select" style="width:auto;">
                <option value="">All Categories</option>
                <option value="overdue" <?php echo $catFilter === 'overdue' ? 'selected' : ''; ?>>Overdue / Expired</option>
                <option value="due_soon" <?php echo $catFilter === 'due_soon' ? 'selected' : ''; ?>>Due Within 7 Days</option>
                <option value="current" <?php echo $catFilter === 'current' ? 'selected' : ''; ?>>Currently Paid Up</option>
            </select>
            <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-filter me-1"></i>Filter</button>
            <?php if ($search !== '' || $catFilter !== ''): ?>
                <a href="fee_due.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i>Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo count($overdue); ?></h5>
                    <small class="text-muted">Overdue</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning"><i class="fas fa-hourglass-half"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo count($dueSoon); ?></h5>
                    <small class="text-muted">Due in 7 Days</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success"><i class="fas fa-check-circle"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo count($current); ?></h5>
                    <small class="text-muted">Paid Up</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-dark"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalDue, 0); ?></h5>
                    <small class="text-muted">Total Monthly Due</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="printArea">
    <?php
    $printReportTitle = 'Monthly Fee Due Report';
    $printMeta = 'Generated on ' . date('d M Y') . ' | Total Due: Rs. ' . number_format($totalDue, 0);
    ?>
    <div class="print-letterhead d-none d-print-block">
        <?php include __DIR__ . '/../includes/print_header.php'; ?>
    </div>
    <?php foreach ($sectionConfig as $key => $section): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3 text-<?php echo $section['class']; ?>">
                    <i class="fas <?php echo $section['icon']; ?> me-2"></i><?php echo $section['label']; ?>
                    <span class="badge text-bg-<?php echo $section['class']; ?> ms-1"><?php echo count($section['rows']); ?></span>
                </h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member</th>
                                <th>Phone</th>
                                <th>Diet Plan</th>
                                <th>Monthly Fee</th>
                                <th>Subscription End</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($section['rows'])): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No members in this category.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($section['rows'] as $i => $r): ?>
                                <?php
                                if ($section['class'] === 'danger') {
                                    $statusBadge = 'text-bg-danger';
                                    $statusLabel = 'Overdue';
                                } elseif ($section['class'] === 'warning') {
                                    $statusBadge = 'text-bg-warning';
                                    $statusLabel = $r['days_left'] > 0 ? $r['days_left'] . ' day(s) left' : 'Due Now';
                                } else {
                                    $statusBadge = 'text-bg-success';
                                    $statusLabel = $r['days_left'] . ' days left';
                                }
                                ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($r['name']); ?></td>
                                    <td><?php echo htmlspecialchars($r['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($r['plan'] ?? '-'); ?></td>
                                    <td class="fw-bold text-success">Rs.<?php echo number_format($r['monthly_fee'], 0); ?>/mo</td>
                                    <td><?php echo date('d M Y', strtotime($r['end_date'])); ?></td>
                                    <td><span class="badge <?php echo $statusBadge; ?>"><?php echo $statusLabel; ?></span></td>
                                    <td class="text-end">
                                        <div class="btn-group-actions d-inline-flex gap-1">
                                            <a href="view.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="fas fa-eye"></i></a>
                                            <a href="payments.php?member_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-success" title="Record Payment"><i class="fas fa-money-bill-wave"></i></a>
                                            <a href="ledger.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-dark" title="Ledger"><i class="fas fa-book"></i></a>
                                            <?php if ($section['class'] === 'danger'): ?>
                                                <a href="/gym/subscriptions/renew.php?id=<?php echo $r['sub_id']; ?>" class="btn btn-sm btn-outline-warning" title="Renew"><i class="fas fa-sync-alt"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/pdf_helper.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
