<?php
// Generates the favicon / home-screen icon PNGs from code: php scripts/make-icons.php
// Mark: a white "F" on the accent blue, built from three bars (no fonts needed, so it renders the same everywhere).
$out = __DIR__.'/../public/icons';
@mkdir($out, 0775, true);

function render(int $size, bool $rounded): GdImage
{
    $s = 4;                                               // supersample for clean edges
    $n = $size * $s;
    $im = imagecreatetruecolor($n, $n);
    imagealphablending($im, false); imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    $blue = imagecolorallocate($im, 0, 113, 227);
    $white = imagecolorallocate($im, 255, 255, 255);

    if (! $rounded) {
        imagefilledrectangle($im, 0, 0, $n, $n, $blue);   // iOS applies its own corner mask
    } else {
        $r = (int) ($n * 0.22);
        imagefilledrectangle($im, $r, 0, $n - $r, $n, $blue);
        imagefilledrectangle($im, 0, $r, $n, $n - $r, $blue);
        foreach ([[$r, $r], [$n - $r, $r], [$r, $n - $r], [$n - $r, $n - $r]] as [$x, $y]) { imagefilledellipse($im, $x, $y, $r * 2, $r * 2, $blue); }
    }
    // the F: stem + top bar + middle bar, centred in the safe area
    $w = (int) ($n * 0.40); $h = (int) ($n * 0.50); $t = (int) ($n * 0.115);
    $x = (int) (($n - $w) / 2); $y = (int) (($n - $h) / 2);
    imagefilledrectangle($im, $x, $y, $x + $t, $y + $h, $white);
    imagefilledrectangle($im, $x, $y, $x + $w, $y + $t, $white);
    imagefilledrectangle($im, $x, $y + (int) ($h * 0.42), $x + (int) ($w * 0.8), $y + (int) ($h * 0.42) + $t, $white);

    $dst = imagecreatetruecolor($size, $size);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $size, $size, $n, $n);

    return $dst;
}

foreach (['apple-touch-icon.png' => [180, false], 'favicon-32.png' => [32, true]] as $file => [$size, $rounded]) {
    imagepng(render($size, $rounded), "$out/$file", 9);
    echo "wrote $file\n";
}
