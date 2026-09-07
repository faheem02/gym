<?php
$activePage = 'subscriptions';
$pageTitle = 'Renew Subscription';
include __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT s.id, s.member_id, s.plan_id, s.start_date, s.end_date, s.status,
            m.name AS member_name, m.phone, p.name AS plan_name, p.duration_days, p.price
     FROM subscriptions s
     JOIN members m ON m.id = s.member_id
     JOIN plans p ON p.id = s.plan_id
     WHERE s.id = ?"
);
$stmt->execute([$id]);
$sub = $stmt->fetch();

if (!$sub) {
    echo '<div class="alert alert-warning">Subscription not found. <a href="index.php">Back</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$today = date('Y-m-d');
$daysLeft = (int)ceil((strtotime($sub['end_date']) - strtotime($today)) / 86400);
$isExpired = $sub['status'] === 'expired' || $daysLeft < 0;
$newEnd = date('Y-m-d', strtotime($sub['end_date'] . ' + ' . $sub['duration_days'] . ' days'));
$error = '';

// Recent payments for this member (to show if pending dues)
$payments = $pdo->prepare("SELECT payment_date, amount, payment_for FROM member_payments WHERE member_id = ? ORDER BY payment_date DESC LIMIT 5");
$payments->execute([$sub['member_id']]);
$recentPayments = $payments->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = '';

    // Payment (optional) details
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $payment_for = trim($_POST['payment_for'] ?? '');
    $payment_notes = trim($_POST['payment_notes'] ?? '');
    $pay_date = trim($_POST['payment_date'] ?? date('Y-m-d'));

    if ($payment_for === '') $payment_for = 'Plan Renewal';
    if (!in_array($payment_method, ['cash', 'bank_transfer'])) $payment_method = 'cash';

    if ($amount < 0) {
        $error = 'Amount cannot be negative.';
    }

    if ($error === '') {
        $pdo->beginTransaction();
        try {
            $new_end = date('Y-m-d', strtotime($sub['end_date'] . ' + ' . $sub['duration_days'] . ' days'));
            $stmt = $pdo->prepare("UPDATE subscriptions SET end_date = ?, status = 'active' WHERE id = ?");
            $stmt->execute([$new_end, $id]);

            // Record payment if amount entered (money credited to customer's account)
            if ($amount > 0) {
                $note = ($payment_notes !== '') ? $payment_notes : ($sub['plan_name'] . ' renewal (' . date('d M Y', strtotime($sub['end_date'])) . ' - ' . date('d M Y', strtotime($newEnd)) . ')');
                $stmtPay = $pdo->prepare('INSERT INTO member_payments (member_id, amount, payment_method, payment_for, notes, payment_date) VALUES (?, ?, ?, ?, ?, ?)');
                $stmtPay->execute([$sub['member_id'], $amount, $payment_method, $payment_for, $note, $pay_date]);
            }

            $pdo->commit();
            header('Location: /gym/subscriptions/index.php?msg=renewed');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error renewing: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4 d-flex gap-2">
    <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="card form-card" style="max-width: 720px;">
    <div class="card-body p-4">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="stat-icon" style="width:56px;height:56px;font-size:1.4rem;background:linear-gradient(135deg,#28a745,#20c997);color:#fff;box-shadow:0 4px 14px rgba(40,167,69,0.3);"><i class="fas fa-sync-alt"></i></div>
            <div>
                <h5 class="fw-bold mb-1">Renew Subscription</h5>
                <div class="fw-semibold"><?php echo htmlspecialchars($sub['member_name']); ?> <small class="text-muted">(<?php echo htmlspecialchars($sub['phone']); ?>)</small></div>
            </div>
            <span class="badge <?php echo $isExpired ? 'badge-expired' : 'badge-active'; ?> ms-auto fs-6">
                <?php echo $isExpired ? 'Expired' : 'Active'; ?>
            </span>
        </div>

        <?php if ($isExpired): ?>
            <div class="alert alert-danger py-2">
                <i class="fas fa-exclamation-triangle me-1"></i>
                <strong>Subscription expired <?php echo abs($daysLeft); ?> days ago.</strong> Renew it to restore access for this member.
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted text-uppercase fw-bold mb-2"><i class="fas fa-utensils me-1"></i>Diet Plan</div>
                    <div class="fw-bold fs-5"><?php echo htmlspecialchars($sub['plan_name']); ?></div>
                    <div class="text-muted small"><?php echo $sub['duration_days']; ?> days &middot; Rs.<?php echo number_format($sub['price'], 0); ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted text-uppercase fw-bold mb-2"><i class="fas fa-calendar me-1"></i>Current Period</div>
                    <div><?php echo date('d M Y', strtotime($sub['start_date'])); ?> &rarr; <?php echo date('d M Y', strtotime($sub['end_date'])); ?></div>
                    <div class="mt-1">
                        <?php if ($isExpired): ?>
                            <span class="badge text-bg-danger"><i class="fas fa-exclamation-circle me-1"></i><?php echo abs($daysLeft); ?> days overdue</span>
                        <?php else: ?>
                            <span class="badge <?php echo $daysLeft <= 7 ? 'text-bg-warning' : 'text-bg-success'; ?>"><i class="fas fa-clock me-1"></i><?php echo $daysLeft; ?> days left</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-success d-flex align-items-center gap-2 mt-3 mb-0">
            <i class="fas fa-calendar-check fa-lg me-1"></i>
            <div>
                <div class="small text-muted">After renewal, the new subscription period will be:</div>
                <div class="fw-bold text-success fs-6">
                    <?php echo date('d M Y', strtotime($sub['end_date'])); ?> &rarr; <?php echo date('d M Y', strtotime($newEnd)); ?>
                </div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 mt-3 mb-0"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="mt-4">
            <div class="section-label mb-3">
                <h6 class="fw-bold text-muted"><i class="fas fa-money-bill-wave text-success me-1"></i> Payment (Optional)</h6>
                <small class="text-muted">Enter amount paid during renewal to record it on the member's account (ledger). Leave blank if no payment now.</small>
                <hr class="mt-1">
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold"><i class="fas fa-rupee-sign me-1 text-muted"></i>Amount Received (Rs.)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-success text-white">Rs.</span>
                        <input type="number" step="0.01" min="0" name="amount" id="renewAmount" class="form-control form-control-lg fw-bold" placeholder="0" value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
                    </div>
                    <small class="text-muted">Suggested: Rs.<?php echo number_format($sub['price'], 0); ?> (plan price)</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold"><i class="fas fa-calendar me-1 text-muted"></i>Payment Date</label>
                    <input type="date" name="payment_date" class="form-control form-control-lg" value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold"><i class="fas fa-credit-card me-1 text-muted"></i>Payment Method</label>
                    <select name="payment_method" class="form-select form-select-lg">
                        <option value="cash" <?php echo ($_POST['payment_method'] ?? 'cash') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="bank_transfer" <?php echo ($_POST['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold"><i class="fas fa-list me-1 text-muted"></i>Payment For</label>
                    <select name="payment_for" class="form-select form-select-lg">
                        <?php foreach (['Plan Renewal', 'Membership Fee', 'Registration Fee', 'Personal Training', 'Other'] as $pf): ?>
                            <option value="<?php echo $pf; ?>" <?php echo ($_POST['payment_for'] ?? 'Plan Renewal') === $pf ? 'selected' : ''; ?>><?php echo $pf; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold"><i class="fas fa-sticky-note me-1 text-muted"></i>Notes <small class="text-muted">(optional)</small></label>
                    <input type="text" name="payment_notes" class="form-control" placeholder="e.g. Paid in full, cheque no. etc." value="<?php echo htmlspecialchars($_POST['payment_notes'] ?? ''); ?>">
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-success btn-lg fw-bold px-4"><i class="fas fa-sync-alt me-1"></i>Renew &amp; Record</button>
                <a href="index.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($recentPayments)): ?>
<div class="card mt-4" style="max-width: 720px;">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="fas fa-money-bill-wave text-success me-2"></i>Recent Payments</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Date</th><th>For</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($recentPayments as $p): ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($p['payment_for'] ?? '-'); ?></td>
                            <td class="text-end fw-bold text-success">Rs.<?php echo number_format($p['amount'], 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
