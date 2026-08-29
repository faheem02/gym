<?php
$activePage = 'attendance';
$pageTitle = 'Attendance';
include __DIR__ . '/../includes/header.php';

$error = '';
$date = trim($_GET['date'] ?? date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $member_id = (int)($_POST['member_id'] ?? 0);
    $check_date = trim($_POST['check_date'] ?? date('Y-m-d'));

    if ($action === 'checkin') {
        if ($member_id <= 0) {
            $error = 'Select a member to check in.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM attendance WHERE member_id = ? AND check_in_date = ? AND check_out_time IS NULL');
            $stmt->execute([$member_id, $check_date]);
            if ($stmt->fetch()) {
                $error = 'This member is already checked in.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO attendance (member_id, check_in_date, check_in_time) VALUES (?, ?, ?)');
                $stmt->execute([$member_id, $check_date, date('H:i:s')]);
                header('Location: /gym/attendance/index.php?msg=checkin');
                exit;
            }
        }
    } elseif ($action === 'checkout') {
        if ($member_id <= 0) {
            $error = 'Select a member to check out.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM attendance WHERE member_id = ? AND check_in_date = ? AND check_out_time IS NULL');
            $stmt->execute([$member_id, $check_date]);
            if (!$stmt->fetch()) {
                $error = 'No active check-in found for this member today.';
            } else {
                $stmt = $pdo->prepare('UPDATE attendance SET check_out_time = ? WHERE member_id = ? AND check_in_date = ? AND check_out_time IS NULL');
                $stmt->execute([date('H:i:s'), $member_id, $check_date]);
                header('Location: /gym/attendance/index.php?msg=checkout');
                exit;
            }
        }
    }
}

$msg = $_GET['msg'] ?? '';
if ($msg === 'checkin') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Check-in recorded successfully.</div>';
if ($msg === 'checkout') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Check-out recorded successfully.</div>';

$members = $pdo->query('SELECT id, name, phone FROM members WHERE status = "active" ORDER BY name')->fetchAll();
$checkedInMembers = $pdo->prepare(
    "SELECT m.id, m.name, m.phone
     FROM attendance a
     JOIN members m ON m.id = a.member_id
     WHERE a.check_in_date = ? AND a.check_out_time IS NULL
     ORDER BY m.name"
);
$checkedInMembers->execute([date('Y-m-d')]);
$checkedInMembers = $checkedInMembers->fetchAll();

$stmt = $pdo->prepare(
    "SELECT a.check_in_date, a.check_in_time, a.check_out_time, m.name, m.phone
     FROM attendance a
     JOIN members m ON m.id = a.member_id
     WHERE a.check_in_date = ?
     ORDER BY a.check_in_time"
);
$stmt->execute([$date]);
$todayLog = $stmt->fetchAll();
?>

