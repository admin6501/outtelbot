<?php
/* =======================================================
 *  هسته ربات: دیتابیس، توابع تلگرام و کیبوردها
 * ======================================================= */

/* ---------- دیتابیس ---------- */
function db() {
    static $pdo = null;
    if ($pdo === null) {
        if (!is_dir(dirname(DB_PATH))) {
            @mkdir(dirname(DB_PATH), 0775, true);
            @file_put_contents(dirname(DB_PATH) . '/.htaccess', "Require all denied\nDeny from all");
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL;');
        $pdo->exec('PRAGMA foreign_keys=ON;');
    }
    return $pdo;
}

function db_init() {
    $db = db();
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tg_id INTEGER UNIQUE,
        first_name TEXT,
        username TEXT,
        balance REAL DEFAULT 0,
        referred_by INTEGER DEFAULT NULL,
        step TEXT DEFAULT '',
        temp TEXT DEFAULT '',
        is_blocked INTEGER DEFAULT 0,
        created_at TEXT
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        is_active INTEGER DEFAULT 1,
        created_at TEXT
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS locations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        flag TEXT DEFAULT '',
        is_active INTEGER DEFAULT 1,
        created_at TEXT
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER,
        location_id INTEGER,
        title TEXT,
        description TEXT,
        price REAL,
        is_active INTEGER DEFAULT 1,
        created_at TEXT
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_tg INTEGER,
        plan_id INTEGER,
        plan_title TEXT,
        price REAL,
        status TEXT,
        payment_method TEXT,
        receipt_file_id TEXT,
        config_text TEXT,
        discount_code TEXT,
        created_at TEXT,
        updated_at TEXT
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_tg INTEGER,
        amount REAL,
        receipt_file_id TEXT,
        status TEXT,
        created_at TEXT,
        updated_at TEXT
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS discount_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE,
        type TEXT,
        value REAL,
        max_uses INTEGER DEFAULT 0,
        used_count INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        expire_at TEXT DEFAULT '',
        created_at TEXT
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_tg INTEGER,
        amount REAL,
        type TEXT,
        description TEXT,
        created_at TEXT
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");

    $defaults = [
        'card_number'      => '0000-0000-0000-0000',
        'card_holder'      => 'نام صاحب حساب',
        'support_username' => 'YourSupport',
        'channel_username' => '',
        'forced_join'      => '0',
        'referral_enabled' => '1',
        'referral_percent' => '10',
        'min_charge'       => '50000',
        'card_enabled'     => '1',
        'panel_url'        => '',
        'panel_user'       => '',
        'panel_pass'       => '',
        'panel_address'    => '',
        'panel_sub_url'    => '',
        'panel_auto'       => '0',
        'panel_cookie'     => '',
        'panel_cookie_time'=> '0',
        'welcome_text'     => "🌐 به ربات فروش اوت‌باند خوش آمدید!\n\nاز منوی زیر یکی از گزینه‌ها را انتخاب کنید.",
        'bot_username'     => '',
        'warn_days'        => '2',
        'warn_gb'          => '1',
        'del_grace_time'   => '1',
        'del_grace_vol'    => '7',
    ];
    foreach ($defaults as $k => $v) {
        $st = $db->prepare("INSERT OR IGNORE INTO settings(key,value) VALUES(?,?)");
        $st->execute([$k, $v]);
    }

    // مهاجرت ستون‌ها (افزودن ستون‌های جدید به جدول‌های موجود)
    $addcol = function ($table, $col, $def) use ($db) {
        $cols = $db->query("PRAGMA table_info($table)")->fetchAll();
        foreach ($cols as $c) { if ($c['name'] === $col) return; }
        $db->exec("ALTER TABLE $table ADD COLUMN $col $def");
    };
    $addcol('discount_codes', 'plan_ids', "TEXT DEFAULT ''");
    $addcol('plans', 'inbound_id', "INTEGER DEFAULT 0");
    $addcol('plans', 'traffic_gb', "INTEGER DEFAULT 0");
    $addcol('plans', 'duration_days', "INTEGER DEFAULT 0");
    $addcol('orders', 'panel_inbound', "INTEGER DEFAULT 0");
    $addcol('orders', 'panel_client_id', "TEXT DEFAULT ''");
    $addcol('orders', 'panel_email', "TEXT DEFAULT ''");
    $addcol('orders', 'panel_sub_id', "TEXT DEFAULT ''");
    $addcol('orders', 'renew_of', "INTEGER DEFAULT 0");
    $addcol('orders', 'warn_time_at', "TEXT DEFAULT ''");
    $addcol('orders', 'warn_vol_at', "TEXT DEFAULT ''");
    $addcol('orders', 'depleted_at', "TEXT DEFAULT ''");
}

/* ---------- تنظیمات ---------- */
function setting($key, $default = null) {
    $st = db()->prepare("SELECT value FROM settings WHERE key=?");
    $st->execute([$key]);
    $r = $st->fetch();
    return $r ? $r['value'] : $default;
}
function set_setting($key, $value) {
    $st = db()->prepare("INSERT INTO settings(key,value) VALUES(?,?)
        ON CONFLICT(key) DO UPDATE SET value=excluded.value");
    $st->execute([$key, $value]);
}

/* ---------- کاربر ---------- */
function is_admin($tg_id) {
    global $ADMIN_IDS;
    return in_array((int)$tg_id, array_map('intval', $ADMIN_IDS), true);
}
function now() { return date('Y-m-d H:i:s'); }
function fmt($n) { return number_format((float)$n); }

function ensure_user($from) {
    $tg = $from['id'];
    $st = db()->prepare("SELECT * FROM users WHERE tg_id=?");
    $st->execute([$tg]);
    $u = $st->fetch();
    if (!$u) {
        $ins = db()->prepare("INSERT INTO users(tg_id, first_name, username, created_at) VALUES(?,?,?,?)");
        $ins->execute([$tg, $from['first_name'] ?? '', $from['username'] ?? '', now()]);
        $st->execute([$tg]);
        $u = $st->fetch();
    } else {
        db()->prepare("UPDATE users SET first_name=?, username=? WHERE tg_id=?")
            ->execute([$from['first_name'] ?? '', $from['username'] ?? '', $tg]);
    }
    return $u;
}
function get_user($tg) {
    $st = db()->prepare("SELECT * FROM users WHERE tg_id=?");
    $st->execute([$tg]);
    return $st->fetch();
}
function set_step($tg, $step) {
    db()->prepare("UPDATE users SET step=? WHERE tg_id=?")->execute([$step, $tg]);
}
function set_temp($tg, $arr) {
    db()->prepare("UPDATE users SET temp=? WHERE tg_id=?")->execute([json_encode($arr, JSON_UNESCAPED_UNICODE), $tg]);
}
function get_temp($tg) {
    $u = get_user($tg);
    if (!$u || !$u['temp']) return [];
    $d = json_decode($u['temp'], true);
    return is_array($d) ? $d : [];
}
function add_balance($tg, $amount) {
    db()->prepare("UPDATE users SET balance = balance + ? WHERE tg_id=?")->execute([$amount, $tg]);
}
function add_tx($tg, $amount, $type, $desc) {
    db()->prepare("INSERT INTO transactions(user_tg, amount, type, description, created_at) VALUES(?,?,?,?,?)")
        ->execute([$tg, $amount, $type, $desc, now()]);
}

/* ---------- تلگرام API ---------- */
function tg($method, $params = []) {
    $ch = curl_init(API_URL . $method);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_TIMEOUT => 30,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}
function send($chat, $text, $kb = null, $parse = 'HTML') {
    $p = ['chat_id' => $chat, 'text' => $text, 'parse_mode' => $parse, 'disable_web_page_preview' => true];
    if ($kb !== null) $p['reply_markup'] = json_encode($kb);
    return tg('sendMessage', $p);
}
function edit($chat, $mid, $text, $kb = null, $parse = 'HTML') {
    $p = ['chat_id' => $chat, 'message_id' => $mid, 'text' => $text, 'parse_mode' => $parse, 'disable_web_page_preview' => true];
    if ($kb !== null) $p['reply_markup'] = json_encode($kb);
    $r = tg('editMessageText', $p);
    if (isset($r['ok']) && !$r['ok']) return send($chat, $text, $kb, $parse);
    return $r;
}
function answer($cb_id, $text = '', $alert = false) {
    tg('answerCallbackQuery', ['callback_query_id' => $cb_id, 'text' => $text, 'show_alert' => $alert]);
}
function send_photo($chat, $file_id, $caption = '', $kb = null) {
    $p = ['chat_id' => $chat, 'photo' => $file_id, 'caption' => $caption, 'parse_mode' => 'HTML'];
    if ($kb !== null) $p['reply_markup'] = json_encode($kb);
    return tg('sendPhoto', $p);
}

/* ---------- کیبوردها ---------- */
function inline($rows) { return ['inline_keyboard' => $rows]; }
function btn($text, $data) { return ['text' => $text, 'callback_data' => $data]; }
function url_btn($text, $url) { return ['text' => $text, 'url' => $url]; }

function main_menu_kb($tg = null) {
    $kb = [
        [['text' => '🛒 خرید اوت‌باند'], ['text' => '👛 کیف پول']],
        [['text' => '📦 سفارش‌های من'], ['text' => '🎁 کد تخفیف']],
        [['text' => '👥 زیرمجموعه‌گیری'], ['text' => '☎️ پشتیبانی']],
    ];
    if ($tg !== null && is_admin($tg)) {
        $kb[] = [['text' => '🛠 پنل مدیریت']];
    }
    return ['keyboard' => $kb, 'resize_keyboard' => true];
}

function out($chat, $mid, $text, $kb = null) {
    return $mid ? edit($chat, $mid, $text, $kb) : send($chat, $text, $kb);
}

function bot_username() {
    $u = setting('bot_username', '');
    if (!$u) {
        $r = tg('getMe');
        if (isset($r['result']['username'])) {
            $u = $r['result']['username'];
            set_setting('bot_username', $u);
        }
    }
    return $u;
}

function notify_admins($text, $kb = null) {
    global $ADMIN_IDS;
    foreach ($ADMIN_IDS as $aid) {
        send($aid, $text, $kb);
    }
}

/* ---------- جوین اجباری ---------- */
function check_join($tg_id) {
    if (setting('forced_join', '0') !== '1') return true;
    if (is_admin($tg_id)) return true;
    $ch = trim(setting('channel_username', ''));
    if (!$ch) return true;
    if ($ch[0] !== '@') $ch = '@' . $ch;
    $r = tg('getChatMember', ['chat_id' => $ch, 'user_id' => $tg_id]);
    if (!isset($r['ok']) || !$r['ok']) return true; // اگر ربات ادمین کانال نباشد، مسدود نکن
    $status = $r['result']['status'] ?? '';
    return in_array($status, ['member', 'administrator', 'creator'], true);
}
function send_join_prompt($chat) {
    $ch = trim(setting('channel_username', ''));
    $clean = ltrim($ch, '@');
    $kb = inline([
        [url_btn('📢 عضویت در کانال', 'https://t.me/' . $clean)],
        [btn('✅ عضو شدم', 'check_join')],
    ]);
    send($chat, "🔒 برای استفاده از ربات ابتدا باید در کانال زیر عضو شوید:\n\n@" . $clean . "\n\nپس از عضویت، روی «✅ عضو شدم» بزنید.", $kb);
}

/* ---------- کمک‌کننده‌ها ---------- */
function get_plan($id) {
    $st = db()->prepare("SELECT * FROM plans WHERE id=?");
    $st->execute([$id]);
    return $st->fetch();
}
function status_label($s) {
    $map = [
        'awaiting_receipt' => '⏳ در انتظار ارسال رسید',
        'pending_approval' => '🕓 در انتظار تایید پرداخت',
        'paid'             => '📦 در حال آماده‌سازی',
        'delivered'        => '✅ تحویل شده',
        'rejected'         => '❌ لغو شده',
        'expired'          => '⛔️ منقضی شده',
    ];
    return $map[$s] ?? $s;
}
function apply_discount($price, $code, $plan_id = null) {
    if (!$code) return [$price, 0, null];
    $st = db()->prepare("SELECT * FROM discount_codes WHERE code=? AND is_active=1");
    $st->execute([$code]);
    $d = $st->fetch();
    if (!$d) return [$price, 0, null];
    if ($d['max_uses'] > 0 && $d['used_count'] >= $d['max_uses']) return [$price, 0, null];
    if ($d['expire_at'] && strtotime($d['expire_at']) < time()) return [$price, 0, null];
    // محدودیت پلن: اگر plan_ids تعیین شده باشد، فقط برای همان پلن‌ها معتبر است
    $allowed = trim($d['plan_ids'] ?? '');
    if ($allowed !== '') {
        $ids = array_filter(array_map('intval', explode(',', $allowed)));
        if ($plan_id === null || !in_array((int)$plan_id, $ids, true)) return [$price, 0, null];
    }
    $off = $d['type'] === 'percent' ? round($price * $d['value'] / 100) : $d['value'];
    if ($off > $price) $off = $price;
    return [$price - $off, $off, $d['code']];
}
