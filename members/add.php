<?php
$activePage = 'members';
$pageTitle = 'Add Member';
include __DIR__ . '/../includes/header.php';

$error = '';
$plans = $pdo->query('SELECT id, name, duration_days, price FROM plans WHERE status = "active" ORDER BY price ASC')->fetchAll();
$trainers = $pdo->query('SELECT id, name, specialty FROM trainers ORDER BY name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $join_date = trim($_POST['join_date'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $trainer_id = (int)($_POST['trainer_id'] ?? 0);
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    $start_date = trim($_POST['start_date'] ?? '');

    if ($name === '' || $phone === '' || $join_date === '') {
        $error = 'Name, phone and join date are required.';
    } elseif ($plan_id > 0 && $start_date === '') {
        $error = 'Please select a start date for the plan.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO members (name, phone, email, join_date, status, trainer_id) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $phone, $email ?: null, $join_date, $status, $trainer_id > 0 ? $trainer_id : null]);
        $memberId = $pdo->lastInsertId();

        if ($plan_id > 0) {
            $plan = null;
            $stmt2 = $pdo->prepare('SELECT * FROM plans WHERE id = ?');
            $stmt2->execute([$plan_id]);
            $plan = $stmt2->fetch();

            if ($plan) {
                $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $plan['duration_days'] . ' days'));
                $stmt3 = $pdo->prepare('INSERT INTO subscriptions (member_id, plan_id, start_date, end_date, status) VALUES (?, ?, ?, ?, "active")');
                $stmt3->execute([$memberId, $plan_id, $start_date, $end_date]);
            }
        }

        header('Location: /gym/members/index.php?msg=added');
        exit;
    }
}
?>

<div class="card form-card" style="max-width: 720px;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-user-plus text-warning me-2"></i>Add New Member</h5>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="section-label mb-3">
                <h6 class="fw-bold text-muted"><i class="fas fa-user me-1"></i> Personal Information</h6>
                <hr class="mt-1">
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user me-1 text-muted"></i>Full Name *</label>
                <input type="text" name="name" class="form-control" placeholder="Enter full name" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-phone me-1 text-muted"></i>Phone *</label>
                <input type="text" name="phone" class="form-control" placeholder="03XX-XXXXXXX" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-envelope me-1 text-muted"></i>Email</label>
                <input type="email" name="email" class="form-control" placeholder="Enter email address">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Join Date *</label>
                    <input type="date" name="join_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-toggle-on me-1 text-muted"></i>Status</label>
                    <select name="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-muted"><i class="fas fa-dumbbell me-1"></i> Assign Trainer <small class="fw-normal">(Optional)</small></h6>
                <hr class="mt-1">
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user-tie me-1 text-muted"></i>Select Trainer</label>
                <select name="trainer_id" class="form-select">
                    <option value="0">-- No Trainer (Skip) --</option>
                    <?php foreach ($trainers as $t): ?>
                        <option value="<?php echo $t['id']; ?>">
                            <?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['specialty'] ?? 'General'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-muted"><i class="fas fa-clipboard-list me-1"></i> Assign Plan <small class="fw-normal">(Optional)</small></h6>
                <hr class="mt-1">
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-tag me-1 text-muted"></i>Select Plan</label>
                <select name="plan_id" class="form-select" id="planSelect" onchange="updatePlanInfo()">
                    <option value="0">-- No Plan (Skip) --</option>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?php echo $p['id']; ?>" data-duration="<?php echo $p['duration_days']; ?>" data-price="<?php echo $p['price']; ?>">
                            <?php echo htmlspecialchars($p['name']); ?> - <?php echo $p['duration_days']; ?> days (Rs.<?php echo number_format($p['price'], 0); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="planFields" style="display: none;">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Plan Start Date *</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-calendar-check me-1 text-muted"></i>Plan End Date</label>
                        <input type="text" class="form-control" id="endDate" readonly placeholder="Auto-calculated">
                    </div>
                </div>
                <div class="alert alert-info py-2 mb-3" id="planSummary" style="display: none;">
                    <i class="fas fa-info-circle me-1"></i>
                    <span id="planSummaryText"></span>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Save Member</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function updatePlanInfo() {
    var select = document.getElementById('planSelect');
    var planFields = document.getElementById('planFields');
    var endDate = document.getElementById('endDate');
    var summary = document.getElementById('planSummary');
    var summaryText = document.getElementById('planSummaryText');
    var startDate = document.querySelector('input[name="start_date"]');

    if (select.value > 0) {
        var opt = select.options[select.selectedIndex];
        var duration = parseInt(opt.getAttribute('data-duration'));
        var price = parseFloat(opt.getAttribute('data-price'));
        var name = opt.text.split(' - ')[0];

        planFields.style.display = 'block';
        summary.style.display = 'block';
        summaryText.innerHTML = '<strong>' + name + '</strong> &mdash; ' + duration + ' days for <strong>Rs.' + price.toLocaleString() + '</strong>';

        calcEndDate(startDate.value, duration);
        startDate.onchange = function() { calcEndDate(this.value, duration); };
    } else {
        planFields.style.display = 'none';
        summary.style.display = 'none';
    }
}

function calcEndDate(start, duration) {
    if (!start) return;
    var d = new Date(start);
    d.setDate(d.getDate() + duration);
    var dd = String(d.getDate()).padStart(2, '0');
    var mm = String(d.getMonth() + 1).padStart(2, '0');
    var yyyy = d.getFullYear();
    document.getElementById('endDate').value = dd + '/' + mm + '/' + yyyy;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
