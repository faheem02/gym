<?php
$activePage = 'day_passes';
$pageTitle = 'Day Passes';
include __DIR__ . '/../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'issued') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Day pass issued successfully.</div>';
if ($msg === 'checkedout') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Visitor checked out.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Day pass deleted.</div>';

$date = trim($_GET['date'] ?? date('Y-m-d'));

$stmt = $pdo->prepare(
    "SELECT dp.*, m.name AS member_name
     FROM day_passes dp
     LEFT JOIN members m ON m.id = dp.member_id
     WHERE dp.pass_date = ?
     ORDER BY dp.check_in_time DESC"
);
$stmt->execute([$date]);
$passes = $stmt->fetchAll();

$todayRevenue = 0;
foreach ($passes as $p) {
    $todayRevenue += $p['amount'];
}

$currentlyInside = 0;
foreach ($passes as $p) {
    if ($p['check_out_time'] === null) $currentlyInside++;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    $passId = (int)($_POST['pass_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE day_passes SET check_out_time = ? WHERE id = ? AND check_out_time IS NULL");
    $stmt->execute([date('H:i:s'), $passId]);
    header('Location: /gym/day_passes/index.php?msg=checkedout&date=' . urlencode($date));
    exit;
}
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary"><i class="fas fa-ticket-alt"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo count($passes); ?></h5>
                    <small class="text-muted">Today's Passes</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($todayRevenue, 0); ?></h5>
                    <small class="text-muted">Today's Revenue</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning"><i class="fas fa-door-open"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $currentlyInside; ?></h5>
                    <small class="text-muted">Currently Inside</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="search-bar">
    <div class="row g-2 align-items-center">
        <div class="col-md-7 col-lg-8">
            <form method="GET" action="" class="d-flex">
                <input type="date" name="date" class="form-control me-2" value="<?php echo $date; ?>">
                <button class="btn btn-dark"><i class="fas fa-filter me-1"></i>Filter</button>
            </form>
        </div>
        <div class="col-md-5 col-lg-4 text-md-end">
            <a href="add.php" class="btn btn-warning fw-bold"><i class="fas fa-plus me-1"></i>Issue Day Pass</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Visitor</th>
                    <th>Phone</th>
                    <th>Type</th>
                    <th>Related Member</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Amount</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($passes)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-ticket-alt me-1"></i>No day passes for this date.</td></tr>
                <?php endif; ?>
                <?php foreach ($passes as $i => $p): ?>
                    <?php
                        $typeLabels = [
                            'gym' => ['Gym Access', 'primary'],
                            'kids_play' => ['Kids Play Area', 'success'],
                            'both' => ['Gym + Kids Play', 'warning'],
                        ];
                        $label = $typeLabels[$p['pass_type']] ?? ['Unknown', 'secondary'];
                        $duration = '';
                        if ($p['check_out_time']) {
                            $mins = (strtotime($p['check_out_time']) - strtotime($p['check_in_time'])) / 60;
                            $hrs = floor($mins / 60);
                            $mins = $mins % 60;
                            $duration = $hrs . 'h ' . $mins . 'm';
                        }
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($p['visitor_name']); ?></td>
                        <td><?php echo htmlspecialchars($p['phone'] ?? '-'); ?></td>
                        <td><span class="badge text-bg-<?php echo $label[1]; ?>"><?php echo $label[0]; ?></span></td>
                        <td><?php echo $p['member_name'] ? htmlspecialchars($p['member_name']) : '<span class="text-muted">Walk-in</span>'; ?></td>
                        <td><i class="fas fa-clock text-muted me-1"></i><?php echo date('h:i A', strtotime($p['check_in_time'])); ?></td>
                        <td>
                            <?php if ($p['check_out_time']): ?>
                                <span class="text-success"><i class="fas fa-check-circle me-1"></i><?php echo date('h:i A', strtotime($p['check_out_time'])); ?></span>
                                <?php if ($duration): ?>
                                    <br><small class="text-muted"><?php echo $duration; ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge-active"><i class="fas fa-circle me-1" style="font-size:0.5rem;vertical-align:middle;"></i>Inside</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold">Rs.<?php echo number_format($p['amount'], 0); ?></td>
                        <td class="text-end">
                            <?php if (!$p['check_out_time']): ?>
                                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Check out this visitor?');">
                                    <input type="hidden" name="action" value="checkout">
                                    <input type="hidden" name="pass_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Check Out"><i class="fas fa-sign-out-alt"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
