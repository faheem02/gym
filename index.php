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

<style>
.quick-action-card {
    border-radius: 14px;
    border: none;
    transition: all 0.22s ease-in-out;
    position: relative;
    overflow: hidden;
    color: #fff !important;
    text-decoration: none;
    display: block;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}
.quick-action-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
    color: #fff !important;
}
.quick-action-card:active {
    transform: scale(0.98);
}
.quick-action-card .card-body {
    padding: 1.15rem 1.2rem;
    position: relative;
    z-index: 2;
}
.quick-action-card .action-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}
.quick-action-card .action-bg-icon {
    position: absolute;
    right: -8px;
    bottom: -15px;
    font-size: 4.8rem;
    opacity: 0.14;
    z-index: 1;
    pointer-events: none;
    transform: rotate(-10deg);
}
.qa-pos { background: linear-gradient(135deg, #f59e0b, #d97706); }
.qa-member { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.qa-daypass { background: linear-gradient(135deg, #10b981, #059669); }
.qa-stock { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
</style>

<!-- Quick Action Shortcuts -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="/gym/canteen/pos/" class="quick-action-card qa-pos">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="action-icon"><i class="fas fa-cash-register"></i></div>
                <div class="overflow-hidden">
                    <h6 class="mb-0 fw-bold text-white text-truncate">POS Billing</h6>
                    <small class="text-white-50 text-truncate d-block">Canteen Sale &amp; Print</small>
                </div>
            </div>
            <i class="fas fa-shopping-cart action-bg-icon"></i>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="/gym/members/add.php" class="quick-action-card qa-member">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="action-icon"><i class="fas fa-user-plus"></i></div>
                <div class="overflow-hidden">
                    <h6 class="mb-0 fw-bold text-white text-truncate">Add Member</h6>
                    <small class="text-white-50 text-truncate d-block">Register Gym Member</small>
                </div>
            </div>
            <i class="fas fa-users action-bg-icon"></i>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="/gym/day_passes/add.php" class="quick-action-card qa-daypass">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="action-icon"><i class="fas fa-ticket-alt"></i></div>
                <div class="overflow-hidden">
                    <h6 class="mb-0 fw-bold text-white text-truncate">Add Day Pass</h6>
                    <small class="text-white-50 text-truncate d-block">Daily Entry &amp; Slip</small>
                </div>
            </div>
            <i class="fas fa-receipt action-bg-icon"></i>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="/gym/canteen/stock/" class="quick-action-card qa-stock">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="action-icon"><i class="fas fa-cubes"></i></div>
                <div class="overflow-hidden">
                    <h6 class="mb-0 fw-bold text-white text-truncate">Inventory Stock</h6>
                    <small class="text-white-50 text-truncate d-block">Stock Levels &amp; Items</small>
                </div>
            </div>
            <i class="fas fa-boxes action-bg-icon"></i>
        </a>
    </div>
</div>

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
