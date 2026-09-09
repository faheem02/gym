<?php
require __DIR__ . '/../config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid member ID.');
}

$stmt = $pdo->prepare('SELECT m.*, t.name AS trainer_name, t.phone AS trainer_phone FROM members m LEFT JOIN trainers t ON t.id = m.trainer_id WHERE m.id = ?');
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    http_response_code(404);
    exit('Member not found.');
}

$stmt = $pdo->prepare("SELECT s.*, p.name AS plan_name, p.price, p.duration_days FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.member_id = ? AND s.status = 'active' ORDER BY s.end_date DESC LIMIT 1");
$stmt->execute([$id]);
$activeSub = $stmt->fetch();

$daysLeft = $activeSub ? (int)((strtotime($activeSub['end_date']) - time()) / 86400) : 0;

$stmt2 = $pdo->prepare("SELECT * FROM member_payments WHERE member_id = ? ORDER BY id DESC LIMIT 1");
$stmt2->execute([$id]);
$lastPayment = $stmt2->fetch();

$stmtSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total_paid FROM member_payments WHERE member_id = ?");
$stmtSum->execute([$id]);
$totalPaid = (float)$stmtSum->fetch()['total_paid'];

$accessTypeLabels = [
    'gym' => ['label' => 'Gym Access', 'color' => '#2563eb', 'bg' => '#eff6ff'],
    'kids_play' => ['label' => 'Kids Play Area', 'color' => '#059669', 'bg' => '#ecfdf5'],
    'both' => ['label' => 'Gym + Kids Play Area', 'color' => '#d97706', 'bg' => '#fffbeb'],
];
$currAccess = $accessTypeLabels[$member['access_type'] ?? 'gym'] ?? $accessTypeLabels['gym'];

$regFee = (float)($member['registration_fee'] ?? 0);
$monthlyFee = (float)($member['monthly_fee'] ?? 0);
if ($monthlyFee == 0 && $activeSub) {
    $monthlyFee = (float)$activeSub['price'];
}
$kidsFee = (float)($member['kids_fee'] ?? 0);
$trainerFee = (float)($member['trainer_fee'] ?? 0);
$discount = (float)($member['discount'] ?? 0);
$totalPayable = max(0, ($regFee + $monthlyFee + $kidsFee + $trainerFee) - $discount);
$remainingDue = max(0, $totalPayable - $totalPaid);

