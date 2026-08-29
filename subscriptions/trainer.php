<?php
$activePage = 'subscriptions';
$pageTitle = 'Assign Trainer';
include __DIR__ . '/../includes/header.php';

$error = '';
$members = $pdo->query('SELECT id, name, phone FROM members ORDER BY name')->fetchAll();
$trainers = $pdo->query('SELECT id, name, specialty FROM trainers ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = (int)($_POST['member_id'] ?? 0);
    $trainer_id = (int)($_POST['trainer_id'] ?? 0);

    if ($member_id <= 0) {
        $error = 'Select a member.';
    } else {
        $stmt = $pdo->prepare('UPDATE members SET trainer_id = ? WHERE id = ?');
        $stmt->execute([$trainer_id > 0 ? $trainer_id : null, $member_id]);
        header('Location: /gym/subscriptions/index.php?msg=trainer');
        exit;
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
                    <input type="text" id="trainMemberSearch" class="form-control" placeholder="Type member name or phone..." autocomplete="off" spellcheck="false" required>
                    <button type="button" class="btn btn-outline-secondary" id="clearTrainMember" style="display:none;"><i class="fas fa-times"></i></button>
                </div>
                <input type="hidden" name="member_id" id="trainMemberId" value="<?php echo htmlspecialchars($_POST['member_id'] ?? ''); ?>" required>
                <div id="trainMemberResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:220px; overflow-y:auto; display:none; border-radius:6px;"></div>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user-tie me-1 text-muted"></i>Trainer *</label>
                <select name="trainer_id" class="form-select" required>
                    <option value="0">-- No Trainer (Remove) --</option>
                    <?php foreach ($trainers as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo ($_POST['trainer_id'] ?? '') == $t['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['specialty'] ?? 'General'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-user-tie me-1"></i>Assign Trainer</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var members = <?php echo json_encode($members); ?>;
    var searchInput = document.getElementById('trainMemberSearch');
    var hiddenInput = document.getElementById('trainMemberId');
    var resultsBox = document.getElementById('trainMemberResults');
    var clearBtn = document.getElementById('clearTrainMember');

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
        if (!e.target.closest('#trainMemberSearch') && !e.target.closest('#trainMemberResults')) {
            resultsBox.style.display = 'none';
        }
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
