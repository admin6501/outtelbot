<?php
/* =======================================================
 *  سیستم بکاپ‌گیری و بازگردانی دیتابیس (SQLite → تلگرام)
 *  - بکاپ دستی و خودکار (کرون) به پیوی ادمین‌ها
 *  - بازگردانی از فایل آپلودی با اعتبارسنجی و تایید دو‌مرحله‌ای
 * ======================================================= */

/* تبدیل ارقام فارسی/عربی به انگلیسی و گرفتن مقدار صحیح */
function bk_int($s) {
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    $s = str_replace($fa, $en, (string)$s);
    $s = str_replace($ar, $en, $s);
    return (int)preg_replace('/\D/', '', $s);
}

/* مسیر فایل موقت بازگردانی در انتظار تایید */
function backup_pending_path() {
    return dirname(DB_PATH) . '/_restore_pending.db';
}

/* ---------- ارسال فایل (سند) به تلگرام ---------- */
function tg_send_document($chat, $path, $caption = '') {
    if (!is_file($path)) return ['ok' => false];
    $p = [
        'chat_id'    => $chat,
        'caption'    => $caption,
        'parse_mode' => 'HTML',
        'document'   => new CURLFile($path),
    ];
    return tg('sendDocument', $p);
}

/* ---------- دانلود فایل از تلگرام ---------- */
function tg_download_file($file_id, $dest) {
    $r = tg('getFile', ['file_id' => $file_id]);
    if (empty($r['ok']) || empty($r['result']['file_path'])) return false;
    $fp  = $r['result']['file_path'];
    $url = 'https://api.telegram.org/file/bot' . BOT_TOKEN . '/' . $fp;
    $out = fopen($dest, 'wb');
    if (!$out) return false;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $out,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 120,
    ]);
    $ok   = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($out);
    if (!$ok || $code !== 200 || !is_file($dest) || filesize($dest) < 1) {
        @unlink($dest);
        return false;
    }
    return true;
}

/* ---------- ساخت اسنپ‌شات سالم از دیتابیس ---------- */
function backup_make_file() {
    $dir  = dirname(DB_PATH);
    $name = 'backup-' . date('Ymd-His') . '.db';
    $tmp  = $dir . '/' . $name;
    @unlink($tmp);
    try {
        // VACUUM INTO یک کپی تک‌فایلی و سازگار می‌سازد (صرف‌نظر از WAL)
        db()->exec('VACUUM INTO ' . db()->quote($tmp));
    } catch (Exception $e) {
        // fallback برای نسخه‌های قدیمی SQLite
        try { db()->exec('PRAGMA wal_checkpoint(TRUNCATE)'); } catch (Exception $e2) {}
        @copy(DB_PATH, $tmp);
    }
    return [$tmp, $name];
}

