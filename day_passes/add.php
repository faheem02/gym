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
        $newPassId = (int)$pdo->lastInsertId();
        header('Location: /gym/day_passes/slip.php?id=' . $newPassId . '&autoprint=1');
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
                <div class="col-md-6 mb-3 position-relative">
                    <label class="form-label"><i class="fas fa-link me-1 text-muted"></i>Related Member (Optional)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" id="dpMemberSearch" class="form-control" placeholder="Search member name or phone..." autocomplete="off" spellcheck="false">
                        <button type="button" class="btn btn-outline-secondary" id="clearDpMember" style="display:none;"><i class="fas fa-times"></i></button>
                    </div>
                    <input type="hidden" name="member_id" id="memberSelect" value="0">
                    <div id="dpMemberResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:200px; overflow-y:auto; display:none; border-radius:6px;"></div>
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
                <h6 class="fw-bold text-muted"><i class="fas fa-dumbbell me-1"></i> Pass Details</h6>
                <hr class="mt-1">
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-layer-group me-1 text-muted"></i>Pass Type *</label>
                <div class="row g-2">
                    <?php foreach ($passTypes as $key => $pt): ?>
                        <div class="col-md-4">
                            <div class="card h-100 pass-type-card border" id="card-<?php echo $key; ?>" onclick="selectPassType('<?php echo $key; ?>')" style="cursor: pointer;">
                                <div class="card-body text-center p-3">
                                    <i class="fas <?php echo $pt['icon']; ?> fa-2x text-<?php echo $pt['color']; ?> mb-2"></i>
                                    <h6 class="fw-bold mb-1"><?php echo $pt['label']; ?></h6>
                                    <small class="text-muted d-block mb-2"><?php echo $pt['desc']; ?></small>
                                    <span class="badge text-bg-<?php echo $pt['color']; ?> fs-6">Rs. <?php echo number_format($pt['price']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="pass_type" id="passTypeInput" value="gym">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Pass Date *</label>
                    <input type="date" name="pass_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-money-bill-wave me-1 text-muted"></i>Amount (Rs.) *</label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <input type="number" step="1" name="amount" id="amountInput" class="form-control" value="200" min="0" required>
                    </div>
                    <span id="discountBadge" class="badge text-bg-success mt-1" style="display: none;"></span>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-sticky-note me-1 text-muted"></i>Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-print me-1"></i>Issue &amp; Print Slip</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<style>
.pass-type-card.selected {
    border-color: #f7b731 !important;
    border-width: 2px !important;
    background-color: #fffdf5;
}
</style>

<script>
var baseAmounts = <?php echo json_encode(array_map(fn($p) => $p['price'], $passTypes)); ?>;
var memberPlans = <?php echo $memberPlansJson; ?>;
var selectedType = 'gym';
var discountPercent = 0;

function selectPassType(type) {
    selectedType = type;
    document.getElementById('passTypeInput').value = type;

    document.querySelectorAll('.pass-type-card').forEach(function (card) {
        card.classList.remove('selected');
    });
    var el = document.getElementById('card-' + type);
    if (el) el.classList.add('selected');

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

// Autocomplete logic for Day Pass Member Search
(function() {
    var members = <?php echo json_encode($members); ?>;
    var searchInput = document.getElementById('dpMemberSearch');
    var hiddenInput = document.getElementById('memberSelect');
    var resultsBox = document.getElementById('dpMemberResults');
    var clearBtn = document.getElementById('clearDpMember');

    function renderList(query) {
        var q = (query || '').trim().toLowerCase();
        resultsBox.innerHTML = '';

        if (q.length < 1) {
            resultsBox.style.display = 'none';
            return;
        }

        var filtered = members.filter(function(m) {
            return m.name.toLowerCase().includes(q) || (m.phone && m.phone.toLowerCase().includes(q));
        });

        if (filtered.length === 0) {
            resultsBox.innerHTML = '<div class="list-group-item text-muted py-2 text-center small"><i class="fas fa-user-slash me-1"></i>No members found</div>';
            resultsBox.style.display = 'block';
            return;
        }

        filtered.slice(0, 40).forEach(function(m) {
            var a = document.createElement('a');
            a.href = '#';
            a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3 small';
            a.innerHTML = '<div><strong>' + escapeHtml(m.name) + '</strong><br><span class="text-muted"><i class="fas fa-phone me-1"></i>' + (m.phone || 'No phone') + '</span></div><span class="badge bg-light text-dark border">Select</span>';
            
            a.addEventListener('click', function(e) {
                e.preventDefault();
                searchInput.value = m.name + (m.phone ? ' (' + m.phone + ')' : '');
                hiddenInput.value = m.id;
                resultsBox.style.display = 'none';
                clearBtn.style.display = 'inline-block';
                onMemberChange();
            });
            resultsBox.appendChild(a);
        });

        resultsBox.style.display = 'block';
    }

    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return (text || '').replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 1) {
            renderList(this.value);
        }
    });

    searchInput.addEventListener('input', function() {
        hiddenInput.value = '0';
        onMemberChange();
        if (this.value.trim().length > 0) {
            clearBtn.style.display = 'inline-block';
        } else {
            clearBtn.style.display = 'none';
        }
        renderList(this.value);
    });

    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        hiddenInput.value = '0';
        clearBtn.style.display = 'none';
        resultsBox.style.display = 'none';
        resultsBox.innerHTML = '';
        onMemberChange();
        searchInput.focus();
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#dpMemberSearch') && !e.target.closest('#dpMemberResults')) {
            resultsBox.style.display = 'none';
        }
    });
})();

selectPassType('gym');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
