<?php
$activePage = 'plans';
$pageTitle = 'Membership Plans';
include __DIR__ . '/../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Plan added successfully.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Plan updated successfully.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Plan deleted.</div>';

$plans = $pdo->query('SELECT * FROM plans ORDER BY is_popular DESC, price ASC')->fetchAll();

function planIconClass($name) {
    $n = strtolower($name);
    if (strpos($n, 'month') !== false && strpos($n, 'year') === false) return 'monthly';
    if (strpos($n, 'quarter') !== false || strpos($n, '3') !== false) return 'quarterly';
    if (strpos($n, 'year') !== false) return 'yearly';
    return 'default';
}

function planIcon($name) {
    $n = strtolower($name);
    if (strpos($n, 'month') !== false && strpos($n, 'year') === false) return 'fa-calendar-alt';
    if (strpos($n, 'quarter') !== false) return 'fa-fire';
    if (strpos($n, 'year') !== false) return 'fa-crown';
    return 'fa-star';
}

function pricePerMonth($price, $days) {
    if ($days <= 0) return $price;
    $months = $days / 30;
    if ($months < 1) $months = 1;
    return $price / $months;
}
?>

<!-- Pricing Cards Section -->
<div class="pricing-section">
    <div class="section-heading">
        <i class="fas fa-tags"></i> Active Membership Plans
    </div>
    <div class="pricing-cards">
        <?php if (empty($plans)): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-clipboard-list" style="font-size:3rem;opacity:0.2;"></i>
                <p class="mt-2">No plans created yet.</p>
            </div>
        <?php endif; ?>
        <?php foreach ($plans as $p): ?>
            <?php
                $features = array_filter(array_map('trim', explode("\n", $p['features'] ?? '')));
                $perMonth = pricePerMonth($p['price'], $p['duration_days']);
                $iconClass = planIconClass($p['name']);
                $icon = planIcon($p['name']);
            ?>
            <div class="pricing-card <?php echo $p['is_popular'] ? 'popular' : ''; ?>">
                <?php if ($p['is_popular']): ?>
                    <div class="popular-badge">POPULAR</div>
                <?php endif; ?>

                <div class="card-top">
                    <div class="plan-icon <?php echo $iconClass; ?>">
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="plan-name"><?php echo htmlspecialchars($p['name']); ?></div>
                    <div class="plan-duration"><?php echo $p['duration_days']; ?> Days</div>
                </div>

                <div class="plan-price">
                    <span class="currency">Rs.</span><span class="amount"><?php echo number_format($p['price'], 0); ?></span>
                    <span class="period">for <?php echo $p['duration_days']; ?> days &middot; Rs.<?php echo number_format($perMonth, 0); ?>/mo</span>
                </div>

                <div class="plan-features">
                    <ul>
                        <?php if (!empty($features)): ?>
                            <?php foreach ($features as $f): ?>
                                <li><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($f); ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><i class="fas fa-check-circle"></i>Gym Access</li>
                        <?php endif; ?>
                        <?php if (($p['day_pass_discount'] ?? 0) > 0): ?>
                            <li style="border-top:2px dashed #e9ecef; margin-top:4px; padding-top:10px;">
                                <i class="fas fa-ticket-alt text-warning"></i>
                                <span><strong class="text-warning"><?php echo $p['day_pass_discount']; ?>% OFF</strong> on Day Passes</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="plan-footer">
                    <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-dark">
                        <i class="fas fa-pen me-1"></i>Edit Plan
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Management Table Section -->
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0"><i class="fas fa-cog text-muted me-2"></i>Plan Management</h6>
            <a href="add.php" class="btn btn-warning fw-bold btn-sm"><i class="fas fa-plus me-1"></i>Add New Plan</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Plan Name</th>
                        <th>Duration</th>
                        <th>Price</th>
                        <th>Per Month</th>
                        <th>Day Pass Discount</th>
                        <th>Popular</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plans)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-clipboard me-1"></i>No plans found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($plans as $p): ?>
                        <?php $perMonth = pricePerMonth($p['price'], $p['duration_days']); ?>
                        <tr>
                            <td><?php echo $p['id']; ?></td>
                            <td class="fw-semibold">
                                <?php echo htmlspecialchars($p['name']); ?>
                                <?php if ($p['is_popular']): ?>
                                    <span class="badge text-bg-warning ms-1" style="font-size:0.65rem;">POPULAR</span>
                                <?php endif; ?>
                            </td>
                            <td><i class="fas fa-clock text-muted me-1"></i><?php echo $p['duration_days']; ?> days</td>
                            <td class="fw-bold text-success">Rs.<?php echo number_format($p['price'], 2); ?></td>
                            <td class="text-muted">Rs.<?php echo number_format($perMonth, 0); ?>/mo</td>
                            <td>
                                <?php $dp = $p['day_pass_discount'] ?? 0; ?>
                                <?php if ($dp > 0): ?>
                                    <span class="badge text-bg-warning"><i class="fas fa-percent me-1"></i><?php echo $dp; ?>% OFF</span>
                                <?php else: ?>
                                    <span class="text-muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['is_popular']): ?>
                                    <span class="badge badge-active"><i class="fas fa-star me-1"></i>Yes</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($p['status'] ?? 'active') === 'active'): ?>
                                    <span class="badge badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                                <a href="delete.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this plan?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
