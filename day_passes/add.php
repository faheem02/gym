<?php
$activePage = 'day_passes';
$pageTitle = 'Issue Day Pass';
include __DIR__ . '/../includes/header.php';

$error = '';
$members = $pdo->query('SELECT id, name, phone FROM members WHERE status = "active" ORDER BY name')->fetchAll();

$memberPlans = $pdo->query(
    "SELECT m.id AS member_id, m.name AS member_name, p.name AS plan_name, p.duration_days, s.end_date, p.day_pass_discount
     FROM members m
     JOIN subscriptions s ON s.member_id = m.id
     JOIN plans p ON p.id = s.plan_id
     WHERE s.status = 'active' AND m.status = 'active'"
)->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

$memberPlansJson = json_encode($memberPlans);

$passTypes = [
    'gym' => ['label' => 'Gym Access', 'icon' => 'fa-dumbbell', 'color' => 'primary', 'desc' => 'Access to gym equipment & workout area', 'price' => 200],
    'kids_play' => ['label' => 'Kids Play Area', 'icon' => 'fa-child', 'color' => 'success', 'desc' => 'Access to kids playing area', 'price' => 100],
    'both' => ['label' => 'Gym + Kids Play', 'icon' => 'fa-users', 'color' => 'warning', 'desc' => 'Full access: gym + kids play area', 'price' => 250],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitor_name = trim($_POST['visitor_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $pass_type = $_POST['pass_type'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $member_id = (int)($_POST['member_id'] ?? 0);
    $pass_date = trim($_POST['pass_date'] ?? date('Y-m-d'));
    $notes = trim($_POST['notes'] ?? '');

    if ($visitor_name === '') {
        $error = 'Visitor name is required.';
    } elseif (!array_key_exists($pass_type, $passTypes)) {
        $error = 'Please select a valid pass type.';
    } elseif ($amount < 0) {
        $error = 'Amount cannot be negative.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO day_passes (visitor_name, phone, member_id, pass_type, pass_date, check_in_time, amount, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $visitor_name,
            $phone ?: null,
            $member_id > 0 ? $member_id : null,
            $pass_type,
            $pass_date,
            date('H:i:s'),
            $amount,
            $notes ?: null,
        ]);
        header('Location: /gym/day_passes/index.php?msg=issued&date=' . urlencode($pass_date));
        exit;
    }
}
?>

<div class="card form-card" style="max-width: 720px;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-ticket-alt text-warning me-2"></i>Issue New Day Pass</h5>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="section-label mb-3">
                <h6 class="fw-bold text-muted"><i class="fas fa-user me-1"></i> Visitor Information</h6>
                <hr class="mt-1">
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user me-1 text-muted"></i>Visitor Name *</label>
                <input type="text" name="visitor_name" class="form-control" placeholder="Enter visitor's name" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-phone me-1 text-muted"></i>Phone</label>
                    <input type="text" name="phone" class="form-control" placeholder="03XX-XXXXXXX">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-link me-1 text-muted"></i>Related Member</label>
                    <select name="member_id" class="form-select" id="memberSelect" onchange="onMemberChange()">
                        <option value="0">-- Walk-in (No Member) --</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']) . ' (' . htmlspecialchars($m['phone']) . ')'; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="memberPlanInfo" style="display: none;">
                <div class="alert alert-success py-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-id-card"></i>
                        <div>
                            <strong id="planMemberName"></strong>
                            <span class="text-muted">has</span>
                            <span class="badge text-bg-success" id="planName"></span>
                            <span class="text-muted">active until</span>
                            <strong id="planEndDate"></strong>
                        </div>
                    </div>
                    <div class="mt-1" id="discountInfo" style="display:none;">
                        <i class="fas fa-tag text-warning me-1"></i>
                        <strong class="text-warning" id="discountText"></strong>
                    </div>
                </div>
            </div>

            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-muted"><i class="fas fa-tag me-1"></i> Pass Details</h6>
                <hr class="mt-1">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Select Pass Type *</label>
                <div class="row g-3 mt-1">
                    <?php foreach ($passTypes as $key => $pt): ?>
                        <div class="col-md-4">
                            <div class="pass-type-card" onclick="selectPassType('<?php echo $key; ?>')">
                                <input type="radio" name="pass_type" value="<?php echo $key; ?>" id="type_<?php echo $key; ?>" class="d-none" <?php echo $key === 'gym' ? 'checked' : ''; ?>>
                                <div class="pass-type-inner border rounded-3 p-3 text-center" id="card_<?php echo $key; ?>">
                                    <i class="fas <?php echo $pt['icon']; ?> fa-2x mb-2 text-<?php echo $pt['color']; ?>"></i>
                                    <div class="fw-bold"><?php echo $pt['label']; ?></div>
                                    <small class="text-muted"><?php echo $pt['desc']; ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Pass Date *</label>
                    <input type="date" name="pass_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-money-bill-wave me-1 text-muted"></i>Amount (Rs.) *</label>
                    <div class="input-group">
                        <input type="number" step="1" name="amount" class="form-control" id="amountInput" value="200" min="0" required>
                        <span class="input-group-text" id="discountBadge" style="display:none; background:#d4edda; color:#155724; font-weight:600;"></span>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-sticky-note me-1 text-muted"></i>Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Issue Pass</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
