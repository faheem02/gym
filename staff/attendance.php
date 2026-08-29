<?php
$activePage = 'staff_attendance';
$pageTitle = 'Staff Attendance';
include __DIR__ . '/../includes/header.php';

$error = '';
$msg = $_GET['msg'] ?? '';

$date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

// Handle Check-in Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'checkin') {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        $att_date = trim($_POST['attendance_date'] ?? date('Y-m-d'));
        $check_in_time = trim($_POST['check_in_time'] ?? date('H:i:s'));
        $status = $_POST['status'] ?? 'present';
        $notes = trim($_POST['notes'] ?? '');

        if ($staff_id <= 0) {
            $error = 'Please select a valid staff member.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM staff_attendance WHERE staff_id = ? AND attendance_date = ? AND check_out_time IS NULL");
            $stmt->execute([$staff_id, $att_date]);
            if ($stmt->fetch()) {
                $error = 'This staff member is already checked in for this date.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO staff_attendance (staff_id, attendance_date, check_in_time, status, notes) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$staff_id, $att_date, $check_in_time, $status, $notes ?: null]);
                header('Location: /gym/staff/attendance.php?msg=checkin' . ($date ? '&date=' . urlencode($date) : ''));
                exit;
            }
        }
    } elseif ($action === 'checkout') {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        $att_date = trim($_POST['attendance_date'] ?? date('Y-m-d'));
        $check_out_time = trim($_POST['check_out_time'] ?? date('H:i:s'));

        if ($staff_id <= 0) {
            $error = 'Please select a staff member to check out.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM staff_attendance WHERE staff_id = ? AND attendance_date = ? AND check_out_time IS NULL');
            $stmt->execute([$staff_id, $att_date]);
            $rec = $stmt->fetch();
            if (!$rec) {
                $error = 'No active on-duty check-in found for this staff member today.';
            } else {
                $stmt = $pdo->prepare('UPDATE staff_attendance SET check_out_time = ? WHERE id = ?');
                $stmt->execute([$check_out_time, $rec['id']]);
                header('Location: /gym/staff/attendance.php?msg=checkout' . ($date ? '&date=' . urlencode($date) : ''));
                exit;
            }
        }
    } elseif ($action === 'quick_checkout') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE staff_attendance SET check_out_time = ? WHERE id = ? AND check_out_time IS NULL');
            $stmt->execute([date('H:i:s'), $id]);
            header('Location: /gym/staff/attendance.php?msg=checkout' . ($date ? '&date=' . urlencode($date) : ''));
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM staff_attendance WHERE id = ?');
            $stmt->execute([$id]);
            header('Location: /gym/staff/attendance.php?msg=deleted' . ($date ? '&date=' . urlencode($date) : ''));
            exit;
        }
    }
}

// Active staff for dropdown
$allStaff = $pdo->query("SELECT id, name, role, phone FROM staff WHERE status = 'active' ORDER BY name")->fetchAll();

