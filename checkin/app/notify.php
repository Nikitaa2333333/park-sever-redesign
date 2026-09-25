<?php
declare(strict_types=1);

// Уведомление администратору в Telegram. Ошибка отправки не ломает подписание —
// договор уже сохранён, событие просто пишется в журнал.
function notify_admin(string $text): void
{
    $token = (string)cfg('telegram_bot_token');
    $chat = (string)cfg('telegram_chat_id');
    if ($token === '' || $chat === '') return;

    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chat, 'text' => $text, 'disable_web_page_preview' => 1]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 6,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false || $code !== 200) {
        audit(null, 'system', 'telegram_failed', ['http' => $code]);
    }
}
