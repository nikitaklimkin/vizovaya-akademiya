<?php
// Приём уведомлений о записи через ботов BotHelp (Telegram, MAX, ВКонтакте)
// и отправка их в @Zayavki_na_vebinar_bot.
//
// Адрес для вебхука в BotHelp:
//   https://vizovaya-akademiya.ru/hook.php?ch=Telegram&time=14:00

header('Content-Type: application/json; charset=utf-8');

$TG_TOKEN = '8942163692:AAG0DVX3L4O97rfMsX-pYNdkbyH1m8wqJ0M';
$TG_CHAT  = '1630009226';

// --- журнал входящих запросов --------------------------------------------
$LOG = __DIR__ . '/hook_log.txt';
$rawBody = file_get_contents('php://input');
@file_put_contents(
    $LOG,
    date('d.m.Y H:i:s') . " | " . ($_SERVER['REQUEST_METHOD'] ?? '?')
      . " | QS: " . ($_SERVER['QUERY_STRING'] ?? '')
      . " | CT: " . ($_SERVER['CONTENT_TYPE'] ?? '')
      . " | BODY: " . mb_substr($rawBody, 0, 800)
      . " | POST: " . mb_substr(json_encode($_POST, JSON_UNESCAPED_UNICODE), 0, 400)
      . "\n",
    FILE_APPEND
);

if (isset($_GET['log'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo is_file($LOG) ? file_get_contents($LOG) : 'журнал пуст';
    exit;
}

// --- откуда пришла запись -------------------------------------------------
$channel = isset($_GET['ch'])   ? trim($_GET['ch'])   : '—';
$time    = isset($_GET['time']) ? trim($_GET['time']) : '—';

// --- данные подписчика от BotHelp ----------------------------------------
$raw  = $rawBody;
$data = json_decode($raw, true);
if (!is_array($data)) { $data = $_POST; }

function pick(array $d, array $keys) {
    foreach ($keys as $k) {
        if (isset($d[$k]) && $d[$k] !== '' && !is_array($d[$k])) {
            return trim((string)$d[$k]);
        }
    }
    // BotHelp может вложить данные в subscriber / contact
    foreach (['subscriber', 'contact', 'data'] as $wrap) {
        if (isset($d[$wrap]) && is_array($d[$wrap])) {
            foreach ($keys as $k) {
                if (isset($d[$wrap][$k]) && $d[$wrap][$k] !== '' && !is_array($d[$wrap][$k])) {
                    return trim((string)$d[$wrap][$k]);
                }
            }
        }
    }
    return '';
}

$first = pick($data, ['first_name', 'firstName', 'name']);
$last  = pick($data, ['last_name', 'lastName']);
$uname = pick($data, ['username', 'user_name', 'login']);
$phone = pick($data, ['phone', 'telephone']);
$email = pick($data, ['email', 'mail']);

$fio = trim($first . ' ' . $last);
if ($fio === '') { $fio = 'без имени'; }

// --- собираем сообщение ---------------------------------------------------
$lines   = [];
$lines[] = 'Новая запись через бота';
$lines[] = '';
$lines[] = 'Канал: ' . $channel;
$lines[] = 'Время: ' . $time;
$lines[] = 'Имя: ' . $fio;
if ($uname !== '') { $lines[] = 'Ник: @' . ltrim($uname, '@'); }
if ($phone !== '') { $lines[] = 'Телефон: ' . $phone; }
if ($email !== '') { $lines[] = 'Почта: ' . $email; }
$lines[] = date('d.m.Y H:i:s');

$text = implode("\n", $lines);

// --- отправка -------------------------------------------------------------
$ok      = false;
$code    = 0;
$err     = '';
$resp    = '';
if ($TG_TOKEN !== '' && $TG_CHAT !== '' && function_exists('curl_init')) {
    $ch = curl_init('https://api.telegram.org/bot' . $TG_TOKEN . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'chat_id'                  => $TG_CHAT,
            'text'                     => $text,
            'disable_web_page_preview' => true,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = ($code === 200);
} elseif (!function_exists('curl_init')) {
    $err = 'curl недоступен';
}

if (isset($_GET['debug'])) {
    echo json_encode([
        'ok'    => $ok,
        'code'  => $code,
        'error' => $err,
        'resp'  => mb_substr((string)$resp, 0, 300),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);
