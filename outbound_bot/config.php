<?php
/* =======================================================
 *  تنظیمات اصلی ربات فروش اوت‌باند
 *  این مقادیر را قبل از اجرا حتماً ویرایش کنید.
 * ======================================================= */

// توکن ربات را از @BotFather بگیرید و اینجا قرار دهید
define('BOT_TOKEN', 'PUT_YOUR_BOT_TOKEN_HERE');

// آیدی عددی ادمین‌ها (می‌توانید چند ادمین وارد کنید)
// آیدی عددی خود را از @userinfobot بگیرید
$ADMIN_IDS = [123456789];

/* --- این بخش را تغییر ندهید --- */
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('DB_PATH', __DIR__ . '/data/bot.db');
date_default_timezone_set('Asia/Tehran');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
