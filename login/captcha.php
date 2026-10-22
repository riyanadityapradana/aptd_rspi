<?php
session_start();
require_once __DIR__ . '/login_security.php';
aptd_login_no_cache();
header('X-Content-Type-Options: nosniff');

$id = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';
$challenge = isset($_SESSION['login_captchas'][$id]) ? $_SESSION['login_captchas'][$id] : null;
if (!$challenge || $challenge['expires'] <= time() || !extension_loaded('gd')) {
    http_response_code(404);
    exit;
}
$code = $challenge['code'];
session_write_close();

$image = imagecreatetruecolor(220, 70);
$background = imagecolorallocate($image, 238, 248, 255);
imagefill($image, 0, 0, $background);
for ($i = 0; $i < 12; $i++) {
    $noise = imagecolorallocate($image, random_int(140, 200), random_int(180, 220), random_int(210, 245));
    imageline($image, random_int(0, 219), random_int(0, 69), random_int(0, 219), random_int(0, 69), $noise);
}
// Perbesar karakter GD lalu putar tiap karakter; tidak bergantung font eksternal.
for ($i = 0; $i < 5; $i++) {
    $glyph = imagecreatetruecolor(28, 42);
    imagefill($glyph, 0, 0, $background);
    $small = imagecreatetruecolor(9, 15);
    imagefill($small, 0, 0, $background);
    $ink = imagecolorallocate($small, random_int(25, 50), random_int(65, 100), random_int(120, 165));
    imagestring($small, 5, 0, 0, $code[$i], $ink);
    imagecopyresized($glyph, $small, 0, 0, 0, 0, 28, 42, 9, 15);
    $rotated = imagerotate($glyph, random_int(-15, 15), $background);
    imagecopy($image, $rotated, 13 + $i * 40, random_int(8, 14), 0, 0, imagesx($rotated), imagesy($rotated));
    imagedestroy($small);
    imagedestroy($glyph);
    imagedestroy($rotated);
}
header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
