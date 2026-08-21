<?php
$activePage = 'staff';
$pageTitle = 'Staff';
include __DIR__ . '/../includes/header.php';

$search = trim($_GET['q'] ?? '');
$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Staff member added successfully.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Staff member updated successfully.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Staff member deleted.</div>';

$roleColors = [
    'receptionist' => 'text-bg-info',
    'trainer' => 'text-bg-primary',
    'helper' => 'text-bg-secondary',
    'cleaner' => 'text-bg-warning',
    'manager' => 'text-bg-success',
    'accountant' => 'text-bg-dark',
    'other' => 'text-bg-secondary'
];

$totalStaff = $pdo->query('SELECT COUNT(*) FROM staff')->fetchColumn();
$activeStaff = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'active'")->fetchColumn();
$totalSalary = $pdo->query("SELECT COALESCE(SUM(salary),0) FROM staff WHERE status = 'active'")->fetchColumn();
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #6c5ce7;">
            <div class="stat-icon" style="background:rgba(108,92,231,0.1);color:#6c5ce7;"><i class="fas fa-users"></i></div>
            <div><div class="stat-number"><?php echo $totalStaff; ?></div><div class="stat-label">Total Staff</div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #00b894;">
            <div class="stat-icon" style="background:rgba(0,184,148,0.1);color:#00b894;"><i class="fas fa-user-check"></i></div>
            <div><div class="stat-number"><?php echo $activeStaff; ?></div><div class="stat-label">Active Staff</div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #f7b731;">
            <div class="stat-icon" style="background:rgba(247,183,49,0.1);color:#f7b731;"><i class="fas fa-money-bill-wave"></i></div>
            <div><div class="stat-number">Rs.<?php echo number_format($totalSalary); ?></div><div class="stat-label">Total Salaries (Active)</div></div>
        </div>
    </div>
</div>

<div class="search-bar">
    <div class="row g-2 align-items-center">
        <div class="col-md-7 col-lg-8">
            <form method="GET" action="" class="d-flex">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control me-2" placeholder="Search by name, phone, role...">
                <button class="btn btn-dark" type="submit"><i class="fas fa-search me-1"></i>Search</button>
            </form>
        </div>
        <div class="col-md-5 col-lg-4 text-md-end">
            <a href="add.php" class="btn btn-warning fw-bold"><i class="fas fa-plus me-1"></i>Add Staff</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Salary</th>
                    <th>Join Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = 'SELECT * FROM staff';
                $params = [];
                if ($search !== '') {
                    $sql .= ' WHERE name LIKE ? OR phone LIKE ? OR role LIKE ? OR email LIKE ?';
                    $like = '%' . $search . '%';
                    $params = [$like, $like, $like, $like];
                }
                $sql .= ' ORDER BY id DESC';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $staff = $stmt->fetchAll();
                ?>
                <?php if (empty($staff)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-user-slash me-1"></i>No staff found.</td></tr>
                <?php endif; ?>
                <?php foreach ($staff as $s): ?>
                    <tr>
                        <td><?php echo $s['id']; ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($s['name']); ?></td>
                        <td><?php echo htmlspecialchars($s['phone']); ?></td>
                        <td><span class="badge <?php echo $roleColors[$s['role']] ?? 'text-bg-secondary'; ?>"><i class="fas fa-briefcase me-1"></i><?php echo ucfirst($s['role']); ?></span></td>
                        <td>Rs.<?php echo number_format($s['salary'], 0); ?></td>
                        <td><?php echo date('d M Y', strtotime($s['join_date'])); ?></td>
                        <td>
                            <span class="badge <?php echo $s['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo ucfirst($s['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="edit.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                            <a href="delete.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this staff member?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
