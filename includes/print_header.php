<?php
/**
 * Reusable Print Header Component
 *
 * Variables:
 *   $printReportTitle (string) - Specific report title (defaults to $pageTitle)
 *   $printMeta (string)        - Metadata string/HTML (dates, member name, etc.)
 */
$printReportTitle = $printReportTitle ?? ($pageTitle ?? 'Report');
$printMeta = $printMeta ?? '';
?>
<!-- Letterhead -->
<div class="print-header">
    <div class="print-logo"><img src="<?php echo GYM_LOGO; ?>" alt="<?php echo htmlspecialchars(GYM_NAME); ?>" style="height:80px;width:auto;"></div>
    <div class="print-gym-name"><?php echo htmlspecialchars(GYM_NAME); ?></div>
    <div class="print-gym-contact">
        <span><strong>Customer / Prop:</strong> <?php echo htmlspecialchars(GYM_OWNER); ?></span>
        <span style="margin: 0 8px;">|</span>
        <span><strong>Cell:</strong> <?php echo htmlspecialchars(GYM_PHONE); ?></span>
    </div>
    <div class="print-gym-address"><?php echo htmlspecialchars(GYM_ADDRESS); ?></div>
    <div class="print-gym-sub"><?php echo htmlspecialchars($printReportTitle); ?></div>
    <?php if (!empty($printMeta)): ?>
        <div class="print-gym-meta"><?php echo $printMeta; ?></div>
    <?php endif; ?>
</div>