<?php if ($error): ?>
    <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-sign-in-alt text-success me-2"></i>Check-in</h6>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="checkin">
                    <div class="mb-3 position-relative">
                        <label class="form-label"><i class="fas fa-user me-1 text-muted"></i>Member</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="attInMemberSearch" class="form-control" placeholder="Type member name or phone..." autocomplete="off" spellcheck="false" required>
                            <button type="button" class="btn btn-outline-secondary" id="clearAttInMember" style="display:none;"><i class="fas fa-times"></i></button>
                        </div>
                        <input type="hidden" name="member_id" id="attInMemberId" required>
                        <div id="attInMemberResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:220px; overflow-y:auto; display:none; border-radius:6px;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Date</label>
                        <input type="date" name="check_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <button type="submit" class="btn btn-success fw-bold w-100"><i class="fas fa-fingerprint me-1"></i>Check In</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-sign-out-alt text-danger me-2"></i>Check-out</h6>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="checkout">
                    <div class="mb-3 position-relative">
                        <label class="form-label"><i class="fas fa-user me-1 text-muted"></i>Member (Checked-In)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="attOutMemberSearch" class="form-control" placeholder="<?php echo empty($checkedInMembers) ? 'No members currently in gym' : 'Type name to check out...'; ?>" autocomplete="off" spellcheck="false" <?php if (empty($checkedInMembers)) echo 'disabled'; ?> required>
                            <button type="button" class="btn btn-outline-secondary" id="clearAttOutMember" style="display:none;"><i class="fas fa-times"></i></button>
                        </div>
                        <input type="hidden" name="member_id" id="attOutMemberId" required>
                        <div id="attOutMemberResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:220px; overflow-y:auto; display:none; border-radius:6px;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Date</label>
                        <input type="date" name="check_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <button type="submit" class="btn btn-danger fw-bold w-100" <?php if (empty($checkedInMembers)) echo 'disabled'; ?>><i class="fas fa-sign-out-alt me-1"></i>Check Out</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-history text-primary me-2"></i>Attendance Log</h6>
                    <form method="GET" action="" class="d-flex align-items-center gap-2">
                        <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date); ?>">
                        <button type="submit" class="btn btn-dark btn-sm text-nowrap"><i class="fas fa-filter me-1"></i>Filter</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member</th>
                                <th>Phone</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($todayLog)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-calendar-times me-1"></i>No attendance records for this date.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($todayLog as $i => $a): ?>
                                <?php
                                $checkin = strtotime($a['check_in_time']);
                                $checkout = $a['check_out_time'] ? strtotime($a['check_out_time']) : null;
                                $duration = '';
                                if ($checkout) {
                                    $diff = $checkout - $checkin;
                                    $hours = floor($diff / 3600);
                                    $mins = floor(($diff % 3600) / 60);
                                    $duration = $hours . 'h ' . $mins . 'm';
                                }
                                ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($a['name']); ?></td>
                                    <td><?php echo htmlspecialchars($a['phone']); ?></td>
                                    <td><i class="fas fa-sign-in-alt text-success me-1"></i><?php echo date('h:i A', $checkin); ?></td>
                                    <td>
                                        <?php if ($checkout): ?>
                                            <i class="fas fa-sign-out-alt text-danger me-1"></i><?php echo date('h:i A', $checkout); ?>
                                        <?php else: ?>
                                            <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($duration): ?>
                                            <span class="badge bg-info"><?php echo $duration; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($a['check_out_time']): ?>
                                            <span class="badge bg-secondary">Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">In Gym</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    function setupAutocomplete(cfg) {
        var items = cfg.items;
        var searchInput = document.getElementById(cfg.searchInputId);
        var hiddenInput = document.getElementById(cfg.hiddenInputId);
        var resultsBox = document.getElementById(cfg.resultsBoxId);
        var clearBtn = document.getElementById(cfg.clearBtnId);

        if (!searchInput || !hiddenInput || !resultsBox) return;

        function escapeHtml(text) {
            var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return (text || '').replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function renderList(query) {
            var q = (query || '').trim().toLowerCase();
            resultsBox.innerHTML = '';

            if (q.length < 1) {
                resultsBox.style.display = 'none';
                return;
            }

            var filtered = items.filter(function(m) {
                return m.name.toLowerCase().includes(q) || (m.phone && m.phone.toLowerCase().includes(q));
            });

            if (filtered.length === 0) {
                resultsBox.innerHTML = '<div class="list-group-item text-muted py-2 text-center small"><i class="fas fa-user-slash me-1"></i>No matching members</div>';
                resultsBox.style.display = 'block';
                return;
            }

            filtered.slice(0, 30).forEach(function(m) {
                var a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3 small';
                a.innerHTML = '<div><strong>' + escapeHtml(m.name) + '</strong><br><span class="text-muted"><i class="fas fa-phone me-1"></i>' + (m.phone || 'No phone') + '</span></div><span class="badge bg-light text-dark border">Select</span>';
                
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    searchInput.value = m.name + (m.phone ? ' (' + m.phone + ')' : '');
                    hiddenInput.value = m.id;
                    resultsBox.style.display = 'none';
                    if (clearBtn) clearBtn.style.display = 'inline-block';
                });
                resultsBox.appendChild(a);
            });

            resultsBox.style.display = 'block';
        }

        searchInput.addEventListener('focus', function() {
            if (this.value.trim().length >= 1) {
                renderList(this.value);
            }
        });

        searchInput.addEventListener('input', function() {
            hiddenInput.value = '';
            if (clearBtn) {
                clearBtn.style.display = this.value.trim().length > 0 ? 'inline-block' : 'none';
            }
            renderList(this.value);
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                hiddenInput.value = '';
                clearBtn.style.display = 'none';
                resultsBox.style.display = 'none';
                resultsBox.innerHTML = '';
                searchInput.focus();
            });
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#' + cfg.searchInputId) && !e.target.closest('#' + cfg.resultsBoxId)) {
                resultsBox.style.display = 'none';
            }
        });
    }

    // Check-in
    setupAutocomplete({
        items: <?php echo json_encode($members); ?>,
        searchInputId: 'attInMemberSearch',
        hiddenInputId: 'attInMemberId',
        resultsBoxId: 'attInMemberResults',
        clearBtnId: 'clearAttInMember'
    });

    // Check-out
    setupAutocomplete({
        items: <?php echo json_encode($checkedInMembers); ?>,
        searchInputId: 'attOutMemberSearch',
        hiddenInputId: 'attOutMemberId',
        resultsBoxId: 'attOutMemberResults',
        clearBtnId: 'clearAttOutMember'
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
