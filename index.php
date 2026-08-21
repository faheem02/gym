<?php
$activePage = 'dashboard';
$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';

$pdo->exec("UPDATE subscriptions SET status = 'expired' WHERE end_date < CURDATE() AND status = 'active'");

$totalMembers = (int)$pdo->query('SELECT COUNT(*) FROM members')->fetchColumn();
$activeSubs = (int)$pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'")->fetchColumn();
$expiringSoon = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
$todayCheckins = (int)$pdo->query('SELECT COUNT(*) FROM attendance WHERE check_in_date = CURDATE()')->fetchColumn();
$totalTrainers = (int)$pdo->query('SELECT COUNT(*) FROM trainers')->fetchColumn();

$recentMembers = $pdo->query('SELECT id, name, phone, join_date, status FROM members ORDER BY id DESC LIMIT 5')->fetchAll();
$expiring = $pdo->query(
    "SELECT m.name, p.name AS plan_name, s.end_date
     FROM subscriptions s
     JOIN members m ON m.id = s.member_id
     JOIN plans p ON p.id = s.plan_id
     WHERE s.status = 'active' AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     ORDER BY s.end_date LIMIT 5"
)->fetchAll();
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary"><i class="fas fa-users"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $totalMembers; ?></h5>
                    <small class="text-muted">Total Members</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success"><i class="fas fa-check-circle"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $activeSubs; ?></h5>
                    <small class="text-muted">Active Subscriptions</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning"><i class="fas fa-clock"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $expiringSoon; ?></h5>
                    <small class="text-muted">Expiring in 7 Days</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $todayCheckins; ?></h5>
                    <small class="text-muted">Today's Check-ins</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-user-plus text-primary me-2"></i>Recently Added Members</h6>
                    <a href="/gym/members/" class="btn btn-sm btn-outline-dark">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Name</th><th>Phone</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentMembers as $m): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($m['name']); ?></td>
                                    <td><?php echo htmlspecialchars($m['phone']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $m['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                            <?php echo ucfirst($m['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Subscriptions Expiring Soon</h6>
                    <a href="/gym/subscriptions/" class="btn btn-sm btn-outline-dark">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Member</th><th>Plan</th><th>Ends On</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expiring)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4"><i class="fas fa-check-circle me-1"></i>No subscriptions expiring soon.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($expiring as $e): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($e['name']); ?></td>
                                    <td><?php echo htmlspecialchars($e['plan_name']); ?></td>
                                    <td><span class="badge text-bg-warning"><?php echo date('d M Y', strtotime($e['end_date'])); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
