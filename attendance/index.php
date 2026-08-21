<?php
$activePage = 'attendance';
$pageTitle = 'Attendance';
include __DIR__ . '/../includes/header.php';

$error = '';
$success = '';
$date = trim($_GET['date'] ?? date('Y-m-d'));
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $member_id = (int)($_POST['member_id'] ?? 0);
    $check_date = trim($_POST['check_date'] ?? date('Y-m-d'));

    if ($action === 'checkin') {
        if ($member_id <= 0) {
            $error = 'Select a member to check in.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM attendance WHERE member_id = ? AND check_in_date = ?');
            $stmt->execute([$member_id, $check_date]);
            if ($stmt->fetch()) {
                $error = 'This member is already checked in today.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO attendance (member_id, check_in_date, check_in_time) VALUES (?, ?, ?)');
                $stmt->execute([$member_id, $check_date, date('H:i:s')]);
                $success = 'Check-in recorded successfully.';
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
                $success = 'Check-out recorded successfully.';
            }
        }
    }
}

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
<?php if ($success): ?>
    <div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($success); ?></div>
    <script>setTimeout(function(){ window.location.reload(); }, 1000);</script>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-sign-in-alt text-success me-2"></i>Check-in</h6>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="checkin">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-user me-1 text-muted"></i>Member</label>
                        <select name="member_id" class="form-select" required>
                            <option value="">-- Select Member --</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']) . ' (' . htmlspecialchars($m['phone']) . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
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
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-user me-1 text-muted"></i>Member</label>
                        <select name="member_id" class="form-select" required>
                            <option value="">-- Select Member --</option>
                            <?php if (empty($checkedInMembers)): ?>
                                <option value="" disabled>No members checked in today</option>
                            <?php else: ?>
                                <?php foreach ($checkedInMembers as $m): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']) . ' (' . htmlspecialchars($m['phone']) . ')'; ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
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
                    <form method="GET" action="" class="d-flex">
                        <input type="date" name="date" class="form-control me-2" value="<?php echo $date; ?>">
                        <button class="btn btn-dark"><i class="fas fa-filter me-1"></i>Filter</button>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
