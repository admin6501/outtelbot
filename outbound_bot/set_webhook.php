<?php
/* =======================================================
 *  نصب و تنظیم وبهوک — این فایل را یک‌بار در مرورگر باز کنید
 *  مثال: https://yourdomain.com/outbound_bot/set_webhook.php
 * ======================================================= */
require __DIR__ . '/config.php';
require __DIR__ . '/bot.php';

header('Content-Type: text/html; charset=utf-8');

if (BOT_TOKEN === 'PUT_YOUR_BOT_TOKEN_HERE') {
    exit('<h3 style="color:red">⛔ ابتدا BOT_TOKEN را در فایل config.php تنظیم کنید.</h3>');
}

db_init();

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$dir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir . '/index.php';

$action = $_GET['action'] ?? 'set';

if ($action === 'delete') {
    $r = tg('deleteWebhook', ['drop_pending_updates' => true]);
} else {
    $r = tg('setWebhook', ['url' => $url, 'drop_pending_updates' => true, 'max_connections' => 40]);
}
$me = tg('getMe');
if (isset($me['result']['username'])) set_setting('bot_username', $me['result']['username']);

echo '<div style="font-family:sans-serif;direction:ltr;max-width:600px;margin:40px auto;padding:20px;border:1px solid #ddd;border-radius:10px">';
echo '<h2>🤖 Outbound Sales Bot</h2>';
echo '<p><b>Bot:</b> @' . ($me['result']['username'] ?? '—') . '</p>';
echo '<p><b>Webhook URL:</b><br><code>' . htmlspecialchars($url) . '</code></p>';
echo '<p><b>Result:</b> ' . ($r['ok'] ? '✅ Success' : '❌ ' . ($r['description'] ?? 'error')) . '</p>';
echo '<hr><p>✅ Database ready. Go to your bot and send /start , then /admin for the admin panel.</p>';
echo '<p style="color:#888">To remove webhook: add <code>?action=delete</code> to the URL.</p>';
echo '</div>';
