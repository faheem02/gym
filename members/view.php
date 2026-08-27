<?php
$activePage = 'members';
$pageTitle = 'Member Profile';
include __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT m.*, t.name AS trainer_name, t.phone AS trainer_phone FROM members m LEFT JOIN trainers t ON t.id = m.trainer_id WHERE m.id = ?');
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    echo '<div class="alert alert-warning">Member not found. <a href="index.php">Back to members</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Active subscription
$stmt = $pdo->prepare("SELECT s.*, p.name AS plan_name, p.price, p.duration_days FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.member_id = ? AND s.status = 'active' ORDER BY s.end_date DESC LIMIT 1");
$stmt->execute([$id]);
$activeSub = $stmt->fetch();

// Subscription history
$stmt = $pdo->prepare('SELECT s.*, p.name AS plan_name, p.price FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.member_id = ? ORDER BY s.start_date DESC, s.id DESC');
$stmt->execute([$id]);
$subscriptions = $stmt->fetchAll();

// Payments
$stmt = $pdo->prepare('SELECT * FROM member_payments WHERE member_id = ? ORDER BY payment_date DESC, id DESC LIMIT 10');
$stmt->execute([$id]);
$payments = $stmt->fetchAll();
$totalPaid = 0;
foreach ($payments as $p) $totalPaid += (float)$p['amount'];
$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) AS total FROM member_payments WHERE member_id = ?');
$stmt->execute([$id]);
$totalPaidAll = (float)$stmt->fetch()['total'];

// Attendance
$stmt = $pdo->prepare('SELECT * FROM attendance WHERE member_id = ? ORDER BY check_in_date DESC, check_in_time DESC LIMIT 10');
$stmt->execute([$id]);
$attendance = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM attendance WHERE member_id = ?');
$stmt->execute([$id]);
$totalVisits = (int)$stmt->fetch()['total'];

// Balance
$stmt = $pdo->prepare("SELECT COALESCE(SUM(p.price),0) AS total FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.member_id = ?");
$stmt->execute([$id]);
$totalPlanValue = (float)$stmt->fetch()['total'];
$balance = $totalPlanValue - $totalPaidAll;

$daysLeft = $activeSub ? (int)((strtotime($activeSub['end_date']) - time()) / 86400) : 0;
?>

