<?php
// Generates the favicon / home-screen icon PNGs from code: php scripts/make-icons.php
// Brand mark: the khatam - an eight-pointed star made of two overlapping squares - in gold on deep emerald.
$out = __DIR__.'/../public/icons';
@mkdir($out, 0775, true);

function star(float $cx, float $cy, float $r): array   // 16 vertices: outer points at radius r, inner notches at r * 0.7654
{
    $pts = [];
    $inner = $r * 0.7654;                                 // 8-point {8/2} star where the two squares cross
    for ($i = 0; $i < 16; $i++) {
        $a = M_PI / 8 * $i - M_PI / 2;
        $rad = $i % 2 === 0 ? $r : $inner;
        $pts[] = $cx + cos($a) * $rad;
        $pts[] = $cy + sin($a) * $rad;
    }

    return $pts;
}

function render(int $size, bool $maskable, bool $rounded): GdImage
{
    $s = 4;                                               // supersample for clean edges
    $n = $size * $s;
    $im = imagecreatetruecolor($n, $n);
    imagealphablending($im, false); imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    $emerald = imagecolorallocate($im, 10, 74, 60);
    $emerald2 = imagecolorallocate($im, 15, 107, 87);
    $gold = imagecolorallocate($im, 212, 162, 76);
    $goldDeep = imagecolorallocate($im, 184, 134, 47);

    if ($maskable || ! $rounded) {
        imagefilledrectangle($im, 0, 0, $n, $n, $emerald);
    } else {
        $r = (int) ($n * 0.22);
        imagefilledrectangle($im, $r, 0, $n - $r, $n, $emerald);
        imagefilledrectangle($im, 0, $r, $n, $n - $r, $emerald);
        foreach ([[$r, $r], [$n - $r, $r], [$r, $n - $r], [$n - $r, $n - $r]] as [$x, $y]) { imagefilledellipse($im, $x, $y, $r * 2, $r * 2, $emerald); }
    }
    $c = $n / 2;
    $scale = $maskable ? 0.30 : 0.36;                     // maskable keeps the mark inside the safe zone
    imagefilledellipse($im, (int) $c, (int) $c, (int) ($n * $scale * 2.35), (int) ($n * $scale * 2.35), $emerald2);
    imagefilledpolygon($im, star($c, $c, $n * $scale), $gold);
    imagefilledpolygon($im, star($c, $c, $n * $scale * 0.58), $emerald);
    imagefilledellipse($im, (int) $c, (int) $c, (int) ($n * $scale * 0.34), (int) ($n * $scale * 0.34), $goldDeep);

    $dst = imagecreatetruecolor($size, $size);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $size, $size, $n, $n);

    return $dst;
}

foreach ([
    'apple-touch-icon.png' => [180, false, false], 'favicon-32.png' => [32, false, true],
] as $file => [$size, $maskable, $rounded]) {
    imagepng(render($size, $maskable, $rounded), "$out/$file", 9);
    echo "wrote $file\n";
}
