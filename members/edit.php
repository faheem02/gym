<?php
$activePage = 'members';
$pageTitle = 'Edit Member';
include __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    echo '<div class="alert alert-warning">Member not found. <a href="index.php">Back to members</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$error = '';
$trainers = $pdo->query('SELECT id, name, specialty, fee FROM trainers ORDER BY name ASC')->fetchAll();
$plans = $pdo->query('SELECT id, name, duration_days, price FROM plans WHERE status = "active" ORDER BY price ASC')->fetchAll();
$fitnessGoals = $pdo->query("SELECT DISTINCT value FROM member_options WHERE category IN ('fitness_goal', 'area_of_interest') ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($fitnessGoals)) {
    $fitnessGoals = ['Weight Loss', 'Muscle Gain', 'Endurance Training', 'Health Recovery', 'Improve Cardiac Health'];
}
$currentAOI = array_map('trim', explode(',', $member['area_of_interest'] ?? ''));

$stmt = $pdo->prepare("SELECT s.*, p.name AS plan_name FROM subscriptions s LEFT JOIN plans p ON p.id = s.plan_id WHERE s.member_id = ? AND s.status = 'active' ORDER BY s.id DESC LIMIT 1");
$stmt->execute([$id]);
$currentSub = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $guardian_name = trim($_POST['guardian_name'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '') ?: null;
    $age = (isset($_POST['age']) && $_POST['age'] !== '') ? (int)$_POST['age'] : null;
    $gender = $_POST['gender'] ?? null;
    $membership_type = trim($_POST['membership_type'] ?? '') ?: null;
    $join_date = trim($_POST['join_date'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $access_type = $_POST['access_type'] ?? 'gym';
    $registration_fee = (float)($_POST['registration_fee'] ?? 0);
    $monthly_fee = (float)($_POST['monthly_fee'] ?? 0);
    $trainer_fee = (float)($_POST['trainer_fee'] ?? 0);
    $kids_fee = (float)($_POST['kids_fee'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $trainer_id = (int)($_POST['trainer_id'] ?? 0);
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    $start_date = trim($_POST['start_date'] ?? '');

    $goals = $_POST['fitness_goals'] ?? $_POST['area_of_interest'] ?? [];
    $area_of_interest = !empty($goals) ? implode(', ', $goals) : null;

    if ($name === '' || $phone === '' || $join_date === '') {
        $error = 'Name, phone and join date are required.';
    } elseif ($plan_id > 0 && $start_date === '') {
        $error = 'Please select a start date for the plan.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE members SET name = ?, guardian_name = ?, phone = ?, date_of_birth = ?, age = ?, gender = ?, membership_type = ?, area_of_interest = ?, join_date = ?, status = ?, access_type = ?, registration_fee = ?, monthly_fee = ?, trainer_fee = ?, kids_fee = ?, discount = ?, trainer_id = ? WHERE id = ?');
            $stmt->execute([$name, $guardian_name, $phone, $date_of_birth, $age, $gender, $membership_type, $area_of_interest, $join_date, $status, $access_type, $registration_fee, $monthly_fee, $trainer_id > 0 ? $trainer_fee : 0, $kids_fee, $discount, $trainer_id > 0 ? $trainer_id : null, $id]);

            if ($plan_id > 0) {
                $stmt2 = $pdo->prepare('SELECT * FROM plans WHERE id = ?');
                $stmt2->execute([$plan_id]);
                $plan = $stmt2->fetch();
                if ($plan) {
                    $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $plan['duration_days'] . ' days'));
                    if ($currentSub) {
                        $stmt3 = $pdo->prepare('UPDATE subscriptions SET plan_id = ?, start_date = ?, end_date = ? WHERE id = ?');
                        $stmt3->execute([$plan_id, $start_date, $end_date, $currentSub['id']]);
                    } else {
                        $stmt3 = $pdo->prepare('INSERT INTO subscriptions (member_id, plan_id, start_date, end_date, status) VALUES (?, ?, ?, ?, "active")');
                        $stmt3->execute([$id, $plan_id, $start_date, $end_date]);
                    }
                }
            }
            $pdo->commit();
            header('Location: /gym/members/index.php?msg=updated');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Could not update member: ' . $e->getMessage();
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
            <div class="section-label mb-3">
                <h6 class="fw-bold text-muted"><i class="fas fa-user me-1"></i> Personal Information</h6>
                <hr class="mt-1">
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user me-1 text-muted"></i>Full Name *</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? $member['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user-shield me-1 text-muted"></i>Parent / Guardian Name</label>
                <input type="text" name="guardian_name" class="form-control" value="<?php echo htmlspecialchars($_POST['guardian_name'] ?? ($member['guardian_name'] ?? '')); ?>" placeholder="Parent/Guardian (for kids play) or alternate contact">
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-phone me-1 text-muted"></i>Phone *</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? $member['phone']); ?>" required>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-birthday-cake me-1 text-muted"></i>Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ($member['date_of_birth'] ?? '')); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-sort-numeric-up me-1 text-muted"></i>Age (Years)</label>
                    <input type="number" name="age" min="0" max="120" class="form-control" placeholder="Enter age" value="<?php echo htmlspecialchars($_POST['age'] ?? ($member['age'] ?? '')); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-venus-mars me-1 text-muted"></i>Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="male" <?php echo ($_POST['gender'] ?? $member['gender']) === 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($_POST['gender'] ?? $member['gender']) === 'female' ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo ($_POST['gender'] ?? $member['gender']) === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-id-card me-1 text-muted"></i>Membership Type</label>
                    <div class="input-group">
                        <select name="membership_type" class="form-select" id="membershipTypeSelect">
                            <option value="">-- Select --</option>
                            <?php foreach ($membershipTypes as $mt): ?>
                                <option value="<?php echo htmlspecialchars($mt); ?>" <?php echo ($_POST['membership_type'] ?? $member['membership_type']) === $mt ? 'selected' : ''; ?>><?php echo htmlspecialchars($mt); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addMembershipTypeModal" title="Add new type"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Join Date *</label>
                    <input type="date" name="join_date" class="form-control" value="<?php echo htmlspecialchars($_POST['join_date'] ?? $member['join_date']); ?>" required>
                </div>
            </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-layer-group me-1 text-muted"></i>Access Type *</label>
                    <select name="access_type" class="form-select">
                        <option value="gym" <?php echo ($_POST['access_type'] ?? $member['access_type']) === 'gym' ? 'selected' : ''; ?>>Gym Access</option>
                        <option value="kids_play" <?php echo ($_POST['access_type'] ?? $member['access_type']) === 'kids_play' ? 'selected' : ''; ?>>Kids Play Area</option>
                        <option value="both" <?php echo ($_POST['access_type'] ?? $member['access_type']) === 'both' ? 'selected' : ''; ?>>Gym + Kids Play</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-toggle-on me-1 text-muted"></i>Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo ($_POST['status'] ?? $member['status']) === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($_POST['status'] ?? $member['status']) === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-muted"><i class="fas fa-file-invoice-dollar me-1"></i> Fee &amp; Concession Details</h6>
                <hr class="mt-1">
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-file-invoice me-1 text-muted"></i>Registration Fee (Rs.)</label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <?php $rf = (float)($_POST['registration_fee'] ?? ($member['registration_fee'] ?? 0)); ?>
                        <input type="number" step="0.01" min="0" name="registration_fee" class="form-control" placeholder="0" value="<?php echo $rf > 0 ? $rf : ''; ?>">
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold"><i class="fas fa-calendar-check me-1 text-primary"></i>Gym Monthly Fee (Rs.)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-primary text-white">Rs.</span>
                        <?php $mf = (float)($_POST['monthly_fee'] ?? ($member['monthly_fee'] ?? 0)); ?>
                        <input type="number" step="0.01" min="0" name="monthly_fee" class="form-control fw-bold" placeholder="0" value="<?php echo $mf > 0 ? $mf : ''; ?>">
                    </div>
                    <small class="text-muted">Monthly gym membership fee</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-child me-1 text-muted"></i>Kids Area Fee (Rs.)</label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <?php $kf = (float)($_POST['kids_fee'] ?? ($member['kids_fee'] ?? 0)); ?>
                        <input type="number" step="0.01" min="0" name="kids_fee" class="form-control" placeholder="0" value="<?php echo $kf > 0 ? $kf : ''; ?>">
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-hand-holding-usd me-1 text-muted"></i>Trainer Fee (Rs.)</label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <?php $tf = (float)($_POST['trainer_fee'] ?? ($member['trainer_fee'] ?? 0)); ?>
                        <input type="number" step="0.01" min="0" name="trainer_fee" class="form-control" placeholder="0" value="<?php echo $tf > 0 ? $tf : ''; ?>">
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-tag me-1 text-muted"></i>Discount (Rs.)</label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <?php $df = (float)($_POST['discount'] ?? ($member['discount'] ?? 0)); ?>
                        <input type="number" step="0.01" min="0" name="discount" class="form-control" placeholder="0" value="<?php echo $df > 0 ? $df : ''; ?>">
                    </div>
                </div>
            </div>

            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-dark"><i class="fas fa-bullseye me-1 text-danger"></i> Fitness Goals</h6>
                <hr class="mt-1">
            </div>

            <div class="mb-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <label class="form-label mb-0 text-muted"><i class="fas fa-check-square me-1 text-muted"></i>Select member's fitness goals</label>
                    <button type="button" class="btn btn-sm btn-outline-success py-0 px-1" data-bs-toggle="modal" data-bs-target="#addFitnessGoalModal" title="Add new goal"><i class="fas fa-plus"></i></button>
                </div>
                <div class="row" id="fitnessGoalCheckboxes">
                    <?php 
                    foreach ($fitnessGoals as $fg): 
                        $goalItemId = 'goal_item_' . md5($fg);
                    ?>
                        <div class="col-md-6 mb-2" id="<?php echo $goalItemId; ?>">
                            <div class="form-check d-flex justify-content-between align-items-center bg-light px-3 py-1 rounded border">
                                <div>
                                    <?php $checked = in_array($fg, $currentAOI) ? 'checked' : ''; ?>
                                    <input class="form-check-input" type="checkbox" name="fitness_goals[]" value="<?php echo htmlspecialchars($fg); ?>" id="fg_<?php echo md5($fg); ?>" <?php echo $checked; ?>>
                                    <label class="form-check-label fw-semibold ms-1" for="fg_<?php echo md5($fg); ?>"><?php echo htmlspecialchars($fg); ?></label>
                                </div>
                                <button type="button" class="btn btn-link text-danger p-0 ms-2 text-decoration-none" onclick="deleteFitnessGoal('<?php echo htmlspecialchars(addslashes($fg)); ?>', '<?php echo $goalItemId; ?>')" title="Delete this goal"><i class="fas fa-trash-alt fa-sm"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-muted"><i class="fas fa-dumbbell me-1"></i> Assign Trainer <small class="fw-normal">(Optional)</small></h6>
                <hr class="mt-1">
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user-tie me-1 text-muted"></i>Select Trainer</label>
                <select name="trainer_id" class="form-select">
                    <option value="0">-- No Trainer --</option>
                    <?php foreach ($trainers as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo ($_POST['trainer_id'] ?? $member['trainer_id']) == $t['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['specialty'] ?? 'General'); ?>) - Fee: Rs. <?php echo number_format((float)($t['fee'] ?? 0), 0); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-muted"><i class="fas fa-utensils me-1"></i> Assign Diet Plan
                    <?php if ($currentSub): ?>
                        <small class="fw-normal">(Current: <?php echo htmlspecialchars($currentSub['plan_name'] ?? 'Plan'); ?>, <?php echo date('d M Y', strtotime($currentSub['start_date'])); ?> - <?php echo date('d M Y', strtotime($currentSub['end_date'])); ?>)</small>
                    <?php else: ?>
                        <small class="fw-normal">(No active subscription)</small>
                    <?php endif; ?>
                </h6>
                <hr class="mt-1">
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-tag me-1 text-muted"></i>Select Diet Plan</label>
                <select name="plan_id" class="form-select" id="planSelect" onchange="updatePlanInfo()">
                    <option value="0">-- Keep Unchanged / No Plan --</option>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?php echo $p['id']; ?>" data-duration="<?php echo $p['duration_days']; ?>" data-price="<?php echo $p['price']; ?>" <?php echo ($_POST['plan_id'] ?? ($currentSub['plan_id'] ?? 0)) == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name']); ?> - <?php echo $p['duration_days']; ?> days (Rs.<?php echo number_format($p['price'], 0); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="planFields" style="display: none;">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Plan Start Date *</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($_POST['start_date'] ?? ($currentSub['start_date'] ?? date('Y-m-d'))); ?>">
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

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Update Member</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- Add Membership Type Modal -->
<div class="modal fade" id="addMembershipTypeModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-plus-circle me-1 text-success"></i>Add Membership Type</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control" id="newMembershipType" placeholder="Enter new type name">
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-success fw-bold" onclick="addMembershipType()"><i class="fas fa-save me-1"></i>Add</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Fitness Goal Modal -->
<div class="modal fade" id="addFitnessGoalModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-plus-circle me-1 text-success"></i>Add Fitness Goal</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control" id="newFitnessGoal" placeholder="e.g. Flexibility, Stamina">
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-success fw-bold" onclick="addFitnessGoal()"><i class="fas fa-save me-1"></i>Add</button>
            </div>
        </div>
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

function addMembershipType() {
    var val = document.getElementById('newMembershipType').value.trim();
    if (!val) return;
    fetch('/gym/members/ajax_add_option.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'category=membership_type&value=' + encodeURIComponent(val)
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            var sel = document.getElementById('membershipTypeSelect');
            var opt = document.createElement('option');
            opt.value = val;
            opt.text = val;
            opt.selected = true;
            sel.appendChild(opt);
            var modal = bootstrap.Modal.getInstance(document.getElementById('addMembershipTypeModal'));
            modal.hide();
            document.getElementById('newMembershipType').value = '';
        } else {
            alert(data.error || 'Failed to add.');
        }
    });
}

function addFitnessGoal() {
    var val = document.getElementById('newFitnessGoal').value.trim();
    if (!val) return;
    fetch('/gym/members/ajax_add_option.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'category=fitness_goal&value=' + encodeURIComponent(val)
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            var container = document.getElementById('fitnessGoalCheckboxes');
            var id = 'fg_' + Math.random().toString(36).substr(2, 9);
            var itemId = 'goal_item_' + Math.random().toString(36).substr(2, 9);
            var col = document.createElement('div');
            col.className = 'col-md-6 mb-2';
            col.id = itemId;
            col.innerHTML = '<div class="form-check d-flex justify-content-between align-items-center bg-light px-3 py-1 rounded border"><div><input class="form-check-input" type="checkbox" name="fitness_goals[]" value="' + val.replace(/"/g, '&quot;') + '" id="' + id + '" checked><label class="form-check-label fw-semibold ms-1" for="' + id + '">' + val.replace(/</g, '&lt;') + '</label></div><button type="button" class="btn btn-link text-danger p-0 ms-2 text-decoration-none" onclick="deleteFitnessGoal(\'' + val.replace(/'/g, "\\'") + '\', \'' + itemId + '\')" title="Delete this goal"><i class="fas fa-trash-alt fa-sm"></i></button></div>';
            container.appendChild(col);
            var modal = bootstrap.Modal.getInstance(document.getElementById('addFitnessGoalModal'));
            modal.hide();
            document.getElementById('newFitnessGoal').value = '';
        } else {
            alert(data.error || 'Failed to add.');
        }
    });
}

function deleteFitnessGoal(val, elementId) {
    if (!confirm('Are you sure you want to delete fitness goal "' + val + '"?')) return;
    fetch('/gym/members/ajax_delete_option.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'category=fitness_goal&value=' + encodeURIComponent(val)
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            var el = document.getElementById(elementId);
            if (el) {
                el.style.transition = 'opacity 0.25s, transform 0.25s';
                el.style.opacity = '0';
                el.style.transform = 'scale(0.95)';
                setTimeout(function() { el.remove(); }, 250);
            }
        } else {
            alert(data.error || 'Failed to delete goal.');
        }
    }).catch(function(err) {
        alert('An error occurred while deleting.');
    });
}

updatePlanInfo();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
