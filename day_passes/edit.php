<?php
$activePage = 'day_passes';
$pageTitle = 'Edit Day Pass';
include __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /gym/day_passes/');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM day_passes WHERE id = ?");
$stmt->execute([$id]);
$pass = $stmt->fetch();

if (!$pass) {
    echo '<div class="alert alert-danger">Day pass not found.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$error = '';
$members = $pdo->query('SELECT id, name, phone FROM members WHERE status = "active" ORDER BY name')->fetchAll();

$passTypes = [
    'gym' => ['label' => 'Gym Access', 'price' => 200],
    'kids_play' => ['label' => 'Kids Play Area', 'price' => 100],
    'both' => ['label' => 'Gym + Kids Play', 'price' => 250],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitor_name = trim($_POST['visitor_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $pass_type = $_POST['pass_type'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $member_id = (int)($_POST['member_id'] ?? 0);
    $pass_date = trim($_POST['pass_date'] ?? date('Y-m-d'));
    $check_in_time = trim($_POST['check_in_time'] ?? '');
    $check_out_time = trim($_POST['check_out_time'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($visitor_name === '') {
        $error = 'Visitor name is required.';
    } elseif (!array_key_exists($pass_type, $passTypes)) {
        $error = 'Please select a valid pass type.';
    } elseif ($amount < 0) {
        $error = 'Amount cannot be negative.';
    } else {
        $stmt = $pdo->prepare(
            'UPDATE day_passes SET visitor_name = ?, phone = ?, member_id = ?, pass_type = ?, pass_date = ?, check_in_time = ?, check_out_time = ?, amount = ?, notes = ? WHERE id = ?'
        );
        $stmt->execute([
            $visitor_name,
            $phone ?: null,
            $member_id > 0 ? $member_id : null,
            $pass_type,
            $pass_date,
            $check_in_time ?: $pass['check_in_time'],
            $check_out_time ?: null,
            $amount,
            $notes ?: null,
            $id
        ]);
        header('Location: /gym/day_passes/index.php?msg=updated&date=' . urlencode($pass_date));
        exit;
    }
}
?>

<div class="card form-card" style="max-width: 720px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0"><i class="fas fa-edit text-warning me-2"></i>Edit Day Pass #<?php echo $pass['id']; ?></h5>
            <a href="index.php?date=<?php echo urlencode($pass['pass_date']); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>

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
                <input type="text" name="visitor_name" class="form-control" value="<?php echo htmlspecialchars($_POST['visitor_name'] ?? $pass['visitor_name']); ?>" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-phone me-1 text-muted"></i>Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? $pass['phone'] ?? ''); ?>" placeholder="03XX-XXXXXXX">
                </div>
                <div class="col-md-6 mb-3 position-relative">
                    <label class="form-label"><i class="fas fa-link me-1 text-muted"></i>Related Member (Optional)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" id="dpEditMemberSearch" class="form-control" placeholder="Search member name or phone..." autocomplete="off" spellcheck="false">
                        <button type="button" class="btn btn-outline-secondary" id="clearDpEditMember" style="display:none;"><i class="fas fa-times"></i></button>
                    </div>
                    <input type="hidden" name="member_id" id="dpEditMemberId" value="<?php echo htmlspecialchars((string)($_POST['member_id'] ?? $pass['member_id'] ?? '0')); ?>">
                    <div id="dpEditMemberResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:200px; overflow-y:auto; display:none; border-radius:6px;"></div>
                </div>
            </div>

            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-muted"><i class="fas fa-tag me-1"></i> Pass Details</h6>
                <hr class="mt-1">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Pass Type *</label>
                    <select name="pass_type" class="form-select" required>
                        <?php foreach ($passTypes as $key => $pt): ?>
                            <option value="<?php echo $key; ?>" <?php echo (($_POST['pass_type'] ?? $pass['pass_type']) === $key) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pt['label']); ?> (Rs. <?php echo $pt['price']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold"><i class="fas fa-money-bill-wave me-1 text-muted"></i>Amount (Rs.) *</label>
                    <input type="number" name="amount" step="1" class="form-control" value="<?php echo htmlspecialchars($_POST['amount'] ?? $pass['amount']); ?>" required min="0">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Pass Date</label>
                    <input type="date" name="pass_date" class="form-control" value="<?php echo htmlspecialchars($_POST['pass_date'] ?? $pass['pass_date']); ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-clock me-1 text-muted"></i>Check In Time</label>
                    <input type="time" name="check_in_time" class="form-control" value="<?php echo htmlspecialchars($_POST['check_in_time'] ?? $pass['check_in_time']); ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-sign-out-alt me-1 text-muted"></i>Check Out Time</label>
                    <input type="time" name="check_out_time" class="form-control" value="<?php echo htmlspecialchars($_POST['check_out_time'] ?? $pass['check_out_time'] ?? ''); ?>">
                    <small class="text-muted">Leave blank if still inside</small>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label"><i class="fas fa-sticky-note me-1 text-muted"></i>Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."><?php echo htmlspecialchars($_POST['notes'] ?? $pass['notes'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold px-4"><i class="fas fa-save me-1"></i>Update Day Pass</button>
                <a href="index.php?date=<?php echo urlencode($pass['pass_date']); ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var members = <?php echo json_encode($members); ?>;
    var searchInput = document.getElementById('dpEditMemberSearch');
    var hiddenInput = document.getElementById('dpEditMemberId');
    var resultsBox = document.getElementById('dpEditMemberResults');
    var clearBtn = document.getElementById('clearDpEditMember');

    var activeId = hiddenInput.value;
    if (activeId && activeId !== '0') {
        var found = members.find(function(m) { return m.id == activeId; });
        if (found) {
            searchInput.value = found.name + (found.phone ? ' (' + found.phone + ')' : '');
            clearBtn.style.display = 'inline-block';
        }
    }

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
        searchInput.focus();
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#dpEditMemberSearch') && !e.target.closest('#dpEditMemberResults')) {
            resultsBox.style.display = 'none';
        }
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
