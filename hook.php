<?php
// Приём уведомлений о записи через ботов BotHelp (Telegram, MAX, ВКонтакте)
// и отправка их в @Zayavki_na_vebinar_bot.
//
// Адрес для вебхука в BotHelp:
//   https://vizovaya-akademiya.ru/hook.php?ch=Telegram&time=14:00

header('Content-Type: application/json; charset=utf-8');

$TG_TOKEN = '8942163692:AAG0DVX3L4O97rfMsX-pYNdkbyH1m8wqJ0M';
$TG_CHAT  = '1630009226';

// --- откуда пришла запись -------------------------------------------------
$channel = isset($_GET['ch'])   ? trim($_GET['ch'])   : '—';
$time    = isset($_GET['time']) ? trim($_GET['time']) : '—';

// --- данные подписчика от BotHelp ----------------------------------------
$raw  = file_get_contents('php://input');
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
$ok = false;
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
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = ($code === 200);
}

echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);