var memberPlans = <?php echo $memberPlansJson; ?>;
var baseAmounts = { gym: 200, kids_play: 100, both: 250 };
var selectedType = 'gym';
var discountPercent = 0;

function selectPassType(type) {
    selectedType = type;
    document.getElementById('type_' + type).checked = true;
    document.querySelectorAll('.pass-type-inner').forEach(function(el) {
        el.classList.remove('border-warning', 'bg-warning', 'bg-opacity-10', 'shadow-sm');
        el.classList.add('border');
    });
    document.getElementById('card_' + type).classList.add('border-warning', 'bg-warning', 'bg-opacity-10', 'shadow-sm');
    document.getElementById('card_' + type).classList.remove('border');
    applyDiscount();
}

function onMemberChange() {
    var memberId = document.getElementById('memberSelect').value;
    var infoBox = document.getElementById('memberPlanInfo');
    var discountInfo = document.getElementById('discountInfo');
    var discountText = document.getElementById('discountText');

    if (memberId > 0 && memberPlans[memberId]) {
        var plan = memberPlans[memberId];
        var endDate = new Date(plan.end_date);

        document.getElementById('planMemberName').textContent = plan.member_name;
        document.getElementById('planName').textContent = plan.plan_name;
        document.getElementById('planEndDate').textContent = endDate.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});

        discountPercent = parseInt(plan.day_pass_discount) || 0;

        if (discountPercent > 0) {
            discountInfo.style.display = 'block';
            if (discountPercent >= 100) {
                discountText.innerHTML = '<i class="fas fa-gift me-1"></i>' + plan.plan_name + ' Member - Day passes are <u>FREE</u>!';
            } else {
                discountText.innerHTML = '<i class="fas fa-percent me-1"></i>' + plan.plan_name + ' Member - ' + discountPercent + '% discount on all day passes!';
            }
        } else {
            discountInfo.style.display = 'none';
        }
        infoBox.style.display = 'block';
    } else {
        discountPercent = 0;
        infoBox.style.display = 'none';
    }
    applyDiscount();
}

function applyDiscount() {
    var base = baseAmounts[selectedType] || 0;
    var finalAmount = base;

    if (discountPercent > 0) {
        finalAmount = Math.round(base - (base * discountPercent / 100));
    }

    document.getElementById('amountInput').value = finalAmount;

    var badge = document.getElementById('discountBadge');
    if (discountPercent > 0 && finalAmount < base) {
        badge.textContent = (discountPercent >= 100 ? 'FREE' : discountPercent + '% OFF');
        badge.style.display = 'inline';
    } else {
        badge.style.display = 'none';
    }
}

selectPassType('gym');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
