<?php
// router.php : dipakai saat menjalankan di komputer sendiri (php -S) atau Docker.
// Menyajikan file statis dari /public, sisanya diserahkan ke api/index.php.
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$f = realpath(__DIR__ . '/public' . $p);
if ($p !== '/' && $f && str_starts_with($f, realpath(__DIR__ . '/public')) && is_file($f)) {
    $types = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'ico' => 'image/x-icon'];
    header('Content-Type: ' . ($types[pathinfo($f, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    readfile($f); return true;
}
require __DIR__ . '/api/index.php';