<div class="mb-4 d-flex gap-2">
    <a href="index.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalPaidAll, 0); ?></h5>
                    <small class="text-muted">Total Paid</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning"><i class="fas fa-clipboard-list"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalPlanValue, 0); ?></h5>
                    <small class="text-muted">Total Plan Value</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon <?php echo $balance > 0 ? 'bg-danger' : 'bg-primary'; ?>"><i class="fas fa-balance-scale"></i></div>
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
                <div class="stat-icon bg-info"><i class="fas fa-door-open"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $totalVisits; ?></h5>
                    <small class="text-muted">Total Visits</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card h-100" style="border-top:3px solid #f7b731;">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="stat-icon" style="width:64px;height:64px;font-size:1.6rem;background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;box-shadow:0 4px 15px rgba(247,183,49,0.3);">
                        <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($member['name']); ?></h5>
                        <span class="badge <?php echo $member['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>"><?php echo ucfirst($member['status']); ?></span>
                        <span class="badge bg-dark ms-1">#<?php echo $member['id']; ?></span>
                    </div>
                </div>
                <ul class="list-unstyled mb-0">
                    <li class="mb-3"><i class="fas fa-phone text-muted me-2"></i><span class="text-muted">Phone:</span> <span class="fw-semibold"><?php echo htmlspecialchars($member['phone']); ?></span></li>
                    <li class="mb-3"><i class="fas fa-envelope text-muted me-2"></i><span class="text-muted">Email:</span> <span class="fw-semibold"><?php echo htmlspecialchars($member['email'] ?? '-'); ?></span></li>
                    <?php if (!empty($member['date_of_birth'])): ?>
                    <li class="mb-3"><i class="fas fa-birthday-cake text-muted me-2"></i><span class="text-muted">Date of Birth:</span> <span class="fw-semibold"><?php echo date('d M Y', strtotime($member['date_of_birth'])); ?></span></li>
                    <?php endif; ?>
                    <?php if (!empty($member['gender'])): ?>
                    <li class="mb-3"><i class="fas fa-venus-mars text-muted me-2"></i><span class="text-muted">Gender:</span> <span class="fw-semibold"><?php echo ucfirst(htmlspecialchars($member['gender'])); ?></span></li>
                    <?php endif; ?>
                    <?php if (!empty($member['membership_type'])): ?>
                    <li class="mb-3"><i class="fas fa-id-card text-muted me-2"></i><span class="text-muted">Membership:</span> <span class="fw-semibold"><?php echo htmlspecialchars($member['membership_type']); ?></span></li>
                    <?php endif; ?>
                    <?php if (!empty($member['area_of_interest'])): ?>
                    <li class="mb-3"><i class="fas fa-heart text-muted me-2"></i><span class="text-muted">Interests:</span> <span class="fw-semibold"><?php echo htmlspecialchars($member['area_of_interest']); ?></span></li>
                    <?php endif; ?>
                    <li class="mb-3"><i class="fas fa-calendar text-muted me-2"></i><span class="text-muted">Join Date:</span> <span class="fw-semibold"><?php echo date('d M Y', strtotime($member['join_date'])); ?></span></li>
                    <li class="mb-0"><i class="fas fa-user-tie text-muted me-2"></i><span class="text-muted">Trainer:</span> <span class="fw-semibold"><?php echo htmlspecialchars($member['trainer_name'] ?? 'No Trainer Assigned'); ?></span><?php if (!empty($member['trainer_phone'])): ?> <small class="text-muted">(<?php echo htmlspecialchars($member['trainer_phone']); ?>)</small><?php endif; ?></li>
                </ul>
                <div class="d-flex gap-2 mt-4">
                    <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-secondary fw-bold"><i class="fas fa-pen me-1"></i>Edit</a>
                    <a href="ledger.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-dark fw-bold"><i class="fas fa-book me-1"></i>Ledger</a>
                    <a href="payments.php?member_id=<?php echo $id; ?>" class="btn btn-sm btn-success fw-bold"><i class="fas fa-plus me-1"></i>Payment</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100" style="border-top:3px solid #8b5cf6;">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-id-card me-2" style="color:#8b5cf6;"></i>Subscription Status</h6>
                <?php if ($activeSub): ?>
                    <div class="alert alert-info mb-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <strong><?php echo htmlspecialchars($activeSub['plan_name']); ?> Plan</strong>
                                <span class="badge bg-success ms-2">Active</span><br>
                                <small class="text-muted"><?php echo date('d M Y', strtotime($activeSub['start_date'])); ?> &mdash; <?php echo date('d M Y', strtotime($activeSub['end_date'])); ?> (Rs.<?php echo number_format($activeSub['price'], 0); ?>)</small>
                            </div>
                            <span class="badge <?php echo $daysLeft > 7 ? 'bg-success' : ($daysLeft > 0 ? 'bg-warning text-dark' : 'bg-danger'); ?> fs-6">
                                <?php echo $daysLeft > 0 ? $daysLeft . ' days left' : 'Expired'; ?>
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-1"></i>No active subscription. <a href="/gym/subscriptions/add.php">Assign a plan</a></div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Plan</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if (empty($subscriptions)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No subscriptions yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($subscriptions as $s): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($s['plan_name']); ?> <small class="text-muted">(Rs.<?php echo number_format($s['price'], 0); ?>)</small></td>
                                    <td><?php echo date('d M Y', strtotime($s['start_date'])); ?></td>
                                    <td><?php echo date('d M Y', strtotime($s['end_date'])); ?></td>
                                    <td><span class="badge <?php echo $s['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo ucfirst($s['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100" style="border-top:3px solid #10b981;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-hand-holding-usd me-2" style="color:#10b981;"></i>Recent Payments</h6>
                    <a href="ledger.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-dark">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Date</th><th>For</th><th>Method</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No payments found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($p['payment_for'] ?? '-'); ?></td>
                                    <td><small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></small></td>
                                    <td class="text-end fw-bold text-success">Rs.<?php echo number_format($p['amount'], 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100" style="border-top:3px solid #3b82f6;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-calendar-check me-2" style="color:#3b82f6;"></i>Recent Visits</h6>
                    <span class="badge bg-dark"><?php echo $totalVisits; ?> total</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Date</th><th>Check In</th><th>Check Out</th></tr></thead>
                        <tbody>
                            <?php if (empty($attendance)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">No visits recorded.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($attendance as $a): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($a['check_in_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($a['check_in_time'])); ?></td>
                                    <td><?php echo $a['check_out_time'] ? date('h:i A', strtotime($a['check_out_time'])) : '<span class="text-muted">-</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
