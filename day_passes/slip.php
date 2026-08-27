<?php
require __DIR__ . '/../config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid pass ID.');
}

$stmt = $pdo->prepare(
    "SELECT dp.*, m.name AS member_name, m.phone AS member_phone
     FROM day_passes dp
     LEFT JOIN members m ON m.id = dp.member_id
     WHERE dp.id = ?"
);
$stmt->execute([$id]);
$pass = $stmt->fetch();

if (!$pass) {
    http_response_code(404);
    exit('Day pass not found.');
}

$duration = '';
if ($pass['check_out_time']) {
    $mins = (strtotime($pass['check_out_time']) - strtotime($pass['check_in_time'])) / 60;
    $hrs = floor($mins / 60);
    $mins = $mins % 60;
    $duration = $hrs . 'h ' . $mins . 'm';
}

$typeLabels = [
    'gym' => 'Gym Access',
    'kids_play' => 'Kids Play Area',
    'both' => 'Gym + Kids Play',
];
$typeLabel = $typeLabels[$pass['pass_type']] ?? 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Day Pass #<?php echo $pass['id']; ?> - <?php echo htmlspecialchars(GYM_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --dark: #111827;
            --muted: #6b7280;
            --border: #d1d5db;
            --light: #f9fafb;
            --accent: #2563eb;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #e5e7eb; color: var(--dark); padding: 40px 20px; -webkit-font-smoothing: antialiased; }

        .page { max-width: 720px; margin: 0 auto; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

        .print-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 32px; background: var(--dark); color: #fff;
        }
        .print-header .brand { display: flex; align-items: center; gap: 16px; }
        .print-header .brand img { height: 72px; width: auto; display: block; object-fit: contain; }
        .print-header .brand-text h1 { font-size: 20px; font-weight: 700; letter-spacing: -0.5px; color: #fff; }
        .print-header .brand-text p { font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 2px; line-height: 1.4; }
        .print-header .doc-meta { text-align: right; }
        .print-header .doc-meta .doc-type { font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #60a5fa; margin-bottom: 4px; }
        .print-header .doc-meta .doc-id { font-size: 13px; font-weight: 600; color: #fff; }
        .print-header .doc-meta .doc-date { font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 2px; }

        .print-body { padding: 28px 32px; }

        .section-title {
            font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
            color: var(--muted); padding-bottom: 8px; border-bottom: 1px solid var(--border);
            margin-bottom: 14px; margin-top: 24px;
        }
        .section-title:first-child { margin-top: 0; }

        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 8px 0; font-size: 13px; vertical-align: top; border-bottom: 1px solid #f3f4f6; }
        .info-table td:first-child { width: 160px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); padding-right: 16px; }
        .info-table td:last-child { font-weight: 500; color: var(--dark); }
        .info-table tr:last-child td { border-bottom: none; }
        .info-table .highlight-val { color: var(--accent); font-weight: 700; }

        .status { display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; padding: 3px 10px; border: 1px solid; }
        .status.inside { color: #065f46; border-color: #065f46; background: #f0fdf4; }
        .status.checked-out { color: #6b7280; border-color: #d1d5db; background: #f9fafb; }

        .duration-box {
            text-align: center; padding: 18px; margin: 14px 0;
            border: 1px solid var(--border); border-top: 3px solid var(--dark);
        }
        .duration-box .dur-label { font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); }
        .duration-box .dur-value { font-size: 28px; font-weight: 700; color: var(--dark); margin-top: 4px; }
        .duration-box .dur-range { font-size: 12px; color: var(--muted); margin-top: 4px; }

        .amount-box {
            text-align: center; padding: 16px; margin: 14px 0;
            border: 1px solid #bbf7d0; background: #f0fdf4;
        }
        .amount-box .amt-label { font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #065f46; }
        .amount-box .amt-value { font-size: 28px; font-weight: 700; color: #065f46; margin-top: 4px; }

        .print-footer {
            border-top: 3px solid var(--dark); padding: 14px 32px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .print-footer .terms { font-size: 9px; color: var(--muted); line-height: 1.5; max-width: 500px; }
        .print-footer .ref { font-size: 9px; color: var(--muted); text-align: right; white-space: nowrap; }

        .actions { text-align: center; margin-top: 24px; display: flex; justify-content: center; gap: 10px; }
        .btn-print { background: var(--dark); color: #fff; border: none; padding: 10px 28px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-print:hover { background: #000; }
        .btn-back { background: #fff; color: var(--dark); border: 1px solid var(--border); padding: 10px 28px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-back:hover { background: var(--light); }

        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .page { box-shadow: none; max-width: 100%; }
            .actions { display: none !important; }
            @page { size: A4; margin: 12mm 14mm; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="print-header">
            <div class="brand">
                <img src="<?php echo GYM_LOGO; ?>" alt="Logo">
                <div class="brand-text">
                    <h1><?php echo htmlspecialchars(GYM_NAME); ?></h1>
                    <p><?php echo htmlspecialchars(GYM_OWNER); ?><br>
                    <?php echo htmlspecialchars(GYM_PHONE); ?> &middot; <?php echo htmlspecialchars(GYM_ADDRESS); ?></p>
                </div>
            </div>
            <div class="doc-meta">
                <div class="doc-type">Day Pass</div>
                <div class="doc-id">Ref: DP-<?php echo str_pad($pass['id'], 5, '0', STR_PAD_LEFT); ?></div>
                <div class="doc-date"><?php echo date('d M Y', strtotime($pass['pass_date'])); ?></div>
            </div>
        </div>

        <div class="print-body">
            <div class="section-title">Visitor Information</div>
            <table class="info-table">
                <tr>
                    <td>Pass ID</td>
                    <td class="highlight-val">#<?php echo str_pad($pass['id'], 5, '0', STR_PAD_LEFT); ?></td>
                </tr>
                <tr>
                    <td>Visitor Name</td>
                    <td><?php echo htmlspecialchars($pass['visitor_name']); ?></td>
                </tr>
                <tr>
                    <td>Phone</td>
                    <td><?php echo htmlspecialchars($pass['phone'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td>Pass Type</td>
                    <td><?php echo $typeLabel; ?></td>
                </tr>
                <?php if ($pass['member_name']): ?>
                <tr>
                    <td>Related Member</td>
                    <td><?php echo htmlspecialchars($pass['member_name']); ?><?php if ($pass['member_phone']): ?> (<?php echo htmlspecialchars($pass['member_phone']); ?>)<?php endif; ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Pass Date</td>
                    <td><?php echo date('l, d M Y', strtotime($pass['pass_date'])); ?></td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td>
                        <?php if ($pass['check_out_time']): ?>
                            <span class="status checked-out">Checked Out</span>
                        <?php else: ?>
                            <span class="status inside">Currently Inside</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <div class="section-title">Timing</div>
            <table class="info-table">
                <tr>
                    <td>Check In</td>
                    <td><?php echo date('h:i A', strtotime($pass['check_in_time'])); ?></td>
                </tr>
                <tr>
                    <td>Check Out</td>
                    <td><?php echo $pass['check_out_time'] ? date('h:i A', strtotime($pass['check_out_time'])) : '<span style="color:var(--muted);">— (Inside)</span>'; ?></td>
                </tr>
                <?php if ($duration): ?>
                <tr>
                    <td>Duration</td>
                    <td style="font-weight:700;"><?php echo $duration; ?></td>
                </tr>
                <?php endif; ?>
            </table>

            <?php if (!empty($pass['notes'])): ?>
            <div class="section-title">Notes</div>
            <p style="font-size:13px;color:var(--muted);line-height:1.6;"><?php echo nl2br(htmlspecialchars($pass['notes'])); ?></p>
            <?php endif; ?>

            <div class="amount-box">
                <div class="amt-label">Amount Paid</div>
                <div class="amt-value">Rs. <?php echo number_format($pass['amount'], 0); ?></div>
            </div>
        </div>

        <div class="print-footer">
            <div class="terms">
                This day pass is non-transferable and valid only for the date printed above.
                For queries contact <?php echo htmlspecialchars(GYM_PHONE); ?>.
            </div>
            <div class="ref">
                Printed: <?php echo date('d M Y, h:i A'); ?>
            </div>
        </div>
    </div>

    <div class="actions">
        <button class="btn-print" onclick="window.print();">
            <i class="fas fa-print me-1"></i> Print Pass
        </button>
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
</body>
</html>
