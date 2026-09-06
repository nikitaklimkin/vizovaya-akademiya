<?php
// Отдаёт картинку с разрешением на кросс-доменное чтение.
// Нужен, чтобы BotHelp мог забрать изображение с сайта.
// Пример: /img.php?f=collage.jpg

$allowed = ['collage.jpg', 'laptop.jpg', 'banner.jpg'];

$f = isset($_GET['f']) ? basename($_GET['f']) : '';

if (!in_array($f, $allowed, true)) {
    http_response_code(404);
    exit('not found');
}

$path = __DIR__ . '/' . $f;

if (!is_file($path)) {
    http_response_code(404);
    exit('not found');
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=3600');

readfile($path);
