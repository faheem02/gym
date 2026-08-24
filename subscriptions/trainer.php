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
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user me-1 text-muted"></i>Member *</label>
                <select name="member_id" class="form-select" required>
                    <option value="">-- Select Member --</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo ($_POST['member_id'] ?? '') == $m['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['name']) . ' (' . htmlspecialchars($m['phone']) . ')'; ?></option>
                    <?php endforeach; ?>
                </select>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
