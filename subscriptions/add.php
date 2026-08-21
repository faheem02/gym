<?php
$activePage = 'subscriptions';
$pageTitle = 'Assign Plan';
include __DIR__ . '/../includes/header.php';

$error = '';
$members = $pdo->query('SELECT id, name, phone FROM members ORDER BY name')->fetchAll();
$plans = $pdo->query('SELECT id, name, duration_days, price FROM plans ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = (int)($_POST['member_id'] ?? 0);
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    $start_date = trim($_POST['start_date'] ?? '');
    $plan = null;
    $member = null;

    if ($member_id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();
    }
    if ($plan_id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM plans WHERE id = ?');
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch();
    }

    if (!$member || !$plan || $start_date === '') {
        $error = 'Select a member, a plan and a start date.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE member_id = ? AND status = 'active'");
        $stmt->execute([$member_id]);
        if ($stmt->fetch()) {
            $error = 'This member already has an active subscription. Renew it instead.';
        } else {
            $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $plan['duration_days'] . ' days'));
            $stmt = $pdo->prepare('INSERT INTO subscriptions (member_id, plan_id, start_date, end_date, status) VALUES (?, ?, ?, ?, "active")');
            $stmt->execute([$member_id, $plan_id, $start_date, $end_date]);
            header('Location: /gym/subscriptions/index.php?msg=added');
            exit;
        }
    }
}
?>

<div class="card form-card">
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user me-1 text-muted"></i>Member *</label>
                <select name="member_id" class="form-select" required>
                    <option value="">-- Select Member --</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']) . ' (' . htmlspecialchars($m['phone']) . ')'; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-clipboard-list me-1 text-muted"></i>Plan *</label>
                <select name="plan_id" class="form-select" required>
                    <option value="">-- Select Plan --</option>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']) . ' - ' . $p['duration_days'] . ' days (Rs.' . number_format($p['price'], 2) . ')'; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Start Date *</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Assign Plan</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
