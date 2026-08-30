<?php
/**
 * Приём заявок с сайта vizovaya-akademiya.ru
 * 1) записывает заявку в файл на сервере
 * 2) отправляет её в Bizon365 (регистрация на вебинар)
 * 3) присылает копию на почту
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://www.vizovaya-akademiya.ru');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo '{"ok":false}'; exit; }

// ── настройки ──────────────────────────────────────────
$MAIL_TO   = 'vizovaya.akademiy@yandex.com';
$PAGE_14   = '209357:vizaakademiya';    // страница регистрации на 14:00
$PAGE_19   = '209357:vizaakademiya19';  // страница регистрации на 19:00
$LOG_FILE  = __DIR__ . '/zayavki.php';   // .php — чтобы файл нельзя было открыть в браузере
// ───────────────────────────────────────────────────────

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

$clean = function ($v, $max = 200) {
    $v = is_string($v) ? $v : '';
    $v = trim(strip_tags($v));
    $v = str_replace(["\r", "\n", "\0"], ' ', $v);
    return mb_substr($v, 0, $max);
};

$name  = $clean($data['name']  ?? '');
$phone = $clean($data['phone'] ?? '', 30);
$email = $clean($data['email'] ?? '', 120);
$slot  = $clean($data['slot']  ?? '', 10);
$utm   = $clean($data['utm']   ?? '', 300);
$page  = $clean($data['page']  ?? '', 100);

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'email']);
    exit;
}

$when   = date('d.m.Y H:i:s');
$ip     = $_SERVER['REMOTE_ADDR'] ?? '';
$bizPage = ($slot === '19:00') ? $PAGE_19 : $PAGE_14;

// ── 1. записываем заявку ───────────────────────────────
if (!file_exists($LOG_FILE)) {
    @file_put_contents($LOG_FILE, "<?php exit; // заявки с сайта\n", LOCK_EX);
}
$line = sprintf("%s | %s | %s | %s | %s | %s | %s\n",
    $when, $name, $phone, $email, $slot, $utm, $ip);
@file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);

// ── 2. отправляем в Bizon365 ───────────────────────────
// Повторяем обычную отправку формы регистрации — токен не нужен
$bizonOk = false;
$bizonMsg = '';
if (function_exists('curl_init')) {
    // дата ближайшего сеанса и выбранное время
    $day  = '2026-09-08';
    $time = ($slot === '19:00') ? '19:00' : '14:00';

    $fields = [
        'day'            => $day,
        'time'           => $time,
        'email'          => $email,
        'privacy-agree'  => 'on',
        'timeoffset'     => '-180',
        'name'           => $name,
        'phone'          => $phone,
    ];
    // прокидываем метки рекламы, если они пришли
    if ($utm !== '') {
        parse_str(str_replace('&amp;', '&', $utm), $utmArr);
        foreach ($utmArr as $k => $v) {
            if (strpos($k, 'utm_') === 0) $fields[$k] = $clean($v, 100);
        }
    }

    $url = 'https://start.bizon365.ru/subscriber/' . rawurlencode($bizPage) . '/register';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Referer: https://start.bizon365.ru/page/209357/' . explode(':', $bizPage)[1],
            'User-Agent: Mozilla/5.0 (compatible; vizovaya-akademiya)',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false) $bizonMsg = curl_error($ch);
    curl_close($ch);
    $bizonOk  = ($code >= 200 && $code < 400);
    $bizonMsg = $bizonMsg ?: ('HTTP ' . $code . ' ' . mb_substr(strip_tags((string)$resp), 0, 200));
} else {
    $bizonMsg = 'curl недоступен';
}

// ── 3. письмо с копией заявки ──────────────────────────
$subject = '=?UTF-8?B?' . base64_encode('Заявка с сайта — ' . ($name ?: $email)) . '?=';
$body =
    "Новая заявка с сайта\n\n" .
    "Имя:      $name\n" .
    "Телефон:  $phone\n" .
    "Почта:    $email\n" .
    "Время:    $slot\n" .
    "Источник: " . ($utm ?: '—') . "\n" .
    "Страница: " . ($page ?: '—') . "\n" .
    "Время:    $when\n\n" .
    "Bizon365: " . ($bizonOk ? 'принято' : 'ОШИБКА — ' . $bizonMsg) . "\n";

$headers  = "From: Сайт <noreply@vizovaya-akademiya.ru>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
@mail($MAIL_TO, $subject, $body, $headers);

echo json_encode(['ok' => true, 'bizon' => $bizonOk], JSON_UNESCAPED_UNICODE);