// Currently checked-in staff for today
$checkedInStaffStmt = $pdo->prepare("
    SELECT s.id, s.name, s.role, s.phone, sa.id AS attendance_id, sa.check_in_time
    FROM staff_attendance sa
    JOIN staff s ON s.id = sa.staff_id
    WHERE sa.attendance_date = ? AND sa.check_out_time IS NULL
    ORDER BY s.name
");
$checkedInStaffStmt->execute([$date ?: date('Y-m-d')]);
$checkedInStaff = $checkedInStaffStmt->fetchAll();

// Filter query for attendance logs
$sql = "
    SELECT sa.*, s.name AS staff_name, s.role, s.phone, s.salary
    FROM staff_attendance sa
    JOIN staff s ON s.id = sa.staff_id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (s.name LIKE ? OR s.role LIKE ? OR s.phone LIKE ? OR sa.notes LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($date !== '') {
    $sql .= " AND sa.attendance_date = ?";
    $params[] = $date;
}
if ($statusFilter === 'duty') {
    $sql .= " AND sa.check_out_time IS NULL";
} elseif ($statusFilter !== '') {
    $sql .= " AND sa.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY sa.attendance_date DESC, sa.check_in_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendanceLogs = $stmt->fetchAll();

// Metrics calculations
$totalActiveStaff = count($allStaff);
$presentCount = 0;
$onDutyCount = count($checkedInStaff);
$lateCount = 0;
$leaveCount = 0;

foreach ($attendanceLogs as $log) {
    if (in_array($log['status'], ['present', 'late', 'half_day'])) $presentCount++;
    if ($log['status'] === 'late') $lateCount++;
    if (in_array($log['status'], ['leave', 'absent'])) $leaveCount++;
}
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold mb-0"><i class="fas fa-id-badge text-warning me-2"></i>Staff Attendance</h5>
        <small class="text-muted">Track daily staff check-in, check-out, working hours, and duty status</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" onclick="downloadStaffAttendancePDF()" class="btn btn-outline-danger btn-sm fw-bold"><i class="fas fa-file-pdf me-1"></i>Download PDF</button>
        <button type="button" onclick="window.print()" class="btn btn-dark btn-sm fw-bold"><i class="fas fa-print me-1"></i>Print Report</button>
    </div>
</div>

<?php if ($msg === 'checkin'): ?>
    <div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Staff check-in recorded successfully.</div>
<?php elseif ($msg === 'checkout'): ?>
    <div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Staff check-out recorded successfully.</div>
<?php elseif ($msg === 'deleted'): ?>
    <div class="alert alert-success py-2"><i class="fas fa-trash me-1"></i>Attendance record deleted.</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Stat Counters Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary"><i class="fas fa-users"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $totalActiveStaff; ?></h5>
                    <small class="text-muted">Total Active Staff</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success"><i class="fas fa-user-check"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $presentCount; ?></h5>
                    <small class="text-muted">Present Records</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning"><i class="fas fa-business-time"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold text-warning-emphasis"><?php echo $onDutyCount; ?></h5>
                    <small class="text-muted">Currently On Duty</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger"><i class="fas fa-user-clock"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $lateCount; ?> Late / <?php echo $leaveCount; ?> Leave</h5>
                    <small class="text-muted">Exceptions</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Check-in & Check-out Forms -->
    <div class="col-lg-4 d-print-none">
        <!-- Check-in Card -->
        <div class="card shadow-sm mb-4" style="border-top:3px solid #10b981;">
            <div class="card-body">
                <h6 class="fw-bold mb-3 text-success"><i class="fas fa-sign-in-alt me-2"></i>Staff Check-in</h6>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="checkin">
                    <div class="mb-3 position-relative">
                        <label class="form-label small fw-bold mb-1">Staff Member *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                            <input type="text" id="staffInSearch" class="form-control form-control-sm" placeholder="Type staff name..." autocomplete="off" spellcheck="false" required>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="clearStaffIn" style="display:none;"><i class="fas fa-times"></i></button>
                        </div>
                        <input type="hidden" name="staff_id" id="staffInId" required>
                        <div id="staffInResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:200px; overflow-y:auto; display:none; border-radius:6px;"></div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Date</label>
                            <input type="date" name="attendance_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Check-in Time</label>
                            <input type="time" name="check_in_time" class="form-control form-control-sm" value="<?php echo date('H:i'); ?>" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="present">Present (On Time)</option>
                            <option value="late">Late Arrival</option>
                            <option value="half_day">Half Day</option>
                            <option value="leave">Approved Leave</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold mb-1">Notes / Remarks</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional notes...">
                    </div>

                    <button type="submit" class="btn btn-success btn-sm w-100 fw-bold"><i class="fas fa-fingerprint me-1"></i>Record Check In</button>
                </form>
            </div>
        </div>

        <!-- Check-out Card -->
        <div class="card shadow-sm" style="border-top:3px solid #ef4444;">
            <div class="card-body">
                <h6 class="fw-bold mb-3 text-danger"><i class="fas fa-sign-out-alt me-2"></i>Staff Check-out</h6>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="checkout">
                    <div class="mb-3 position-relative">
                        <label class="form-label small fw-bold mb-1">On-Duty Staff Member *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-clock text-muted"></i></span>
                            <input type="text" id="staffOutSearch" class="form-control form-control-sm" placeholder="<?php echo empty($checkedInStaff) ? 'No staff currently on duty' : 'Type name to check out...'; ?>" autocomplete="off" spellcheck="false" <?php if (empty($checkedInStaff)) echo 'disabled'; ?> required>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="clearStaffOut" style="display:none;"><i class="fas fa-times"></i></button>
                        </div>
                        <input type="hidden" name="staff_id" id="staffOutId" required>
                        <div id="staffOutResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:200px; overflow-y:auto; display:none; border-radius:6px;"></div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Date</label>
                            <input type="date" name="attendance_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Check-out Time</label>
                            <input type="time" name="check_out_time" class="form-control form-control-sm" value="<?php echo date('H:i'); ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger btn-sm w-100 fw-bold" <?php if (empty($checkedInStaff)) echo 'disabled'; ?>><i class="fas fa-sign-out-alt me-1"></i>Record Check Out</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Attendance Log & Multi-Filter Table -->
    <div class="col-lg-8">
        <!-- Filter Card -->
        <div class="card mb-4 shadow-sm d-print-none" style="border-top:3px solid #f7b731;">
            <div class="card-body p-3">
                <form method="GET" action="" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Search Staff / Role</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Name, role, phone..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Date</label>
                        <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="duty" <?php echo $statusFilter === 'duty' ? 'selected' : ''; ?>>Currently On Duty</option>
                            <option value="present" <?php echo $statusFilter === 'present' ? 'selected' : ''; ?>>Present (On Time)</option>
                            <option value="late" <?php echo $statusFilter === 'late' ? 'selected' : ''; ?>>Late Arrival</option>
                            <option value="half_day" <?php echo $statusFilter === 'half_day' ? 'selected' : ''; ?>>Half Day</option>
                            <option value="leave" <?php echo $statusFilter === 'leave' ? 'selected' : ''; ?>>Leave / Absent</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-warning btn-sm flex-fill fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-filter"></i></button>
                        <a href="attendance.php?date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary btn-sm" title="Today"><i class="fas fa-redo"></i></a>
                        <a href="attendance.php?date=" class="btn btn-outline-secondary btn-sm" title="All Dates"><i class="fas fa-calendar-alt"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="card shadow-sm" id="printSection" style="border-top:3px solid #f7b731;">
            <div class="card-body p-4">
                <!-- Print Letterhead -->
                <div class="d-none d-print-block mb-3">
                    <?php include __DIR__ . '/../includes/print_header.php'; ?>
                    <div class="text-center mb-3">
                        <h4 class="fw-bold mb-1">STAFF ATTENDANCE REPORT</h4>
                        <p class="text-muted small mb-0">Generated on <?php echo date('d M Y, h:i A'); ?><?php echo $date ? ' for Date: ' . date('d M Y', strtotime($date)) : ''; ?></p>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-clipboard-list text-warning me-2"></i>Attendance Records (<?php echo count($attendanceLogs); ?>)</h6>
                    <span class="badge bg-light text-dark border d-print-none"><?php echo $date ? date('d M Y', strtotime($date)) : 'All Dates'; ?></span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Staff Name</th>
                                <th>Role</th>
                                <th>Date</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th class="text-end d-print-none">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendanceLogs)): ?>
                                <tr><td colspan="9" class="text-center text-muted py-5"><i class="fas fa-user-clock fa-2x mb-2 text-warning"></i><br>No attendance records found for this date.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($attendanceLogs as $i => $a): ?>
                                <?php
                                $checkin = strtotime($a['check_in_time']);
                                $checkout = $a['check_out_time'] ? strtotime($a['check_out_time']) : null;
                                $duration = '';
                                if ($checkout) {
                                    $diff = $checkout - $checkin;
                                    $hours = floor($diff / 3600);
                                    $mins = floor(($diff % 3600) / 60);
                                    $duration = ($hours > 0 ? $hours . 'h ' : '') . $mins . 'm';
                                }

                                $statusBadges = [
                                    'present' => ['Present', 'success', 'fa-check'],
                                    'late' => ['Late', 'warning', 'fa-clock'],
                                    'half_day' => ['Half Day', 'info', 'fa-adjust'],
                                    'leave' => ['Leave', 'secondary', 'fa-user-slash'],
                                    'absent' => ['Absent', 'danger', 'fa-times'],
                                ];
                                $sb = $statusBadges[$a['status']] ?? ['Present', 'success', 'fa-check'];
                                ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td>
                                        <strong class="text-dark d-block"><?php echo htmlspecialchars($a['staff_name']); ?></strong>
                                        <small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($a['phone'] ?: 'No phone'); ?></small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars(ucfirst($a['role'])); ?></span></td>
                                    <td><small class="fw-semibold"><?php echo date('d M Y', strtotime($a['attendance_date'])); ?></small></td>
                                    <td><i class="fas fa-sign-in-alt text-success me-1"></i><?php echo date('h:i A', $checkin); ?></td>
                                    <td>
                                        <?php if ($checkout): ?>
                                            <i class="fas fa-sign-out-alt text-danger me-1"></i><?php echo date('h:i A', $checkout); ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-circle me-1" style="font-size:0.5rem;vertical-align:middle;"></i>On Duty</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($duration): ?>
                                            <span class="badge bg-light text-dark border fw-bold"><?php echo $duration; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $sb[1]; ?>"><i class="fas <?php echo $sb[2]; ?> me-1"></i><?php echo $sb[0]; ?></span>
                                    </td>
                                    <td class="text-end d-print-none">
                                        <div class="d-inline-flex gap-1">
                                            <?php if (!$checkout): ?>
                                                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Check out <?php echo htmlspecialchars($a['staff_name']); ?> now?');">
                                                    <input type="hidden" name="action" value="quick_checkout">
                                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Check Out Now">
                                                        <i class="fas fa-sign-out-alt"></i> Out
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this attendance record?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
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

<style>
#printSection .print-logo img {
    filter: brightness(0);
    -webkit-filter: brightness(0);
}
@media print {
    .page-header, .stat-card, .btn, form, .sidebar, .navbar, .d-print-none {
        display: none !important;
    }
    body {
        background: #fff !important;
        font-size: 11px;
    }
    #printSection {
        border: none !important;
        box-shadow: none !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .table-dark {
        background-color: #1a1a2e !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadStaffAttendancePDF() {
    var element = document.getElementById('printSection');
    var opt = {
        margin:       [8, 8, 8, 8],
        filename:     'Staff_Attendance_<?php echo date('Ymd_His'); ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, scrollY: 0 },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}

// Autocomplete Handlers
(function() {
    function setupStaffAutocomplete(cfg) {
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
                return m.name.toLowerCase().includes(q) || (m.role && m.role.toLowerCase().includes(q)) || (m.phone && m.phone.toLowerCase().includes(q));
            });

            if (filtered.length === 0) {
                resultsBox.innerHTML = '<div class="list-group-item text-muted py-2 text-center small"><i class="fas fa-user-slash me-1"></i>No matching staff</div>';
                resultsBox.style.display = 'block';
                return;
            }

            filtered.slice(0, 30).forEach(function(m) {
                var a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3 small';
                a.innerHTML = '<div><strong>' + escapeHtml(m.name) + '</strong><br><span class="text-muted"><i class="fas fa-briefcase me-1"></i>' + (m.role ? m.role.toUpperCase() : 'STAFF') + '</span></div><span class="badge bg-light text-dark border">Select</span>';
                
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    searchInput.value = m.name + (m.role ? ' (' + m.role + ')' : '');
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

    // Staff Check-in Autocomplete
    setupStaffAutocomplete({
        items: <?php echo json_encode($allStaff); ?>,
        searchInputId: 'staffInSearch',
        hiddenInputId: 'staffInId',
        resultsBoxId: 'staffInResults',
        clearBtnId: 'clearStaffIn'
    });

    // Staff Check-out Autocomplete
    setupStaffAutocomplete({
        items: <?php echo json_encode($checkedInStaff); ?>,
        searchInputId: 'staffOutSearch',
        hiddenInputId: 'staffOutId',
        resultsBoxId: 'staffOutResults',
        clearBtnId: 'clearStaffOut'
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