$autoprint = !empty($_GET['autoprint']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Slip #<?php echo $member['id']; ?> - <?php echo htmlspecialchars(GYM_NAME); ?></title>
    <style>
        :root {
            --dark: #111827;
            --muted: #6b7280;
            --border: #d1d5db;
            --light: #f9fafb;
            --accent: #2563eb;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #e5e7eb;
            color: var(--dark);
            padding: 40px 20px;
            -webkit-font-smoothing: antialiased;
        }

        .page {
            max-width: 720px;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        /* ── Print Header ── */
        .print-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 32px;
            background: var(--dark);
            color: #fff;
        }
        .print-header .brand { display: flex; align-items: center; gap: 16px; }
        .print-header .brand img { height: 72px; width: auto; display: block; object-fit: contain; }
        .print-header .brand-text h1 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #fff;
        }
        .print-header .brand-text p {
            font-size: 11px;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
            line-height: 1.4;
        }
        .print-header .doc-meta {
            text-align: right;
        }
        .print-header .doc-meta .doc-type {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #60a5fa;
            margin-bottom: 4px;
        }
        .print-header .doc-meta .doc-id {
            font-size: 13px;
            font-weight: 600;
            color: #fff;
        }
        .print-header .doc-meta .doc-date {
            font-size: 11px;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
        }

        /* ── Body ── */
        .print-body { padding: 28px 32px; }

        .section-title {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--muted);
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 14px;
            margin-top: 24px;
        }
        .section-title:first-child { margin-top: 0; }

        /* ── Info Table ── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 8px 0;
            font-size: 13px;
            vertical-align: top;
            border-bottom: 1px solid #f3f4f6;
        }
        .info-table td:first-child {
            width: 160px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            padding-right: 16px;
        }
        .info-table td:last-child {
            font-weight: 500;
            color: var(--dark);
        }
        .info-table tr:last-child td { border-bottom: none; }

        .info-table .highlight-val {
            color: var(--accent);
            font-weight: 700;
        }

        /* ── Status ── */
        .status {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            padding: 3px 10px;
            border: 1px solid;
        }
        .status.active { color: #065f46; border-color: #065f46; background: #f0fdf4; }
        .status.expired { color: #991b1b; border-color: #991b1b; background: #fef2f2; }

        /* ── Plan Card ── */
        .plan-card {
            border: 1px solid var(--border);
            border-top: 3px solid var(--dark);
            margin-top: 14px;
        }
        .plan-card-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            background: var(--light);
            border-bottom: 1px solid var(--border);
        }
        .plan-card-head .plan-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--dark);
        }
        .plan-card-head .plan-price {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent);
        }
        .plan-card-body {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0;
        }
        .plan-card-body .cell {
            padding: 12px 20px;
            border-right: 1px solid #f3f4f6;
        }
        .plan-card-body .cell:last-child { border-right: none; }
        .plan-card-body .cell .cl {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            margin-bottom: 3px;
        }
        .plan-card-body .cell .cv {
            font-size: 13px;
            font-weight: 600;
            color: var(--dark);
        }

        /* ── Interests ── */
        .interest-tags { display: flex; flex-wrap: wrap; gap: 6px; }
        .interest-tags span {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 12px;
            border: 1px solid var(--border);
            color: var(--dark);
            background: var(--light);
        }

        /* ── Signature Block ── */
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 0;
        }
        .sig-block {
            width: 42%;
            text-align: center;
        }
        .sig-block .sig-line {
            border-top: 1px solid var(--dark);
            margin-top: 56px;
            padding-top: 8px;
        }
        .sig-block .sig-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .sig-block .sig-name {
            font-size: 10px;
            color: var(--muted);
            margin-top: 3px;
        }

        /* ── Footer ── */
        .print-footer {
            border-top: 3px solid var(--dark);
            padding: 14px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .print-footer .terms {
            font-size: 9px;
            color: var(--muted);
            line-height: 1.5;
            max-width: 500px;
        }
        .print-footer .ref {
            font-size: 9px;
            color: var(--muted);
            text-align: right;
            white-space: nowrap;
        }

        /* ── Actions (screen only) ── */
        .actions {
            text-align: center;
            margin-top: 24px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .btn-print {
            background: var(--dark);
            color: #fff;
            border: none;
            padding: 10px 28px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.3px;
            transition: background 0.15s;
        }
        .btn-print:hover { background: #000; }
        .btn-back {
            background: #fff;
            color: var(--dark);
            border: 1px solid var(--border);
            padding: 10px 28px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            letter-spacing: 0.3px;
            transition: background 0.15s;
        }
        .btn-back:hover { background: var(--light); }

        /* ── Thermal Slip (screen hidden, thermal print only) ── */
        .thermal-slip {
            display: none;
            width: 80mm;
            margin: 0 auto;
            background: #fff;
            color: #000;
            padding: 8px 10px;
            font-family: 'Courier New', 'Consolas', monospace, sans-serif;
            font-size: 11px;
            line-height: 1.4;
        }
        .thermal-slip .tsh { text-align: center; margin-bottom: 8px; }
        .thermal-slip .tsh img { max-height: 42px; max-width: 150px; object-fit: contain; margin-bottom: 4px; filter: brightness(0); -webkit-filter: brightness(0); }
        .thermal-slip .tsh .name { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .thermal-slip .tsh .info { font-size: 9.5px; line-height: 1.3; color: #111; }
        .thermal-slip .tsh .title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 5px; padding: 2px 0; border-top: 1px dashed #000; border-bottom: 1px dashed #000; }
        .thermal-slip .row { display: flex; justify-content: space-between; margin-bottom: 2px; }
        .thermal-slip .lbl { font-weight: 600; }
        .thermal-slip .ta-r { text-align: right; }
        .thermal-slip hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
        .thermal-slip .sec { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin: 8px 0 4px; border-bottom: 1px dashed #000; }
        .thermal-slip .tot { font-weight: 700; font-size: 12px; }
        .thermal-slip .ftr { text-align: center; margin-top: 12px; font-size: 9.5px; line-height: 1.45; }

        /* ── Print ── */
        @page slip-a4 { size: A4; margin: 12mm 14mm; }
        @page slip-thermal { size: 80mm auto; margin: 3mm 4mm; }
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .page { box-shadow: none; max-width: 100%; page: slip-a4; }
            .thermal-slip { display: none !important; }
            .actions { display: none !important; }

            body.thermal-print .page { display: none !important; }
            body.thermal-print .thermal-slip { display: block !important; }
            body.thermal-print .thermal-slip { page: slip-thermal; }
            body.thermal-print { padding: 0; margin: 0; background: #fff; }
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Header -->
        <div class="print-header">
            <div class="brand">
                <img src="<?php echo GYM_LOGO; ?>" alt="Logo" onerror="this.onerror=null; this.src='/gym/logo/The%20Compound%20Logo-01.png';">
                <div class="brand-text">
                    <h1><?php echo htmlspecialchars(GYM_NAME); ?></h1>
                    <p><?php echo htmlspecialchars(GYM_PHONE); ?> &middot; <?php echo htmlspecialchars(GYM_ADDRESS); ?></p>
                </div>
            </div>
            <div class="doc-meta">
                <div class="doc-type">Membership Slip</div>
                <div class="doc-id">Ref: GM-<?php echo str_pad($member['id'], 5, '0', STR_PAD_LEFT); ?></div>
                <div class="doc-date"><?php echo date('d M Y'); ?></div>
            </div>
        </div>

        <!-- Body -->
        <div class="print-body">

            <div class="section-title">Member Information</div>
            <table class="info-table">
                <tr>
                    <td>Member ID</td>
                    <td class="highlight-val">#<?php echo str_pad($member['id'], 5, '0', STR_PAD_LEFT); ?></td>
                </tr>
                <tr>
                    <td>Full Name</td>
                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                </tr>
                <?php if (!empty($member['guardian_name'])): ?>
                <tr>
                    <td>Parent / Guardian</td>
                    <td><?php echo htmlspecialchars($member['guardian_name']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Contact</td>
                    <td><?php echo htmlspecialchars($member['phone']); ?></td>
                </tr>
                <?php if (!empty($member['date_of_birth']) || !empty($member['age']) || !empty($member['gender'])): ?>
                <tr>
                    <td>Personal</td>
                    <td>
                        <?php
                        $parts = [];
                        if (!empty($member['gender'])) $parts[] = ucfirst(htmlspecialchars($member['gender']));
                        if (!empty($member['date_of_birth'])) $parts[] = date('d M Y', strtotime($member['date_of_birth']));
                        if (!empty($member['age'])) $parts[] = htmlspecialchars($member['age']) . ' years';
                        echo implode(' &middot; ', $parts);
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Join Date</td>
                    <td><?php echo date('d M Y', strtotime($member['join_date'])); ?></td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td><span class="status <?php echo $member['status']; ?>"><?php echo ucfirst($member['status']); ?></span></td>
                </tr>
                <tr>
                    <td>Access Type</td>
                    <td><span style="display:inline-block; padding:3px 10px; font-weight:700; font-size:11px; text-transform:uppercase; border:1px solid <?php echo $currAccess['color']; ?>; color:<?php echo $currAccess['color']; ?>; background:<?php echo $currAccess['bg']; ?>;"><?php echo htmlspecialchars($currAccess['label']); ?></span></td>
                </tr>
                <?php if (!empty($member['membership_type'])): ?>
                <tr>
                    <td>Membership Type</td>
                    <td><?php echo htmlspecialchars($member['membership_type']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($member['trainer_name'])): ?>
                <tr>
                    <td>Assigned Trainer</td>
                    <td><?php echo htmlspecialchars($member['trainer_name']); ?><?php if (!empty($member['trainer_phone'])): ?> (<?php echo htmlspecialchars($member['trainer_phone']); ?>)<?php endif; ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($member['area_of_interest'])): ?>
                <tr>
                    <td>Fitness Goals</td>
                    <td>
                        <div class="interest-tags">
                            <?php foreach (array_map('trim', explode(',', $member['area_of_interest'])) as $i): ?>
                                <span><i class="fas fa-check text-success me-1"></i><?php echo htmlspecialchars($i); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <?php if ($activeSub): ?>
            <div class="section-title">Subscription Details</div>
            <div class="plan-card">
                <div class="plan-card-head">
                    <div class="plan-name"><?php echo htmlspecialchars($activeSub['plan_name']); ?></div>
                    <div class="plan-price">Rs. <?php echo number_format($activeSub['price'], 0); ?></div>
                </div>
                <div class="plan-card-body">
                    <div class="cell">
                        <div class="cl">Start Date</div>
                        <div class="cv"><?php echo date('d M Y', strtotime($activeSub['start_date'])); ?></div>
                    </div>
                    <div class="cell">
                        <div class="cl">End Date</div>
                        <div class="cv"><?php echo date('d M Y', strtotime($activeSub['end_date'])); ?></div>
                    </div>
                    <div class="cell">
                        <div class="cl">Remaining</div>
                        <div class="cv"><?php echo $daysLeft > 0 ? $daysLeft . ' days' : '<span style="color:#991b1b;">Expired</span>'; ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="section-title">Fee &amp; Payment Summary</div>
            <table class="info-table" style="margin-bottom: 10px;">
                <tr>
                    <td>Registration Fee</td>
                    <td>Rs. <?php echo number_format($regFee, 2); ?></td>
                </tr>
                <?php if ($monthlyFee > 0): ?>
                <tr>
                    <td>Gym Monthly Fee</td>
                    <td>Rs. <?php echo number_format($monthlyFee, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($kidsFee > 0): ?>
                <tr>
                    <td>Kids Play Area Fee</td>
                    <td>Rs. <?php echo number_format($kidsFee, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($trainerFee > 0): ?>
                <tr>
                    <td>Trainer Fee</td>
                    <td>Rs. <?php echo number_format($trainerFee, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($discount > 0): ?>
                <tr>
                    <td>Discount</td>
                    <td style="color:#dc2626;">- Rs. <?php echo number_format($discount, 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr style="border-top:1px solid #111827; font-weight:700;">
                    <td>Total Payable</td>
                    <td style="font-size:14px; color:#111827;">Rs. <?php echo number_format($totalPayable, 2); ?></td>
                </tr>
                <tr>
                    <td>Amount Paid</td>
                    <td style="color:#059669; font-weight:700;">
                        Rs. <?php echo number_format($totalPaid, 2); ?>
                        <?php if ($lastPayment): ?>
                            <span style="font-size:11px; font-weight:normal; color:#6b7280;">(via <?php echo ucfirst(str_replace('_', ' ', $lastPayment['payment_method'])); ?> on <?php echo date('d M Y', strtotime($lastPayment['payment_date'])); ?>)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr style="border-top:1px solid #e5e7eb;">
                    <td>Remaining Balance</td>
                    <td>
                        <?php if ($remainingDue <= 0): ?>
                            <span style="color:#059669; font-weight:700;">Nil (Fully Paid)</span>
                        <?php else: ?>
                            <span style="color:#dc2626; font-weight:700;">Rs. <?php echo number_format($remainingDue, 2); ?> (Due)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <!-- Signatures -->
            <div class="signatures">
                <div class="sig-block">
                    <div class="sig-line"></div>
                    <div class="sig-label">Member Signature</div>
                    <div class="sig-name"><?php echo htmlspecialchars($member['name']); ?></div>
                </div>
                <div class="sig-block">
                    <div class="sig-line"></div>
                    <div class="sig-label">Authorized Signature</div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="print-footer">
            <div class="terms">
                This slip serves as proof of membership at <?php echo htmlspecialchars(GYM_NAME); ?>.
                Membership is non-transferable. For queries contact <?php echo htmlspecialchars(GYM_PHONE); ?>.
            </div>
            <div class="ref">
                Printed: <?php echo date('d M Y, h:i A'); ?>
            </div>
        </div>
    </div>

    <!-- Thermal Slip (used when "Print Slip" → thermal printer) -->
    <div class="thermal-slip" id="thermalSlip">
        <div class="tsh">
            <img src="<?php echo GYM_LOGO; ?>" alt="logo" onerror="this.onerror=null; this.src='/gym/logo/The%20Compound%20Logo-01.png';">
            <div class="name"><?php echo htmlspecialchars(GYM_NAME); ?></div>
            <div class="info"><?php echo htmlspecialchars(GYM_PHONE); ?><br><?php echo htmlspecialchars(GYM_ADDRESS); ?></div>
            <div class="title">Membership Slip</div>
        </div>

        <div class="row"><span class="lbl">Member #:</span><span class="ta-r">#<?php echo str_pad($member['id'], 5, '0', STR_PAD_LEFT); ?></span></div>
        <div class="row"><span class="lbl">Name:</span><span class="ta-r"><?php echo htmlspecialchars($member['name']); ?></span></div>
        <?php if (!empty($member['guardian_name'])): ?>
        <div class="row"><span class="lbl">Guardian:</span><span class="ta-r"><?php echo htmlspecialchars($member['guardian_name']); ?></span></div>
        <?php endif; ?>
        <div class="row"><span class="lbl">Phone:</span><span class="ta-r"><?php echo htmlspecialchars($member['phone']); ?></span></div>
        <?php if (!empty($member['date_of_birth']) || !empty($member['age']) || !empty($member['gender'])): ?>
        <?php
        $tParts = [];
        if (!empty($member['gender'])) $tParts[] = ucfirst(htmlspecialchars($member['gender']));
        if (!empty($member['date_of_birth'])) $tParts[] = date('d M Y', strtotime($member['date_of_birth']));
        if (!empty($member['age'])) $tParts[] = htmlspecialchars($member['age']) . ' yrs';
        ?>
        <div class="row"><span class="lbl">Personal:</span><span class="ta-r"><?php echo implode(' &middot; ', $tParts); ?></span></div>
        <?php endif; ?>
        <div class="row"><span class="lbl">Join Date:</span><span class="ta-r"><?php echo date('d M Y', strtotime($member['join_date'])); ?></span></div>
        <div class="row"><span class="lbl">Access:</span><span class="ta-r"><?php echo htmlspecialchars($currAccess['label']); ?></span></div>
        <div class="row"><span class="lbl">Status:</span><span class="ta-r"><?php echo ucfirst($member['status']); ?></span></div>

        <?php if ($activeSub): ?>
        <hr>
        <div class="sec">Diet Plan</div>
        <div class="row"><span class="lbl">Plan:</span><span class="ta-r"><?php echo htmlspecialchars($activeSub['plan_name']); ?></span></div>
        <div class="row"><span class="lbl">Start:</span><span class="ta-r"><?php echo date('d M Y', strtotime($activeSub['start_date'])); ?></span></div>
        <div class="row"><span class="lbl">End:</span><span class="ta-r"><?php echo date('d M Y', strtotime($activeSub['end_date'])); ?></span></div>
        <div class="row"><span class="lbl">Remaining:</span><span class="ta-r"><?php echo $daysLeft > 0 ? $daysLeft . ' days' : '<strong>Expired</strong>'; ?></span></div>
        <?php endif; ?>

        <hr>
        <div class="sec">Fees</div>
        <div class="row"><span>Registration:</span><span class="ta-r">Rs. <?php echo number_format($regFee, 0); ?></span></div>
        <?php if ($monthlyFee > 0): ?>
        <div class="row"><span>Monthly Fee:</span><span class="ta-r">Rs. <?php echo number_format($monthlyFee, 0); ?></span></div>
        <?php endif; ?>
        <?php if ($kidsFee > 0): ?>
        <div class="row"><span>Kids Fee:</span><span class="ta-r">Rs. <?php echo number_format($kidsFee, 0); ?></span></div>
        <?php endif; ?>
        <?php if ($trainerFee > 0): ?>
        <div class="row"><span>Trainer Fee:</span><span class="ta-r">Rs. <?php echo number_format($trainerFee, 0); ?></span></div>
        <?php endif; ?>
        <?php if ($discount > 0): ?>
        <div class="row"><span>Discount:</span><span class="ta-r">- Rs. <?php echo number_format($discount, 0); ?></span></div>
        <?php endif; ?>
        <div class="row tot"><span>TOTAL PAYABLE:</span><span class="ta-r">Rs. <?php echo number_format($totalPayable, 0); ?></span></div>
        <div class="row"><span>Amount Paid:</span><span class="ta-r">Rs. <?php echo number_format($totalPaid, 0); ?></span></div>
        <div class="row"><span>Balance:</span>
            <span class="ta-r">
                <?php if ($remainingDue <= 0): ?>
                    <strong>Fully Paid</strong>
                <?php else: ?>
                    <strong>Rs. <?php echo number_format($remainingDue, 0); ?> (Due)</strong>
                <?php endif; ?>
            </span>
        </div>

        <hr>
        <div class="ftr">
            Issued: <?php echo date('d M Y, h:i A'); ?><br>
            Member Signature: ______________________<br>
            <strong>Thank you for choosing <?php echo htmlspecialchars(GYM_NAME); ?>!</strong>
        </div>
    </div>

    <div class="actions">
        <button type="button" class="btn-print" onclick="thermalPrint();">
            <i class="fas fa-print me-1"></i> Print Slip
        </button>
        <button class="btn-print" style="background:#0284c7;" onclick="downloadSlipPDF();">
            <i class="fas fa-file-pdf me-1"></i> Download PDF
        </button>
    </div>

    <script src="/gym/assets/vendor/html2pdf/html2pdf.bundle.min.js"></script>
    <script>
    function downloadSlipPDF() {
        var element = document.querySelector('.page');
        if (!element) return;

        var btn = event && event.target ? event.target.closest('button') : null;
        var originalHTML = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Generating...';
        }

        var opt = {
            margin:       [8, 8, 8, 8],
            filename:     'Membership_Slip_<?php echo str_pad($member['id'], 5, '0', STR_PAD_LEFT); ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, letterRendering: true, scrollY: 0 },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save().then(function() {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }).catch(function(err) {
            console.error('PDF error:', err);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        });
    }

    function thermalPrint() {
        document.body.classList.add('thermal-print');
        window.print();
        setTimeout(function () {
            document.body.classList.remove('thermal-print');
        }, 500);
    }

    <?php if ($autoprint): ?>
    window.addEventListener('DOMContentLoaded', function () {
        setTimeout(function() {
            window.print();
        }, 400);
    });
    <?php endif; ?>
    </script>
</body>
</html>
