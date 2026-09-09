<?php
$activePage = 'members';
$pageTitle = 'Add Member';
include __DIR__ . '/../includes/header.php';

$error = '';
$plans = $pdo->query('SELECT id, name, duration_days, price FROM plans WHERE status = "active" ORDER BY price ASC')->fetchAll();
$trainers = $pdo->query('SELECT id, name, specialty, fee FROM trainers ORDER BY name ASC')->fetchAll();
$membershipTypes = $pdo->query("SELECT value FROM member_options WHERE category = 'membership_type' ORDER BY value ASC")->fetchAll(PDO::FETCH_COLUMN);
$fitnessGoals = $pdo->query("SELECT DISTINCT value FROM member_options WHERE category IN ('fitness_goal', 'area_of_interest') ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($fitnessGoals)) {
    $fitnessGoals = ['Weight Loss', 'Muscle Gain', 'Endurance Training', 'Health Recovery', 'Improve Cardiac Health'];
}

$accessTypes = [
    'gym' => ['label' => 'Gym Access', 'icon' => 'fa-dumbbell', 'color' => 'primary', 'desc' => 'Access to gym equipment & workout area'],
    'kids_play' => ['label' => 'Kids Play Area', 'icon' => 'fa-child', 'color' => 'success', 'desc' => 'Access to kids playing & recreation area'],
    'both' => ['label' => 'Gym + Kids Play', 'icon' => 'fa-users', 'color' => 'warning', 'desc' => 'Combo: gym workout + kids play area'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $access_type = $_POST['access_type'] ?? 'gym';
    if (!array_key_exists($access_type, $accessTypes)) {
        $access_type = 'gym';
    }

    $name = trim($_POST['name'] ?? '');
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '') ?: null;
    $age = (isset($_POST['age']) && $_POST['age'] !== '') ? (int)$_POST['age'] : null;
    $gender = $_POST['gender'] ?? null;
    $membership_type = trim($_POST['membership_type'] ?? '') ?: null;
    $join_date = trim($_POST['join_date'] ?? date('Y-m-d'));
    $status = $_POST['status'] ?? 'active';

    $trainer_id = (int)($_POST['trainer_id'] ?? 0);
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    $start_date = trim($_POST['start_date'] ?? '');

    $registration_fee = (float)($_POST['registration_fee'] ?? 0);
    $monthly_fee = (float)($_POST['monthly_fee'] ?? 0);
    $kids_fee = (float)($_POST['kids_fee'] ?? 0);
    $trainer_fee = (float)($_POST['trainer_fee'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $amount_received = (float)($_POST['amount_received'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $payment_notes = trim($_POST['payment_notes'] ?? '');

    $goals = $_POST['fitness_goals'] ?? $_POST['area_of_interest'] ?? [];
    $area_of_interest = !empty($goals) ? implode(', ', $goals) : null;

    // Reset irrelevant fields based on access type
    if ($access_type === 'kids_play') {
        $trainer_id = 0;
        $trainer_fee = 0.00;
        $plan_id = 0;
        $monthly_fee = 0.00;
        $area_of_interest = null;
    } elseif ($access_type === 'gym') {
        $kids_fee = 0.00;
        $guardian_name = '';
    }

    $validMethods = ['cash', 'bank_transfer'];
    if (!in_array($payment_method, $validMethods)) {
        $payment_method = 'cash';
    }

    if ($name === '' || $phone === '' || $join_date === '') {
        $error = 'Name, phone and join date are required.';
    } elseif ($access_type === 'kids_play' && $guardian_name === '') {
        $error = 'Parent / Guardian name is required for Kids Play Area.';
    } elseif ($access_type !== 'kids_play' && $plan_id > 0 && $start_date === '') {
        $error = 'Please select a start date for the gym plan.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO members (name, guardian_name, phone, date_of_birth, age, gender, membership_type, area_of_interest, join_date, status, access_type, registration_fee, monthly_fee, trainer_fee, kids_fee, discount, trainer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $name,
                $guardian_name ?: null,
                $phone,
                $date_of_birth,
                $age,
                $gender,
                $membership_type,
                $area_of_interest,
                $join_date,
                $status,
                $access_type,
                $registration_fee,
                $monthly_fee,
                $trainer_id > 0 ? $trainer_fee : 0.00,
                $kids_fee,
                $discount,
                $trainer_id > 0 ? $trainer_id : null,
            ]);
            $memberId = (int)$pdo->lastInsertId();

            if ($plan_id > 0 && $access_type !== 'kids_play') {
                $stmt2 = $pdo->prepare('SELECT * FROM plans WHERE id = ?');
                $stmt2->execute([$plan_id]);
                $plan = $stmt2->fetch();

                if ($plan) {
                    $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $plan['duration_days'] . ' days'));
                    $stmt3 = $pdo->prepare('INSERT INTO subscriptions (member_id, plan_id, start_date, end_date, status) VALUES (?, ?, ?, ?, "active")');
                    $stmt3->execute([$memberId, $plan_id, $start_date, $end_date]);
                }
            }

            // Record initial payment if received
            if ($amount_received > 0) {
                $subtotal = $registration_fee + $monthly_fee + $kids_fee + ($trainer_id > 0 ? $trainer_fee : 0);
                $netTotal = max(0, $subtotal - $discount);
                $remaining = max(0, $netTotal - $amount_received);

                $noteBreakdown = "[" . $accessTypes[$access_type]['label'] . "] Reg Fee: Rs. " . number_format($registration_fee, 2);
                if ($monthly_fee > 0) $noteBreakdown .= " | Gym Plan: Rs. " . number_format($monthly_fee, 2);
                if ($kids_fee > 0) $noteBreakdown .= " | Kids Fee: Rs. " . number_format($kids_fee, 2);
                if ($trainer_id > 0 && $trainer_fee > 0) $noteBreakdown .= " | Trainer: Rs. " . number_format($trainer_fee, 2);
                if ($discount > 0) $noteBreakdown .= " | Discount: Rs. " . number_format($discount, 2);
                if ($remaining > 0) $noteBreakdown .= " | Remaining: Rs. " . number_format($remaining, 2);
                if ($payment_notes !== '') $noteBreakdown .= " (" . $payment_notes . ")";

                $paymentFor = $access_type === 'kids_play' ? 'Kids Play Area Membership' : 'Admission & Membership Fee';

                $stmtPay = $pdo->prepare('INSERT INTO member_payments (member_id, amount, payment_method, payment_for, notes, payment_date) VALUES (?, ?, ?, ?, ?, ?)');
                $stmtPay->execute([
                    $memberId,
                    $amount_received,
                    $payment_method,
                    $paymentFor,
                    $noteBreakdown,
                    $join_date,
                ]);
            }

            $pdo->commit();

            header('Location: /gym/members/slip.php?id=' . $memberId . '&autoprint=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error saving member: ' . $e->getMessage();
        }
    }
}
?>

