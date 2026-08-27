<?php
$activePage = 'members';
$pageTitle = 'Add Member';
include __DIR__ . '/../includes/header.php';

$error = '';
$plans = $pdo->query('SELECT id, name, duration_days, price FROM plans WHERE status = "active" ORDER BY price ASC')->fetchAll();
$trainers = $pdo->query('SELECT id, name, specialty FROM trainers ORDER BY name ASC')->fetchAll();
$membershipTypes = $pdo->query("SELECT value FROM member_options WHERE category = 'membership_type' ORDER BY value ASC")->fetchAll(PDO::FETCH_COLUMN);
$areasOfInterest = $pdo->query("SELECT value FROM member_options WHERE category = 'area_of_interest' ORDER BY value ASC")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '') ?: null;
    $gender = $_POST['gender'] ?? null;
    $membership_type = trim($_POST['membership_type'] ?? '') ?: null;
    $join_date = trim($_POST['join_date'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $trainer_id = (int)($_POST['trainer_id'] ?? 0);
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    $start_date = trim($_POST['start_date'] ?? '');

    $aoi = $_POST['area_of_interest'] ?? [];
    $area_of_interest = !empty($aoi) ? implode(', ', $aoi) : null;

    if ($name === '' || $phone === '' || $join_date === '') {
        $error = 'Name, phone and join date are required.';
    } elseif ($plan_id > 0 && $start_date === '') {
        $error = 'Please select a start date for the plan.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO members (name, phone, email, date_of_birth, gender, membership_type, area_of_interest, join_date, status, trainer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $phone, $email ?: null, $date_of_birth, $gender, $membership_type, $area_of_interest, $join_date, $status, $trainer_id > 0 ? $trainer_id : null]);
        $memberId = $pdo->lastInsertId();

        if ($plan_id > 0) {
            $stmt2 = $pdo->prepare('SELECT * FROM plans WHERE id = ?');
            $stmt2->execute([$plan_id]);
            $plan = $stmt2->fetch();

            if ($plan) {
                $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $plan['duration_days'] . ' days'));
                $stmt3 = $pdo->prepare('INSERT INTO subscriptions (member_id, plan_id, start_date, end_date, status) VALUES (?, ?, ?, ?, "active")');
                $stmt3->execute([$memberId, $plan_id, $start_date, $end_date]);
            }
        }

        header('Location: /gym/members/slip.php?id=' . $memberId);
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
                    <label class="form-label"><i class="fas fa-birthday-cake me-1 text-muted"></i>Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-venus-mars me-1 text-muted"></i>Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
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
                                <option value="<?php echo htmlspecialchars($mt); ?>"><?php echo htmlspecialchars($mt); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addMembershipTypeModal" title="Add new type"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Join Date *</label>
                    <input type="date" name="join_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-toggle-on me-1 text-muted"></i>Status</label>
                <select name="status" class="form-select">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-muted"><i class="fas fa-star me-1"></i> Area of Interest</h6>
                <hr class="mt-1">
            </div>

            <div class="mb-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <label class="form-label mb-0"><i class="fas fa-heart me-1 text-muted"></i>Select interests</label>
                    <button type="button" class="btn btn-sm btn-outline-success py-0 px-1" data-bs-toggle="modal" data-bs-target="#addAreaOfInterestModal" title="Add new interest"><i class="fas fa-plus"></i></button>
                </div>
                <div class="row" id="areaOfInterestCheckboxes">
                    <?php foreach ($areasOfInterest as $aoi): ?>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="area_of_interest[]" value="<?php echo htmlspecialchars($aoi); ?>" id="aoi_<?php echo md5($aoi); ?>">
                                <label class="form-check-label" for="aoi_<?php echo md5($aoi); ?>"><?php echo htmlspecialchars($aoi); ?></label>
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

<!-- Add Area of Interest Modal -->
<div class="modal fade" id="addAreaOfInterestModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-plus-circle me-1 text-success"></i>Add Area of Interest</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control" id="newAreaOfInterest" placeholder="Enter new interest">
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-success fw-bold" onclick="addAreaOfInterest()"><i class="fas fa-save me-1"></i>Add</button>
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

function addAreaOfInterest() {
    var val = document.getElementById('newAreaOfInterest').value.trim();
    if (!val) return;
    fetch('/gym/members/ajax_add_option.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'category=area_of_interest&value=' + encodeURIComponent(val)
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            var container = document.getElementById('areaOfInterestCheckboxes');
            var id = 'aoi_' + Math.random().toString(36).substr(2, 9);
            var col = document.createElement('div');
            col.className = 'col-md-6';
            col.innerHTML = '<div class="form-check"><input class="form-check-input" type="checkbox" name="area_of_interest[]" value="' + val.replace(/"/g, '&quot;') + '" id="' + id + '" checked><label class="form-check-label" for="' + id + '">' + val.replace(/</g, '&lt;') + '</label></div>';
            container.appendChild(col);
            var modal = bootstrap.Modal.getInstance(document.getElementById('addAreaOfInterestModal'));
            modal.hide();
            document.getElementById('newAreaOfInterest').value = '';
        } else {
            alert(data.error || 'Failed to add.');
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
