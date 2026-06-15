<?php
/* =======================================================
 *  نقطه ورود وبهوک تلگرام
 * ======================================================= */
require __DIR__ . '/config.php';
require __DIR__ . '/bot.php';
require __DIR__ . '/panel.php';
require __DIR__ . '/handlers_user.php';
require __DIR__ . '/handlers_admin.php';
require __DIR__ . '/backup.php';

db_init();

$content = file_get_contents('php://input');
$update = json_decode($content, true);
if (!$update) { http_response_code(200); exit; }

try {
    if (isset($update['message'])) {
        $msg = $update['message'];
        $from = $msg['from'] ?? null;
        if (!$from) exit;
        $tg = $from['id'];
        $u = ensure_user($from);
        if ($u['is_blocked']) exit;
        $text = $msg['text'] ?? '';
        $step = $u['step'];

        // دریافت فایل (سند) — برای بازگردانی دیتابیس توسط ادمین
        if (isset($msg['document'])) {
            if (is_admin($tg) && $step === 'admin_restore_wait') {
                admin_handle_restore_document($msg, $u);
            }
            http_response_code(200);
            exit;
        }

        if (is_admin($tg) && ($text === '/admin' || strpos($step, 'admin_') === 0 || is_admin_menu_text($text))) {
            admin_handle_message($msg, $u);
        } else {
            user_handle_message($msg, $u);
        }
    } elseif (isset($update['callback_query'])) {
        $cb = $update['callback_query'];
        $tg = $cb['from']['id'];
        $u = ensure_user($cb['from']);
        if ($u['is_blocked']) { answer($cb['id']); exit; }
        $data = $cb['data'] ?? '';
        if (strpos($data, 'a_') === 0 && is_admin($tg)) {
            admin_handle_callback($cb, $u);
        } else {
            user_handle_callback($cb, $u);
        }
    }
} catch (Exception $e) {
    error_log('BOT ERROR: ' . $e->getMessage());
}
http_response_code(200);
