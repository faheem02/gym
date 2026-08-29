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
    <div class="print-logo" style="margin-bottom: 6px;">
        <img src="<?php echo GYM_LOGO; ?>" alt="<?php echo htmlspecialchars(GYM_NAME); ?>" style="height: 60px; width: auto; display: inline-block; object-fit: contain; filter: brightness(0); -webkit-filter: brightness(0);" onerror="this.onerror=null; this.src='/gym/logo/The%20Compound%20Logo-01.png';">
    </div>
    <div class="print-gym-name"><?php echo htmlspecialchars(GYM_NAME); ?></div>
    <div class="print-gym-contact">
        <strong>Cell:</strong> <?php echo htmlspecialchars(GYM_PHONE); ?>
    </div>
    <div class="print-gym-address"><?php echo htmlspecialchars(GYM_ADDRESS); ?></div>
    <div class="print-gym-sub"><?php echo htmlspecialchars($printReportTitle); ?></div>
    <?php if (!empty($printMeta)): ?>
        <div class="print-gym-meta"><?php echo $printMeta; ?></div>
    <?php endif; ?>
</div>
