<?php
$srcPath = 'c:/xampp/htdocs/gym/logo/The Compound Logo-01.png';
$im = imagecreatefrompng($srcPath);
imagealphablending($im, false);
imagesavealpha($im, true);
$w = imagesx($im);
$h = imagesy($im);

$out = imagecreatetruecolor($w, $h);
imagealphablending($out, false);
imagesavealpha($out, true);
$transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
imagefilledrectangle($out, 0, 0, $w, $h, $transparent);

// Find bounding box to auto-crop excess whitespace as well!
$minX = $w; $maxX = 0; $minY = $h; $maxY = 0;

for ($x = 0; $x < $w; $x++) {
    for ($y = 0; $y < $h; $y++) {
        $rgba = imagecolorat($im, $x, $y);
        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;
        $a = ($rgba >> 24) & 0x7F;

        // Is neon yellow / artwork
        if ($a < 100 && !($r > 240 && $g > 240 && $b > 240)) {
            $col = imagecolorallocatealpha($out, 17, 24, 39, $a);
            imagesetpixel($out, $x, $y, $col);
            if ($x < $minX) $minX = $x;
            if ($x > $maxX) $maxX = $x;
            if ($y < $minY) $minY = $y;
            if ($y > $maxY) $maxY = $y;
        }
    }
}

// Crop to artwork bounding box with small padding
$pad = 20;
$cropX = max(0, $minX - $pad);
$cropY = max(0, $minY - $pad);
$cropW = min($w - $cropX, ($maxX - $minX) + ($pad * 2));
$cropH = min($h - $cropY, ($maxY - $minY) + ($pad * 2));

$cropped = imagecrop($out, ['x' => $cropX, 'y' => $cropY, 'width' => $cropW, 'height' => $cropH]);
imagealphablending($cropped, false);
imagesavealpha($cropped, true);

imagepng($cropped, 'c:/xampp/htdocs/gym/logo/logo_dark.png');
echo "Dark logo created. Bounding box: ${cropW}x${cropH}\n";