/* آمار خلاصه از یک دیتابیس مشخص (با مسیر) */
function backup_stats_of($path) {
    $out = ['users' => 0, 'orders' => 0, 'plans' => 0];
    try {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach (['users', 'orders', 'plans'] as $t) {
            try { $out[$t] = (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn(); }
            catch (Exception $e) {}
        }
        $pdo = null;
    } catch (Exception $e) {}
    return $out;
}

/* آمار دیتابیس فعلی */
function backup_db_stats() {
    return [
        'users'  => (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'orders' => (int)db()->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'plans'  => (int)db()->query("SELECT COUNT(*) FROM plans")->fetchColumn(),
    ];
}

/* ---------- ارسال بکاپ به همه ادمین‌ها ---------- */
function backup_send_to_admins($caption_prefix = '📦 بکاپ دیتابیس') {
    global $ADMIN_IDS;
    list($path, $name) = backup_make_file();
    if (!is_file($path) || filesize($path) < 1) {
        return [false, 'ساخت فایل بکاپ ناموفق بود.', 0];
    }
    $size  = bytes_human(filesize($path));
    $stats = backup_db_stats();
    $caption = $caption_prefix . "\n"
             . "🗓 " . now() . "\n"
             . "💾 حجم: <b>{$size}</b>\n"
             . "👤 کاربران: <b>{$stats['users']}</b> | 🧾 سفارش‌ها: <b>{$stats['orders']}</b> | 📦 پلن‌ها: <b>{$stats['plans']}</b>\n\n"
             . "♻️ برای بازگردانی: پنل مدیریت → ⚙️ تنظیمات → 💾 بکاپ و بازگردانی → 📥 بازگردانی از فایل، و همین فایل را ارسال کنید.";
    $ok = 0;
    foreach ($ADMIN_IDS as $aid) {
        $r = tg_send_document($aid, $path, $caption);
        if (!empty($r['ok'])) $ok++;
    }
    @unlink($path);
    return [$ok > 0, $ok > 0 ? 'ارسال شد' : 'ارسال به تلگرام ناموفق بود.', $ok];
}

/* ---------- اعتبارسنجی فایل بکاپ ---------- */
function backup_validate_sqlite($path) {
    if (!is_file($path) || filesize($path) < 100) {
        return [false, 'فایل نامعتبر یا خالی است.'];
    }
    $fh  = fopen($path, 'rb');
    $hdr = $fh ? fread($fh, 16) : '';
    if ($fh) fclose($fh);
    if (strncmp($hdr, "SQLite format 3\000", 16) !== 0) {
        return [false, 'این فایل یک دیتابیس SQLite معتبر نیست.'];
    }
    try {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $ic = $pdo->query('PRAGMA integrity_check')->fetch(PDO::FETCH_NUM);
        if (!$ic || strtolower($ic[0]) !== 'ok') {
            return [false, 'سلامت دیتابیس تایید نشد (integrity_check).'];
        }
        $tbls = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['users', 'orders', 'settings'] as $t) {
            if (!in_array($t, $tbls, true)) {
                return [false, "جدول ضروری «{$t}» در فایل یافت نشد. این فایل، بکاپ این ربات نیست."];
            }
        }
        $pdo = null;
    } catch (Exception $e) {
        return [false, 'خطا در باز کردن دیتابیس: ' . $e->getMessage()];
    }
    return [true, 'ok'];
}

/* ---------- اجرای بازگردانی از فایل در انتظار ---------- */
function restore_do_pending() {
    $dir     = dirname(DB_PATH);
    $pending = backup_pending_path();
    if (!is_file($pending)) {
        return [false, 'فایل بکاپ در انتظار پیدا نشد. دوباره فایل را ارسال کنید.'];
    }
    list($ok, $why) = backup_validate_sqlite($pending);
    if (!$ok) { @unlink($pending); return [false, $why]; }

    // نسخه‌ی ایمنی از دیتابیس فعلی
    if (is_file(DB_PATH)) {
        try { db()->exec('PRAGMA wal_checkpoint(TRUNCATE)'); } catch (Exception $e) {}
        @copy(DB_PATH, $dir . '/backup-before-restore-' . date('Ymd-His') . '.db');
    }

    // حذف دیتابیس فعلی و فایل‌های WAL/SHM، سپس جایگزینی
    @unlink(DB_PATH . '-wal');
    @unlink(DB_PATH . '-shm');
    @unlink(DB_PATH);
    if (!@rename($pending, DB_PATH)) {
        if (!@copy($pending, DB_PATH)) {
            return [false, 'جایگزینی فایل ناموفق بود (دسترسی نوشتن پوشه data را بررسی کنید).'];
        }
        @unlink($pending);
    }
    @chmod(DB_PATH, 0664);
    return [true, 'ok'];
}

/* ---------- کرون بکاپ خودکار ---------- */
function run_backup_cron() {
    if (setting('backup_auto', '0') !== '1') return;
    $interval = bk_int(setting('backup_interval_hours', '24'));
    if ($interval < 1) $interval = 24;
    $last   = setting('backup_last_at', '');
    $lastTs = $last ? strtotime($last) : 0;
    if ($lastTs && time() < $lastTs + $interval * 3600) return; // هنوز موعد نرسیده
    list($ok, $why, $cnt) = backup_send_to_admins('🔄 بکاپ خودکار دیتابیس');
    if ($ok) set_setting('backup_last_at', now());
}
