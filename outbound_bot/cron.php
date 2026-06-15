<?php
/* =======================================================
 *  کرون: هشدار انقضا/اتمام حجم و حذف خودکار سرویس‌ها
 *  اجرا: php /path/to/cron.php  (هر ساعت توسط crontab)
 * ======================================================= */
require __DIR__ . '/config.php';
require __DIR__ . '/bot.php';
require __DIR__ . '/panel.php';
require __DIR__ . '/backup.php';

db_init();
run_expiry_cron();
run_backup_cron();
