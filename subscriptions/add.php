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
            <div class="mb-3 position-relative">
                <label class="form-label"><i class="fas fa-user me-1 text-muted"></i>Member *</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="subMemberSearch" class="form-control" placeholder="Type member name or phone..." autocomplete="off" spellcheck="false" required>
                    <button type="button" class="btn btn-outline-secondary" id="clearSubMember" style="display:none;"><i class="fas fa-times"></i></button>
                </div>
                <input type="hidden" name="member_id" id="subMemberId" value="<?php echo htmlspecialchars($_POST['member_id'] ?? ''); ?>" required>
                <div id="subMemberResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:220px; overflow-y:auto; display:none; border-radius:6px;"></div>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-clipboard-list me-1 text-muted"></i>Plan *</label>
                <select name="plan_id" class="form-select" required>
                    <option value="">-- Select Plan --</option>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($_POST['plan_id'] ?? '') == $p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']) . ' - ' . $p['duration_days'] . ' days (Rs.' . number_format($p['price'], 2) . ')'; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Start Date *</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')); ?>" required>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Assign Plan</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var members = <?php echo json_encode($members); ?>;
    var searchInput = document.getElementById('subMemberSearch');
    var hiddenInput = document.getElementById('subMemberId');
    var resultsBox = document.getElementById('subMemberResults');
    var clearBtn = document.getElementById('clearSubMember');

    var activeId = hiddenInput.value;
    if (activeId) {
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
        hiddenInput.value = '';
        if (this.value.trim().length > 0) {
            clearBtn.style.display = 'inline-block';
        } else {
            clearBtn.style.display = 'none';
        }
        renderList(this.value);
    });

    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        hiddenInput.value = '';
        clearBtn.style.display = 'none';
        resultsBox.style.display = 'none';
        resultsBox.innerHTML = '';
        searchInput.focus();
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#subMemberSearch') && !e.target.closest('#subMemberResults')) {
            resultsBox.style.display = 'none';
        }
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