<style>
.access-type-card {
    transition: all 0.2s ease-in-out;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    cursor: pointer;
}
.access-type-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.access-type-card.selected.card-gym {
    border-color: #3b82f6 !important;
    background-color: #eff6ff;
    box-shadow: 0 0 0 1px #3b82f6, 0 4px 14px rgba(59, 130, 246, 0.2);
}
.access-type-card.selected.card-kids_play {
    border-color: #10b981 !important;
    background-color: #f0fdf4;
    box-shadow: 0 0 0 1px #10b981, 0 4px 14px rgba(16, 185, 129, 0.2);
}
.access-type-card.selected.card-both {
    border-color: #f59e0b !important;
    background-color: #fffbeb;
    box-shadow: 0 0 0 1px #f59e0b, 0 4px 14px rgba(245, 158, 11, 0.2);
}
.summary-box {
    background: linear-gradient(145deg, #1f2937, #111827);
    color: #fff;
    border-radius: 12px;
    padding: 20px;
}
.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px dashed rgba(255,255,255,0.12);
    font-size: 0.92rem;
}
.summary-item:last-child {
    border-bottom: none;
}
.summary-item.total-row {
    font-size: 1.15rem;
    font-weight: 700;
    color: #f7b731;
    border-top: 2px solid rgba(255,255,255,0.25);
    border-bottom: none;
    padding-top: 10px;
    margin-top: 6px;
}
.summary-item.remaining-row {
    font-size: 1.1rem;
    font-weight: 700;
    color: #f87171;
    padding-top: 6px;
}
.summary-item.remaining-row.paid {
    color: #34d399;
}
</style>

<div class="card form-card shadow-sm" style="max-width: 840px;">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-1"><i class="fas fa-user-plus text-warning me-2"></i>Add New Member</h5>
                <small class="text-muted">Select an access type below &mdash; fields will adapt automatically</small>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-3"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="memberForm">
            <!-- 1. Top Access Type Selection (Gym Access / Kids Play Area / Gym + Kids) -->
            <div class="section-label mb-3">
                <h6 class="fw-bold text-dark"><i class="fas fa-layer-group text-primary me-2"></i>Choose Membership Access Type *</h6>
                <hr class="mt-1 mb-3">
            </div>

            <div class="row g-3 mb-4">
                <?php foreach ($accessTypes as $key => $at): ?>
                    <div class="col-md-4">
                        <div class="card h-100 access-type-card card-<?php echo $key; ?>" id="access-card-<?php echo $key; ?>" onclick="selectAccessType('<?php echo $key; ?>')">
                            <div class="card-body text-center p-3">
                                <div class="mb-2">
                                    <i class="fas <?php echo $at['icon']; ?> fa-2x text-<?php echo $at['color']; ?>"></i>
                                </div>
                                <h6 class="fw-bold mb-1"><?php echo $at['label']; ?></h6>
                                <small class="text-muted d-block" style="font-size: 0.8rem; min-height: 36px;"><?php echo $at['desc']; ?></small>
                                <span class="badge text-bg-<?php echo $at['color']; ?> px-2 py-1 mt-2">
                                    <i class="fas fa-check-circle me-1"></i><?php echo $at['label']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="access_type" id="accessTypeInput" value="gym">

            <!-- 2. Personal Information Section (Dynamic labels & fields) -->
            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-muted" id="personalInfoHeading"><i class="fas fa-user me-1"></i> Personal Information</h6>
                <hr class="mt-1">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" id="nameLabel"><i class="fas fa-user me-1 text-muted"></i>Full Name *</label>
                    <input type="text" name="name" id="nameInput" class="form-control" placeholder="Enter full name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                </div>
                
                <!-- Guardian Name: shown for kids_play and both -->
                <div class="col-md-6 mb-3" id="guardianNameCol" style="display: none;">
                    <label class="form-label" id="guardianLabel"><i class="fas fa-user-shield me-1 text-muted"></i>Parent / Guardian Name *</label>
                    <input type="text" name="guardian_name" id="guardianInput" class="form-control" placeholder="Enter parent/guardian name" value="<?php echo htmlspecialchars($_POST['guardian_name'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3" id="phoneCol">
                    <label class="form-label" id="phoneLabel"><i class="fas fa-phone me-1 text-muted"></i>Phone Number *</label>
                    <input type="text" name="phone" class="form-control" placeholder="03XX-XXXXXXX" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" id="dobLabel"><i class="fas fa-birthday-cake me-1 text-muted"></i>Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-sort-numeric-up me-1 text-muted"></i>Age (Years)</label>
                    <input type="number" name="age" min="0" max="120" class="form-control" placeholder="Enter age" value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-venus-mars me-1 text-muted"></i>Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="male" <?php echo ($_POST['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($_POST['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo ($_POST['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-id-card me-1 text-muted"></i>Membership Type</label>
                    <div class="input-group">
                        <select name="membership_type" class="form-select" id="membershipTypeSelect">
                            <option value="">-- Select --</option>
                            <?php foreach ($membershipTypes as $mt): ?>
                                <option value="<?php echo htmlspecialchars($mt); ?>" <?php echo ($_POST['membership_type'] ?? '') === $mt ? 'selected' : ''; ?>><?php echo htmlspecialchars($mt); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addMembershipTypeModal" title="Add new type"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Join Date *</label>
                    <input type="date" name="join_date" class="form-control" value="<?php echo htmlspecialchars($_POST['join_date'] ?? date('Y-m-d')); ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-toggle-on me-1 text-muted"></i>Status</label>
                <select name="status" class="form-select" style="max-width: 200px;">
                    <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <!-- Fitness Goals (Visible for Gym and Both) -->
            <div id="fitnessGoalSection">
                <div class="section-label mb-3 mt-4">
                    <h6 class="fw-bold text-dark"><i class="fas fa-bullseye me-1 text-danger"></i> Fitness Goals</h6>
                    <hr class="mt-1">
                </div>

                <div class="mb-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <label class="form-label mb-0 text-muted"><i class="fas fa-check-square me-1 text-muted"></i>Select member's fitness goals</label>
                        <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" data-bs-toggle="modal" data-bs-target="#addFitnessGoalModal" title="Add new goal"><i class="fas fa-plus me-1"></i>Add Goal</button>
                    </div>
                    <div class="row" id="fitnessGoalCheckboxes">
                        <?php 
                        $selectedGoals = $_POST['fitness_goals'] ?? [];
                        foreach ($fitnessGoals as $fg): 
                            $goalItemId = 'goal_item_' . md5($fg);
                        ?>
                            <div class="col-md-6 mb-2" id="<?php echo $goalItemId; ?>">
                                <div class="form-check d-flex justify-content-between align-items-center bg-light px-3 py-1 rounded border">
                                    <div>
                                        <input class="form-check-input" type="checkbox" name="fitness_goals[]" value="<?php echo htmlspecialchars($fg); ?>" id="fg_<?php echo md5($fg); ?>" <?php echo in_array($fg, $selectedGoals) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold ms-1" for="fg_<?php echo md5($fg); ?>"><?php echo htmlspecialchars($fg); ?></label>
                                    </div>
                                    <button type="button" class="btn btn-link text-danger p-0 ms-2 text-decoration-none" onclick="deleteFitnessGoal('<?php echo htmlspecialchars(addslashes($fg)); ?>', '<?php echo $goalItemId; ?>')" title="Delete this goal"><i class="fas fa-trash-alt fa-sm"></i></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- 3. Diet Plan Section (Gym & Both ONLY - Hidden for Kids Play) -->
            <div id="gymPlanSection">
                <div class="section-label mb-3 mt-4">
                    <h6 class="fw-bold text-dark"><i class="fas fa-utensils me-1 text-primary"></i> Diet Plan <small class="fw-normal text-muted">(Optional)</small></h6>
                    <hr class="mt-1">
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label"><i class="fas fa-tag me-1 text-muted"></i>Select Diet Plan</label>
                        <select name="plan_id" class="form-select" id="planSelect" onchange="onPlanChange()">
                            <option value="0" data-duration="30">-- Standard 30 Days / Custom Duration --</option>
                            <?php foreach ($plans as $p): ?>
                                <option value="<?php echo $p['id']; ?>" data-duration="<?php echo $p['duration_days']; ?>" <?php echo (int)($_POST['plan_id'] ?? 0) === (int)$p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['name']); ?> &mdash; <?php echo $p['duration_days']; ?> days
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Diet plan only sets validity days; monthly fee is entered separately below.</small>
                    </div>
                </div>

                <div id="planDatesRow">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Plan Start Date</label>
                            <input type="date" name="start_date" id="startDateInput" class="form-control" value="<?php echo htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')); ?>" onchange="updateEndDate()">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-calendar-check me-1 text-muted"></i>Plan End Date</label>
                            <input type="text" class="form-control bg-light" id="endDateDisplay" readonly placeholder="Auto-calculated">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Trainer Section (Gym & Both ONLY - completely excluded for Kids Play) -->
            <div id="trainerSection">
                <div class="section-label mb-3 mt-4">
                    <h6 class="fw-bold text-muted"><i class="fas fa-dumbbell me-1"></i> Trainer &amp; Trainer Fee</h6>
                    <hr class="mt-1">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-user-tie me-1 text-muted"></i>Select Trainer</label>
                        <select name="trainer_id" id="trainerSelect" class="form-select" onchange="onTrainerChange()">
                            <option value="0" data-fee="0">-- No Trainer (Skip) --</option>
                            <?php foreach ($trainers as $t): ?>
                                <option value="<?php echo $t['id']; ?>" data-fee="<?php echo (float)($t['fee'] ?? 0); ?>" <?php echo (int)($_POST['trainer_id'] ?? 0) === (int)$t['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['specialty'] ?? 'General'); ?>) - Fee: Rs. <?php echo number_format((float)($t['fee'] ?? 0), 0); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-hand-holding-usd me-1 text-muted"></i>Trainer Fee (Rs.)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs.</span>
                            <input type="number" step="0.01" min="0" name="trainer_fee" id="trainerFeeInput" class="form-control" placeholder="0" value="<?php echo htmlspecialchars($_POST['trainer_fee'] ?? ''); ?>" oninput="recalculateTotals()">
                        </div>
                        <small class="text-muted" id="trainerFeeNotice">Automatically loaded from selected trainer's fee. Can be modified if needed.</small>
                    </div>
                </div>
            </div>

            <!-- 5. Fee & Payment Details Section -->
            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-dark"><i class="fas fa-cash-register text-success me-2"></i>Fees, Discount &amp; Payment Details</h6>
                <hr class="mt-1 mb-3">
            </div>

            <div class="card p-3 mb-4 bg-light border">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold"><i class="fas fa-file-invoice me-1 text-primary"></i>Registration Fee (One-Time) *</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs.</span>
                            <input type="number" step="0.01" min="0" name="registration_fee" id="regFeeInput" class="form-control form-control-lg" placeholder="0" value="<?php echo htmlspecialchars($_POST['registration_fee'] ?? ''); ?>" oninput="recalculateTotals()">
                        </div>
                        <small class="text-muted">Admission / one-time registration charges</small>
                    </div>

                    <div class="col-md-6" id="monthlyFeeCol">
                        <label class="form-label fw-bold text-primary"><i class="fas fa-calendar-check me-1"></i>Gym Monthly Fee (Rs.) *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white">Rs.</span>
                            <input type="number" step="0.01" min="0" name="monthly_fee" id="monthlyFeeInput" class="form-control form-control-lg fw-bold" placeholder="0" value="<?php echo htmlspecialchars($_POST['monthly_fee'] ?? ''); ?>" oninput="recalculateTotals()">
                        </div>
                        <small class="text-muted">Monthly gym fee to be paid every month (recurring monthly fee)</small>
                    </div>

                    <div class="col-md-6" id="kidsFeeCol" style="display: none;">
                        <label class="form-label fw-bold text-success"><i class="fas fa-child me-1"></i>Kids Play Area Fee (Rs.) *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-success text-white">Rs.</span>
                            <input type="number" step="0.01" min="0" name="kids_fee" id="kidsFeeInput" class="form-control form-control-lg fw-bold" placeholder="0" value="<?php echo htmlspecialchars($_POST['kids_fee'] ?? ''); ?>" oninput="recalculateTotals()">
                        </div>
                        <small class="text-muted">Kids play area monthly / pass fee</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold"><i class="fas fa-tag me-1 text-danger"></i>Discount Concession</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="input-group">
                                    <input type="number" step="0.1" min="0" max="100" id="discountPercentInput" class="form-control form-control-lg fw-bold text-danger" placeholder="0" oninput="onDiscountPercentInput()">
                                    <span class="input-group-text bg-danger text-white fw-bold">%</span>
                                </div>
                                <small class="text-muted">Discount in percent (%)</small>
                            </div>
                            <div class="col-6">
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="number" step="0.01" min="0" name="discount" id="discountInput" class="form-control form-control-lg fw-bold text-danger" placeholder="0" value="<?php echo htmlspecialchars($_POST['discount'] ?? ''); ?>" oninput="onDiscountRsInput()">
                                </div>
                                <small class="text-muted">Discount in rupees (Rs.)</small>
                            </div>
                        </div>
                        <div id="discountHelperBadge" class="mt-1" style="min-height: 22px;"></div>
                    </div>
                </div>

                <hr class="my-3">

                <!-- Dynamic Summary Receipt Box -->
                <div class="summary-box mb-3">
                    <h6 class="fw-bold mb-3 text-warning"><i class="fas fa-receipt me-1"></i>Fee Breakdown &amp; Calculation</h6>
                    <div class="summary-item">
                        <span><i class="fas fa-id-badge me-2 text-info"></i>Registration Fee:</span>
                        <strong id="summaryRegFee">Rs. 0.00</strong>
                    </div>
                    <div class="summary-item" id="summaryMonthlyRow">
                        <span><i class="fas fa-calendar-check me-2 text-primary"></i>Gym Monthly Fee:</span>
                        <strong id="summaryMonthlyFee">Rs. 0.00</strong>
                    </div>
                    <div class="summary-item" id="summaryKidsRow" style="display: none;">
                        <span><i class="fas fa-child me-2 text-success"></i>Kids Play Area Fee:</span>
                        <strong id="summaryKidsFee">Rs. 0.00</strong>
                    </div>
                    <div class="summary-item" id="summaryTrainerRow">
                        <span><i class="fas fa-dumbbell me-2 text-warning"></i>Trainer Fee:</span>
                        <strong id="summaryTrainerFee">Rs. 0.00</strong>
                    </div>
                    <div class="summary-item">
                        <span><i class="fas fa-minus-circle me-2 text-danger"></i>Discount Applied:</span>
                        <strong class="text-danger" id="summaryDiscount">- Rs. 0.00</strong>
                    </div>
                    <div class="summary-item total-row">
                        <span><i class="fas fa-calculator me-2"></i>Total Payable:</span>
                        <span id="summaryTotal">Rs. 0.00</span>
                    </div>
                    <div class="summary-item">
                        <span><i class="fas fa-hand-holding-usd me-2 text-success"></i>Amount Received:</span>
                        <strong class="text-success" id="summaryReceived">Rs. 0.00</strong>
                    </div>
                    <div class="summary-item remaining-row" id="summaryRemainingRow">
                        <span><i class="fas fa-hourglass-half me-2"></i>Remaining Balance:</span>
                        <span id="summaryRemaining">Rs. 0.00</span>
                    </div>
                </div>

                <!-- Payment Inputs -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-success"><i class="fas fa-money-bill-wave me-1"></i>Amount Received (Rs.) *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-success text-white">Rs.</span>
                            <input type="number" step="0.01" min="0" name="amount_received" id="amountReceivedInput" class="form-control form-control-lg fw-bold text-success" placeholder="0" value="<?php echo htmlspecialchars($_POST['amount_received'] ?? ''); ?>" oninput="recalculateTotals()">
                        </div>
                        <div class="mt-1">
                            <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" onclick="payFull()"><i class="fas fa-check me-1"></i>Pay Full Amount</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="payZero()"><i class="fas fa-times me-1"></i>Pay Later (0)</button>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold"><i class="fas fa-credit-card me-1 text-muted"></i>Payment Method *</label>
                        <select name="payment_method" id="paymentMethodSelect" class="form-select form-select-lg" onchange="onPaymentMethodChange()">
                            <option value="cash" <?php echo ($_POST['payment_method'] ?? 'cash') === 'cash' ? 'selected' : ''; ?>>Cash (Cash Book)</option>
                            <option value="bank_transfer" <?php echo ($_POST['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank (Bank Book)</option>
                        </select>
                        <small class="text-muted d-block mt-1" id="paymentBookNotice">
                            <i class="fas fa-book text-success me-1"></i>Entries automatically record in <strong>Cash Book</strong>.
                        </small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold"><i class="fas fa-balance-scale me-1 text-muted"></i>Remaining Balance (Rs.)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs.</span>
                            <input type="text" id="remainingBalanceInput" class="form-control form-control-lg fw-bold bg-white" readonly placeholder="0.00" value="">
                        </div>
                        <small class="d-block mt-1" id="remainingBadgeText"><span class="text-muted">Balance will update automatically</span></small>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label"><i class="fas fa-comment-dots me-1 text-muted"></i>Payment Notes / Remarks (Optional)</label>
                    <input type="text" name="payment_notes" class="form-control" placeholder="e.g. Paid in full / Slip # / Cheque #" value="<?php echo htmlspecialchars($_POST['payment_notes'] ?? ''); ?>">
                </div>
            </div>

            <!-- Submit Button -->
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-warning btn-lg fw-bold px-4" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;">
                    <i class="fas fa-print me-1"></i>Save &amp; Print Slip
                </button>
                <a href="index.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
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
var selectedAccessType = '<?php echo htmlspecialchars($_POST['access_type'] ?? 'gym'); ?>';

function selectAccessType(type) {
    selectedAccessType = type;
    document.getElementById('accessTypeInput').value = type;

    document.querySelectorAll('.access-type-card').forEach(function(card) {
        card.classList.remove('selected');
    });
    var el = document.getElementById('access-card-' + type);
    if (el) el.classList.add('selected');

    // DOM Elements to toggle
    var guardianCol = document.getElementById('guardianNameCol');
    var guardianInput = document.getElementById('guardianInput');
    var nameLabel = document.getElementById('nameLabel');
    var guardianLabel = document.getElementById('guardianLabel');
    var phoneLabel = document.getElementById('phoneLabel');
    var gymPlanSection = document.getElementById('gymPlanSection');
    var monthlyFeeCol = document.getElementById('monthlyFeeCol');
    var kidsFeeCol = document.getElementById('kidsFeeCol');
    var trainerSection = document.getElementById('trainerSection');
    var fitnessGoalSection = document.getElementById('fitnessGoalSection');

    // Summary elements
    var summaryMonthlyRow = document.getElementById('summaryMonthlyRow');
    var summaryKidsRow = document.getElementById('summaryKidsRow');
    var summaryTrainerRow = document.getElementById('summaryTrainerRow');

    if (type === 'kids_play') {
        // --- KIDS PLAY AREA ONLY ---
        // Hide Gym Plan, Hide Trainer, Hide Fitness Goals, Hide Gym Monthly Fee
        gymPlanSection.style.display = 'none';
        trainerSection.style.display = 'none';
        fitnessGoalSection.style.display = 'none';
        monthlyFeeCol.style.display = 'none';
        summaryMonthlyRow.style.display = 'none';
        summaryTrainerRow.style.display = 'none';

        // Show Kids Fee & Guardian Name
        kidsFeeCol.style.display = 'block';
        summaryKidsRow.style.display = 'flex';
        guardianCol.style.display = 'block';
        guardianInput.required = true;

        // Update labels
        nameLabel.innerHTML = '<i class="fas fa-child me-1 text-success"></i>Child / Kid Name *';
        guardianLabel.innerHTML = '<i class="fas fa-user-shield me-1 text-muted"></i>Parent / Guardian Name *';
        phoneLabel.innerHTML = '<i class="fas fa-phone me-1 text-muted"></i>Guardian Contact *';

        // Reset gym plan & trainer fields
        document.getElementById('planSelect').value = '0';
        document.getElementById('monthlyFeeInput').value = '0.00';
        document.getElementById('trainerSelect').value = '0';
        document.getElementById('trainerFeeInput').value = '0.00';
    } else if (type === 'both') {
        // --- GYM + KIDS COMBINATION ---
        // Show Gym Plan, Gym Monthly Fee, Kids Fee, Trainer, Guardian/Child field, and Fitness Goals
        gymPlanSection.style.display = 'block';
        monthlyFeeCol.style.display = 'block';
        kidsFeeCol.style.display = 'block';
        trainerSection.style.display = 'block';
        fitnessGoalSection.style.display = 'block';
        summaryMonthlyRow.style.display = 'flex';
        summaryKidsRow.style.display = 'flex';
        summaryTrainerRow.style.display = 'flex';

        guardianCol.style.display = 'block';
        guardianInput.required = false;

        nameLabel.innerHTML = '<i class="fas fa-user me-1 text-warning"></i>Primary Member Name *';
        guardianLabel.innerHTML = '<i class="fas fa-child me-1 text-success"></i>Child / Kid Name (Optional)';
        phoneLabel.innerHTML = '<i class="fas fa-phone me-1 text-muted"></i>Phone Number *';
    } else {
        // --- GYM ACCESS ONLY (Default) ---
        // Show Gym Plan, Trainer, Fitness Goals, Monthly Fee; Hide Kids Fee & Guardian Name
        gymPlanSection.style.display = 'block';
        monthlyFeeCol.style.display = 'block';
        trainerSection.style.display = 'block';
        fitnessGoalSection.style.display = 'block';
        summaryMonthlyRow.style.display = 'flex';
        summaryTrainerRow.style.display = 'flex';

        kidsFeeCol.style.display = 'none';
        summaryKidsRow.style.display = 'none';
        guardianCol.style.display = 'none';
        guardianInput.required = false;

        document.getElementById('kidsFeeInput').value = '0.00';

        nameLabel.innerHTML = '<i class="fas fa-user me-1 text-muted"></i>Full Name *';
        phoneLabel.innerHTML = '<i class="fas fa-phone me-1 text-muted"></i>Phone Number *';
    }

    recalculateTotals();
}

function onPlanChange() {
    updateEndDate();
}

function updateEndDate() {
    var select = document.getElementById('planSelect');
    var opt = select.options[select.selectedIndex];
    var duration = parseInt(opt.getAttribute('data-duration')) || 30;
    var startDateVal = document.getElementById('startDateInput').value;

    if (startDateVal) {
        var d = new Date(startDateVal);
        d.setDate(d.getDate() + duration);
        var dd = String(d.getDate()).padStart(2, '0');
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var yyyy = d.getFullYear();
        document.getElementById('endDateDisplay').value = dd + '/' + mm + '/' + yyyy + ' (' + duration + ' days)';
    } else {
        document.getElementById('endDateDisplay').value = '';
    }
}

function onTrainerChange() {
    var select = document.getElementById('trainerSelect');
    var opt = select.options[select.selectedIndex];
    var fee = parseFloat(opt.getAttribute('data-fee')) || 0;
    var trainerFeeInput = document.getElementById('trainerFeeInput');

    if (select.value > 0 && fee > 0) {
        trainerFeeInput.value = fee.toFixed(2);
    } else {
        trainerFeeInput.value = '';
    }

    recalculateTotals();
}

function onPaymentMethodChange() {
    var method = document.getElementById('paymentMethodSelect').value;
    var notice = document.getElementById('paymentBookNotice');

    if (method === 'cash') {
        notice.innerHTML = '<i class="fas fa-book text-success me-1"></i>Entries automatically record in <strong>Cash Book</strong>.';
    } else {
        notice.innerHTML = '<i class="fas fa-university text-primary me-1"></i>Entries automatically record in <strong>Bank Book</strong>.';
    }
}

var lastDiscountSource = 'rs';

function getSubtotal() {
    var regFee = parseFloat(document.getElementById('regFeeInput').value) || 0;
    var monthlyFee = parseFloat(document.getElementById('monthlyFeeInput').value) || 0;
    var kidsFee = parseFloat(document.getElementById('kidsFeeInput').value) || 0;
    var trainerFee = parseFloat(document.getElementById('trainerFeeInput').value) || 0;

    if (selectedAccessType === 'kids_play') {
        monthlyFee = 0;
        trainerFee = 0;
    } else if (selectedAccessType === 'gym') {
        kidsFee = 0;
    }
    return regFee + monthlyFee + kidsFee + trainerFee;
}

function onDiscountPercentInput() {
    lastDiscountSource = 'percent';
    var pct = parseFloat(document.getElementById('discountPercentInput').value) || 0;
    var subtotal = getSubtotal();

    if (pct > 0 && subtotal > 0) {
        var rs = (subtotal * pct) / 100;
        document.getElementById('discountInput').value = rs.toFixed(2);
        updateDiscountBadge(pct, rs);
    } else if (pct === 0) {
        document.getElementById('discountInput').value = '';
        updateDiscountBadge(0, 0);
    }
    recalculateTotals();
}

function onDiscountRsInput() {
    lastDiscountSource = 'rs';
    var rs = parseFloat(document.getElementById('discountInput').value) || 0;
    var subtotal = getSubtotal();

    if (rs > 0 && subtotal > 0) {
        var pct = (rs / subtotal) * 100;
        document.getElementById('discountPercentInput').value = pct % 1 === 0 ? pct.toFixed(0) : pct.toFixed(1);
        updateDiscountBadge(pct, rs);
    } else if (rs === 0) {
        document.getElementById('discountPercentInput').value = '';
        updateDiscountBadge(0, 0);
    }
    recalculateTotals();
}

function updateDiscountBadge(pct, rs) {
    var badgeEl = document.getElementById('discountHelperBadge');
    if (rs > 0) {
        badgeEl.innerHTML = '<span class="badge text-bg-danger-subtle text-danger border border-danger-subtle"><i class="fas fa-tag me-1"></i>' + pct.toFixed(1) + '% Discount = <strong>Rs. ' + formatNumber(rs) + ' Concession</strong></span>';
    } else {
        badgeEl.innerHTML = '';
    }
}

function recalculateTotals() {
    var regFee = parseFloat(document.getElementById('regFeeInput').value) || 0;
    var monthlyFee = parseFloat(document.getElementById('monthlyFeeInput').value) || 0;
    var kidsFee = parseFloat(document.getElementById('kidsFeeInput').value) || 0;
    var trainerFee = parseFloat(document.getElementById('trainerFeeInput').value) || 0;
    var received = parseFloat(document.getElementById('amountReceivedInput').value) || 0;

    // Filter fees based on active type
    if (selectedAccessType === 'kids_play') {
        monthlyFee = 0;
        trainerFee = 0;
    } else if (selectedAccessType === 'gym') {
        kidsFee = 0;
    }

    var subtotal = regFee + monthlyFee + kidsFee + trainerFee;

    // Sync discount if subtotal changed
    if (lastDiscountSource === 'percent') {
        var pct = parseFloat(document.getElementById('discountPercentInput').value) || 0;
        if (pct > 0 && subtotal > 0) {
            var rs = (subtotal * pct) / 100;
            document.getElementById('discountInput').value = rs.toFixed(2);
            updateDiscountBadge(pct, rs);
        }
    } else if (lastDiscountSource === 'rs') {
        var rs = parseFloat(document.getElementById('discountInput').value) || 0;
        if (rs > 0 && subtotal > 0) {
            var pct = (rs / subtotal) * 100;
            document.getElementById('discountPercentInput').value = pct % 1 === 0 ? pct.toFixed(0) : pct.toFixed(1);
            updateDiscountBadge(pct, rs);
        }
    }

    var discount = parseFloat(document.getElementById('discountInput').value) || 0;
    var totalPayable = Math.max(0, subtotal - discount);
    var remaining = Math.max(0, totalPayable - received);

    // Update Summary Box
    document.getElementById('summaryRegFee').textContent = 'Rs. ' + formatNumber(regFee);
    document.getElementById('summaryMonthlyFee').textContent = 'Rs. ' + formatNumber(monthlyFee);
    document.getElementById('summaryKidsFee').textContent = 'Rs. ' + formatNumber(kidsFee);
    document.getElementById('summaryTrainerFee').textContent = 'Rs. ' + formatNumber(trainerFee);

    var discText = '- Rs. ' + formatNumber(discount);
    var pctVal = parseFloat(document.getElementById('discountPercentInput').value) || 0;
    if (pctVal > 0 && discount > 0) {
        discText += ' (' + pctVal.toFixed(1) + '%)';
    }
    document.getElementById('summaryDiscount').textContent = discText;

    document.getElementById('summaryTotal').textContent = 'Rs. ' + formatNumber(totalPayable);
    document.getElementById('summaryReceived').textContent = 'Rs. ' + formatNumber(received);

    var remEl = document.getElementById('summaryRemaining');
    var remRow = document.getElementById('summaryRemainingRow');
    remEl.textContent = 'Rs. ' + formatNumber(remaining);

    var balanceInput = document.getElementById('remainingBalanceInput');
    balanceInput.value = remaining > 0 ? formatNumber(remaining) : (totalPayable > 0 ? '0.00' : '');

    var badgeText = document.getElementById('remainingBadgeText');
    if (totalPayable > 0 && remaining <= 0) {
        remRow.classList.add('paid');
        balanceInput.className = 'form-control form-control-lg fw-bold bg-success-subtle text-success';
        badgeText.innerHTML = '<span class="badge text-bg-success"><i class="fas fa-check-circle me-1"></i>Fully Paid</span>';
    } else if (remaining > 0) {
        remRow.classList.remove('paid');
        balanceInput.className = 'form-control form-control-lg fw-bold bg-danger-subtle text-danger';
        badgeText.innerHTML = '<span class="badge text-bg-danger"><i class="fas fa-exclamation-circle me-1"></i>Due Balance: Rs. ' + formatNumber(remaining) + '</span>';
    } else {
        remRow.classList.remove('paid');
        balanceInput.className = 'form-control form-control-lg fw-bold bg-white';
        badgeText.innerHTML = '<span class="text-muted">No charges calculated</span>';
    }
}

function payFull() {
    var regFee = parseFloat(document.getElementById('regFeeInput').value) || 0;
    var monthlyFee = parseFloat(document.getElementById('monthlyFeeInput').value) || 0;
    var kidsFee = parseFloat(document.getElementById('kidsFeeInput').value) || 0;
    var trainerFee = parseFloat(document.getElementById('trainerFeeInput').value) || 0;
    var discount = parseFloat(document.getElementById('discountInput').value) || 0;

    if (selectedAccessType === 'kids_play') {
        monthlyFee = 0;
        trainerFee = 0;
    } else if (selectedAccessType === 'gym') {
        kidsFee = 0;
    }

    var total = Math.max(0, (regFee + monthlyFee + kidsFee + trainerFee) - discount);
    document.getElementById('amountReceivedInput').value = total.toFixed(2);
    recalculateTotals();
}

function payZero() {
    document.getElementById('amountReceivedInput').value = '';
    recalculateTotals();
}

function formatNumber(num) {
    return num.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
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

// Initial setup
document.addEventListener('DOMContentLoaded', function() {
    selectAccessType(selectedAccessType || 'gym');
    if (document.getElementById('planSelect').value > 0) {
        onPlanChange();
    }
    onPaymentMethodChange();
    recalculateTotals();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
