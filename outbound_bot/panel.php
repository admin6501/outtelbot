<?php
/* =======================================================
 *  ادغام با پنل 3x-ui (تحویل خودکار کانفیگ)
 *  https://github.com/MHSanaei/3x-ui
 *  پشتیبانی از چند پنل: هر پلن/سفارش به یک پنل (panel_id) متصل است.
 * ======================================================= */

/* ---------- مدیریت context پنل فعال ---------- */
function panel_ctx($set = false) {
    static $ctx = null;
    if ($set !== false) { $ctx = $set; }
    return $ctx;
}
/* خواندن یک مقدار از پنل فعال */
function pcfg($key, $default = '') {
    $ctx = panel_ctx();
    if (is_array($ctx) && isset($ctx[$key]) && $ctx[$key] !== null) return $ctx[$key];
    return $default;
}
function panel_get($id) {
    $st = db()->prepare("SELECT * FROM panels WHERE id=?");
    $st->execute([(int)$id]);
    return $st->fetch() ?: null;
}
/* تنظیم پنل فعال با آیدی */
function panel_use($panel_id) {
    $p = ((int)$panel_id > 0) ? panel_get($panel_id) : null;
    panel_ctx($p);
    return $p;
}
/* تنظیم پنل فعال از روی سفارش */
function panel_use_for_order($o) {
    return panel_use((int)($o['panel_id'] ?? 0));
}
/* تنظیم پنل فعال از روی پلن */
function panel_use_for_plan($plan) {
    return panel_use((int)($plan['panel_id'] ?? 0));
}
/* ذخیره کوکی برای پنل فعال */
function panel_store_cookie($cookie) {
    $ctx = panel_ctx();
    $t = time();
    if (is_array($ctx)) {
        $ctx['cookie'] = $cookie;
        $ctx['cookie_time'] = $t;
        panel_ctx($ctx);
        if (!empty($ctx['id'])) {
            db()->prepare("UPDATE panels SET cookie=?, cookie_time=? WHERE id=?")->execute([$cookie, $t, $ctx['id']]);
        }
    }
}

function panel_server_address() {
    $a = trim(pcfg('address', ''));
    if ($a !== '') return $a;
    $h = parse_url(pcfg('url', ''), PHP_URL_HOST);
    return $h ?: '';
}

function guidv4() {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/* ---------- ارتباط با پنل ---------- */
function panel_login() {
    $url = rtrim(pcfg('url', ''), '/');
    if (!$url) return false;
    $ch = curl_init($url . '/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'username' => pcfg('username', ''),
            'password' => pcfg('password', ''),
        ]),
        CURLOPT_HEADER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $resp = curl_exec($ch);
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($resp === false) return false;
    $headers = substr($resp, 0, $hsize);
    $body = substr($resp, $hsize);
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['success'])) return false;
    preg_match_all('/Set-Cookie:\s*([^;\r\n]+)/i', $headers, $m);
    if (empty($m[1])) return false;
    panel_store_cookie(implode('; ', $m[1]));
    return true;
}

function panel_curl($full, $method = 'GET', $fields = null) {
    $ch = curl_init($full);
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_COOKIE => pcfg('cookie', ''),
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ];
    if ($method === 'POST') {
        $opt[CURLOPT_POST] = true;
        if ($fields !== null) $opt[CURLOPT_POSTFIELDS] = http_build_query($fields);
    }
    curl_setopt_array($ch, $opt);
    $body = curl_exec($ch);
    curl_close($ch);
    if ($body === false) return null;
    $data = json_decode($body, true);
    if ($data === null) {
        if (stripos($body, 'login') !== false || stripos($body, '<html') !== false) return ['needLogin' => true];
        return null;
    }
    return $data;
}

function panel_api($method, $path, $fields = null) {
    $url = rtrim(pcfg('url', ''), '/');
    if (!$url) return null;
    $age = time() - (int)pcfg('cookie_time', 0);
    if (!pcfg('cookie', '') || $age > 3000) { if (!panel_login()) return null; }
    $res = panel_curl($url . $path, $method, $fields);
    if ($res === null || (is_array($res) && !empty($res['needLogin']))) {
        if (panel_login()) $res = panel_curl($url . $path, $method, $fields);
    }
    return $res;
}

function panel_get_inbound($id) {
    $r = panel_api('GET', '/panel/api/inbounds/get/' . (int)$id);
    if (is_array($r) && !empty($r['success']) && isset($r['obj'])) return $r['obj'];
    return null;
}

function panel_list_inbounds() {
    $r = panel_api('GET', '/panel/api/inbounds/list');
    if (is_array($r) && !empty($r['success']) && isset($r['obj'])) return $r['obj'];
    return null;
}

function panel_add_client($inbound_id, $client) {
    $settings = json_encode(['clients' => [$client]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $r = panel_api('POST', '/panel/api/inbounds/addClient', ['id' => (int)$inbound_id, 'settings' => $settings]);
    return is_array($r) && !empty($r['success']);
}

function panel_update_client($inbound_id, $client_secret, $client) {
    $settings = json_encode(['clients' => [$client]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $r = panel_api('POST', '/panel/api/inbounds/updateClient/' . rawurlencode($client_secret), ['id' => (int)$inbound_id, 'settings' => $settings]);
    return is_array($r) && !empty($r['success']);
}

function panel_reset_client_traffic($inbound_id, $email) {
    $r = panel_api('POST', '/panel/api/inbounds/' . (int)$inbound_id . '/resetClientTraffic/' . rawurlencode($email));
    return is_array($r) && !empty($r['success']);
}

/* حذف کلاینت از پنل (clientId = uuid برای vless/vmess و password برای trojan) */
function panel_del_client($inbound_id, $secret) {
    $r = panel_api('POST', '/panel/api/inbounds/' . (int)$inbound_id . '/delClient/' . rawurlencode($secret));
    return is_array($r) && !empty($r['success']);
}

function panel_get_client_traffic($email) {
    $r = panel_api('GET', '/panel/api/inbounds/getClientTraffics/' . rawurlencode($email));
    if (is_array($r) && !empty($r['success']) && isset($r['obj'])) return $r['obj'];
    return null;
}

/* تبدیل بایت به فرمت خوانا */
function bytes_human($b) {
    $b = (float)$b;
    if ($b <= 0) return '0 MB';
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($b, 1024));
    $i = max(0, min($i, count($u) - 1));
    return round($b / pow(1024, $i), 2) . ' ' . $u[$i];
}

/* آیا سفارش به یک کلاینت روی پنل متصل است؟ */
function order_has_panel($o) {
    return (int)($o['panel_inbound'] ?? 0) > 0 && !empty($o['panel_email']) && !empty($o['panel_client_id']);
}

/* فعال/غیرفعال‌سازی کلاینت روی پنل (حجم و انقضا حفظ می‌شود) */
function panel_set_client_enable($o, $enable) {
    if (!order_has_panel($o)) return false;
    $inbound_id = (int)$o['panel_inbound'];
    $secret = $o['panel_client_id'];
    $email = $o['panel_email'];
    $subId = $o['panel_sub_id'];
    $inbound = panel_get_inbound($inbound_id);
    if (!$inbound) return false;
    $protocol = $inbound['protocol'] ?? '';
    $cur = panel_get_client_traffic($email);
    $total = is_array($cur) ? (int)($cur['total'] ?? 0) : 0;
    $exp = is_array($cur) ? (int)($cur['expiryTime'] ?? 0) : 0;
    $client = [
        'email' => $email, 'enable' => (bool)$enable, 'limitIp' => 0,
        'totalGB' => $total, 'expiryTime' => $exp,
        'tgId' => (string)$o['user_tg'], 'subId' => $subId, 'reset' => 0, 'flow' => '',
    ];
    if ($protocol === 'trojan') $client['password'] = $secret; else $client['id'] = $secret;
    return panel_update_client($inbound_id, $secret, $client);
}

/* تغییر لینک: ساخت UUID/subId جدید و باطل‌کردن لینک قبلی (حجم و انقضا حفظ می‌شود) */
function panel_change_client($o) {
    if (!order_has_panel($o)) return false;
    $inbound_id = (int)$o['panel_inbound'];
    $oldSecret = $o['panel_client_id'];
    $email = $o['panel_email'];
    $inbound = panel_get_inbound($inbound_id);
    if (!$inbound) return false;
    $protocol = $inbound['protocol'] ?? '';
    $cur = panel_get_client_traffic($email);
    $total = is_array($cur) ? (int)($cur['total'] ?? 0) : 0;
    $exp = is_array($cur) ? (int)($cur['expiryTime'] ?? 0) : 0;
    $enable = is_array($cur) ? (bool)($cur['enable'] ?? true) : true;
    $newSecret = guidv4();
    $newSubId = bin2hex(random_bytes(8));
    $client = [
        'email' => $email, 'enable' => $enable, 'limitIp' => 0,
        'totalGB' => $total, 'expiryTime' => $exp,
        'tgId' => (string)$o['user_tg'], 'subId' => $newSubId, 'reset' => 0, 'flow' => '',
    ];
    if ($protocol === 'trojan') $client['password'] = $newSecret; else $client['id'] = $newSecret;
    if (!panel_update_client($inbound_id, $oldSecret, $client)) return false;
    db()->prepare("UPDATE orders SET panel_client_id=?, panel_sub_id=? WHERE id=?")
        ->execute([$newSecret, $newSubId, $o['id']]);
    $subUrl = trim(pcfg('sub_url', ''));
    if ($subUrl !== '') return rtrim($subUrl, '/') . '/' . $newSubId;
    return panel_build_link($inbound, $newSecret, $email);
}

/* تست اتصال + نمایش اینباندها (برای یک پنل مشخص) */
function panel_test($panel_id) {
    $p = panel_use($panel_id);
    if (!$p) return [false, 'پنل یافت نشد.'];
    if (!pcfg('url', '')) return [false, 'آدرس پنل تنظیم نشده است.'];
    if (!panel_login()) return [false, 'ورود ناموفق! آدرس/یوزرنیم/پسورد را بررسی کنید (آدرس باید شامل مسیر پایه پنل باشد).'];
    $list = panel_list_inbounds();
    if ($list === null) return [false, 'ورود موفق بود ولی دریافت لیست اینباندها ناموفق شد.'];
    $lines = [];
    foreach ($list as $ib) {
        $lines[] = "🔌 آیدی <b>{$ib['id']}</b> | " . ($ib['remark'] ?? '') . " | " . $ib['protocol'] . " | پورت " . $ib['port'];
    }
    return [true, "✅ اتصال موفق بود.\nتعداد اینباند: " . count($list) . "\n\n" . implode("\n", $lines) . "\n\nℹ️ از آیدی اینباند برای تنظیم پلن استفاده کنید."];
}

/* ---------- ساخت لینک کانفیگ از روی اینباند ---------- */
function panel_stream_params($net, $stream) {
    $p = [];
    if ($net === 'ws') {
        $ws = $stream['wsSettings'] ?? [];
        $p['path'] = $ws['path'] ?? '/';
        $host = $ws['headers']['Host'] ?? ($ws['host'] ?? '');
        if ($host) $p['host'] = $host;
    } elseif ($net === 'grpc') {
        $g = $stream['grpcSettings'] ?? [];
        $p['serviceName'] = $g['serviceName'] ?? '';
        $p['mode'] = !empty($g['multiMode']) ? 'multi' : 'gun';
    } elseif ($net === 'tcp') {
        $tcp = $stream['tcpSettings'] ?? [];
        $hdr = $tcp['header']['type'] ?? 'none';
        $p['headerType'] = $hdr;
        if ($hdr === 'http') {
            $req = $tcp['header']['request'] ?? [];
            $p['path'] = $req['path'][0] ?? '/';
            $host = $req['headers']['Host'][0] ?? '';
            if ($host) $p['host'] = $host;
        }
    } elseif ($net === 'httpupgrade') {
        $h = $stream['httpupgradeSettings'] ?? [];
        $p['path'] = $h['path'] ?? '/';
        if (!empty($h['host'])) $p['host'] = $h['host'];
    } elseif ($net === 'xhttp' || $net === 'splithttp') {
        $h = $stream['xhttpSettings'] ?? ($stream['splithttpSettings'] ?? []);
        $p['path'] = $h['path'] ?? '/';
        if (!empty($h['host'])) $p['host'] = $h['host'];
    }
    return $p;
}

function panel_security_params($security, $stream) {
    $p = ['security' => $security];
    if ($security === 'tls') {
        $tls = $stream['tlsSettings'] ?? [];
        if (!empty($tls['serverName'])) $p['sni'] = $tls['serverName'];
        $fp = $tls['settings']['fingerprint'] ?? '';
        if ($fp) $p['fp'] = $fp;
        if (!empty($tls['alpn'])) $p['alpn'] = implode(',', $tls['alpn']);
    } elseif ($security === 'reality') {
        $r = $stream['realitySettings'] ?? [];
        $p['pbk'] = $r['settings']['publicKey'] ?? '';
        $p['fp']  = $r['settings']['fingerprint'] ?? 'chrome';
        if (!empty($r['serverNames'][0])) $p['sni'] = $r['serverNames'][0];
        if (!empty($r['shortIds'][0])) $p['sid'] = $r['shortIds'][0];
        $p['spx'] = $r['settings']['spiderX'] ?? '/';
    }
    return $p;
}

function panel_build_link($inbound, $secret, $email) {
    $protocol = $inbound['protocol'] ?? '';
    $port = $inbound['port'] ?? 0;
    $address = panel_server_address();
    if (!$address) return '';
    $remark = $inbound['remark'] ?? 'cfg';
    $stream = json_decode($inbound['streamSettings'] ?? '{}', true) ?: [];
    $net = $stream['network'] ?? 'tcp';
    $security = $stream['security'] ?? 'none';
    $tag = rawurlencode($remark . '-' . $email);
    $sp = panel_stream_params($net, $stream);
    $sec = panel_security_params($security, $stream);

    if ($protocol === 'vmess') {
        $conf = [
            'v' => '2', 'ps' => $remark . '-' . $email, 'add' => $address, 'port' => (string)$port,
            'id' => $secret, 'aid' => '0', 'scy' => 'auto', 'net' => $net,
            'type' => $sp['headerType'] ?? 'none',
            'host' => $sp['host'] ?? '', 'path' => $sp['path'] ?? '',
            'tls' => ($security === 'tls' ? 'tls' : ''),
            'sni' => $sec['sni'] ?? '', 'alpn' => $sec['alpn'] ?? '', 'fp' => $sec['fp'] ?? '',
        ];
        if ($net === 'grpc') { $conf['path'] = $sp['serviceName'] ?? ''; $conf['type'] = $sp['mode'] ?? 'gun'; }
        return 'vmess://' . base64_encode(json_encode($conf, JSON_UNESCAPED_UNICODE));
    }

    if ($protocol === 'vless') {
        $params = array_merge(['type' => $net, 'encryption' => 'none'], $sp, $sec);
        return 'vless://' . $secret . '@' . $address . ':' . $port . '?' . http_build_query($params) . '#' . $tag;
    }

    if ($protocol === 'trojan') {
        $params = array_merge(['type' => $net], $sp, $sec);
        return 'trojan://' . $secret . '@' . $address . ':' . $port . '?' . http_build_query($params) . '#' . $tag;
    }

    return '';
}

/* ---------- تحویل خودکار ---------- */
function try_auto_deliver($oid) {
    if (setting('panel_auto', '0') !== '1') return false;
    $st = db()->prepare("SELECT * FROM orders WHERE id=?"); $st->execute([$oid]); $o = $st->fetch();
    if (!$o) return false;
    $plan = get_plan($o['plan_id']);
    if (!$plan || (int)($plan['inbound_id'] ?? 0) <= 0) return false;
    // پنل این پلن را فعال کن
    $pnl = panel_use_for_plan($plan);
    if (!$pnl || !pcfg('url', '')) return false;

    // اگر تمدید است و سفارش اصلی روی پنل کلاینت دارد → همان کلاینت را تمدید کن
    $renewOf = (int)($o['renew_of'] ?? 0);
    if ($renewOf > 0) {
        $os = db()->prepare("SELECT * FROM orders WHERE id=?"); $os->execute([$renewOf]); $orig = $os->fetch();
        if ($orig && !empty($orig['panel_client_id']) && (int)$orig['panel_inbound'] > 0) {
            // تمدید روی همان پنلِ سفارش اصلی
            $origPanel = (int)($orig['panel_id'] ?? 0);
            if ($origPanel > 0) panel_use($origPanel);
            $link = panel_renew_client($orig, $plan);
            if ($link !== false && $link !== '') {
                db()->prepare("UPDATE orders SET panel_id=?, panel_inbound=?, panel_client_id=?, panel_email=?, panel_sub_id=? WHERE id=?")
                    ->execute([($origPanel ?: (int)$pnl['id']), $orig['panel_inbound'], $orig['panel_client_id'], $orig['panel_email'], $orig['panel_sub_id'], $oid]);
                deliver_order($oid, $link, true);
                return true;
            }
            // اگر تمدید ناموفق بود، به ساخت کلاینت جدید برمی‌گردیم (روی پنل پلن)
            panel_use_for_plan($plan);
        }
    }

    return auto_create_client_and_deliver($oid, $o, $plan);
}

function auto_create_client_and_deliver($oid, $o, $plan) {
    $panel_id = (int)pcfg('id', 0);
    $inbound = panel_get_inbound($plan['inbound_id']);
    if (!$inbound) return false;
    $protocol = $inbound['protocol'] ?? '';
    $email = 'tg' . $o['user_tg'] . '_o' . $oid;
    $expiry = ((int)$plan['duration_days'] > 0) ? (time() + (int)$plan['duration_days'] * 86400) * 1000 : 0;
    $totalGB = ((int)$plan['traffic_gb'] > 0) ? (int)$plan['traffic_gb'] * 1073741824 : 0;
    $subId = bin2hex(random_bytes(8));
    $secret = guidv4();
    $client = [
        'email' => $email, 'enable' => true, 'limitIp' => 0,
        'totalGB' => $totalGB, 'expiryTime' => $expiry,
        'tgId' => (string)$o['user_tg'], 'subId' => $subId, 'reset' => 0, 'flow' => '',
    ];
    if ($protocol === 'trojan') $client['password'] = $secret; else $client['id'] = $secret;
    if (!panel_add_client($plan['inbound_id'], $client)) return false;

    $subUrl = trim(pcfg('sub_url', ''));
    $link = $subUrl !== '' ? rtrim($subUrl, '/') . '/' . $subId : panel_build_link($inbound, $secret, $email);
    if (!$link) return false;

    db()->prepare("UPDATE orders SET panel_id=?, panel_inbound=?, panel_client_id=?, panel_email=?, panel_sub_id=? WHERE id=?")
        ->execute([$panel_id, $plan['inbound_id'], $secret, $email, $subId, $oid]);
    deliver_order($oid, $link);
    return true;
}

/* تمدید کلاینت موجود روی پنل: تاریخ انقضا تمدید و حجم تازه‌سازی می‌شود */
function panel_renew_client($orig, $plan) {
    $inbound_id = (int)$orig['panel_inbound'];
    $secret = $orig['panel_client_id'];
    $email = $orig['panel_email'];
    $subId = $orig['panel_sub_id'];
    $inbound = panel_get_inbound($inbound_id);
    if (!$inbound) return false;
    $protocol = $inbound['protocol'] ?? '';

    // تاریخ انقضای فعلی را بگیر و از آن (یا اکنون) تمدید کن
    $cur = panel_get_client_traffic($email);
    $curExp = is_array($cur) ? (int)($cur['expiryTime'] ?? 0) : 0;
    $curTotal = is_array($cur) ? (int)($cur['total'] ?? 0) : 0; // سقف حجم فعلی (بایت) ۰=نامحدود
    $nowMs = time() * 1000;
    $base = ($curExp > $nowMs) ? $curExp : $nowMs;
    $newExp = ((int)$plan['duration_days'] > 0) ? $base + (int)$plan['duration_days'] * 86400 * 1000 : 0;

    // حجم: بدون ریست؛ حجم پلن جدید به سقف فعلی اضافه می‌شود (حجم باقی‌مانده حفظ می‌شود)
    $planBytes = ((int)$plan['traffic_gb'] > 0) ? (int)$plan['traffic_gb'] * 1073741824 : 0;
    if ($planBytes === 0 || $curTotal === 0) {
        $newTotal = 0; // اگر پلن یا سرویس فعلی نامحدود است → نامحدود
    } else {
        $newTotal = $curTotal + $planBytes; // افزودن حجم جدید به سقف فعلی
    }

    $client = [
        'email' => $email, 'enable' => true, 'limitIp' => 0,
        'totalGB' => $newTotal, 'expiryTime' => $newExp,
        'tgId' => (string)$orig['user_tg'], 'subId' => $subId, 'reset' => 0, 'flow' => '',
    ];
    if ($protocol === 'trojan') $client['password'] = $secret; else $client['id'] = $secret;

    if (!panel_update_client($inbound_id, $secret, $client)) return false;
    // توجه: حجم مصرفی ریست نمی‌شود تا حجم باقی‌مانده حفظ شود

    $subUrl = trim(pcfg('sub_url', ''));
    if ($subUrl !== '') return rtrim($subUrl, '/') . '/' . $subId;
    return panel_build_link($inbound, $secret, $email);
}

/* ===================================================================
 *  کرون: هشدار انقضا/اتمام حجم و حذف خودکار سرویس‌های منقضی
 *  - کانفیگ‌های زمان‌دار: del_grace_time روز بعد از انقضا حذف می‌شوند
 *  - کانفیگ‌های فقط‌حجمی: del_grace_vol روز بعد از اتمام حجم حذف می‌شوند
 *  - هشدار وقتی warn_days روز یا warn_gb گیگ باقی مانده باشد
 * =================================================================== */
function run_expiry_cron() {
    $hasPanel = (int)db()->query("SELECT COUNT(*) FROM panels")->fetchColumn();
    if ($hasPanel === 0) return;
    $GB = 1073741824;
    $warnDays  = (int)setting('warn_days', '2');
    $warnBytes = (int)round((float)setting('warn_gb', '1') * $GB);
    $graceTime = (int)setting('del_grace_time', '1');
    $graceVol  = (int)setting('del_grace_vol', '7');
    $nowMs = time() * 1000;

    $orders = db()->query("SELECT * FROM orders WHERE status='delivered' AND panel_inbound>0 AND panel_email!='' AND panel_client_id!=''")->fetchAll();
    foreach ($orders as $o) {
        // پنل مربوط به این سفارش را فعال کن
        if (!panel_use_for_order($o)) continue;
        $live = panel_get_client_traffic($o['panel_email']);
        if (!is_array($live)) continue;
        $used  = (int)($live['up'] ?? 0) + (int)($live['down'] ?? 0);
        $total = (int)($live['total'] ?? 0);
        $exp   = (int)($live['expiryTime'] ?? 0);
        $timeBased = $exp > 0;

        /* ---- حذف خودکار ---- */
        if ($timeBased) {
            if ($nowMs > $exp + $graceTime * 86400000) { cron_delete_order($o); continue; }
        } elseif ($total > 0) {
            if ($used >= $total) {
                if (empty($o['depleted_at'])) {
                    db()->prepare("UPDATE orders SET depleted_at=? WHERE id=?")->execute([now(), $o['id']]);
                    $o['depleted_at'] = now();
                } else {
                    $depTs = strtotime($o['depleted_at']);
                    if ($depTs && time() > $depTs + $graceVol * 86400) { cron_delete_order($o); continue; }
                }
            } elseif (!empty($o['depleted_at'])) {
                db()->prepare("UPDATE orders SET depleted_at='' WHERE id=?")->execute([$o['id']]);
            }
        }

        /* ---- هشدار زمان (فقط برای کانفیگ‌های زمان‌دار) ---- */
        if ($timeBased && empty($o['warn_time_at'])) {
            $daysLeft = ($exp - $nowMs) / 86400000;
            if ($daysLeft > 0 && $daysLeft <= $warnDays) {
                cron_warn($o, 'time', (int)ceil($daysLeft), null);
                db()->prepare("UPDATE orders SET warn_time_at=? WHERE id=?")->execute([now(), $o['id']]);
            }
        }

        /* ---- هشدار حجم (هر زمان که سقف حجم تعریف شده باشد) ---- */
        if ($total > 0 && empty($o['warn_vol_at'])) {
            $remain = $total - $used;
            if ($remain > 0 && $remain <= $warnBytes) {
                cron_warn($o, 'vol', null, $remain);
                db()->prepare("UPDATE orders SET warn_vol_at=? WHERE id=?")->execute([now(), $o['id']]);
            }
        }
    }
}

function cron_delete_order($o) {
    panel_del_client((int)$o['panel_inbound'], $o['panel_client_id']);
    db()->prepare("UPDATE orders SET status='expired', updated_at=? WHERE id=?")->execute([now(), $o['id']]);
    send($o['user_tg'],
        "⛔️ <b>سرویس شما منقضی شد</b>\n\n🧾 سفارش #{$o['id']}\n📦 {$o['plan_title']}\n\nسرویس شما به پایان رسید و از سرور حذف شد.\nبرای ادامه می‌توانید سرویس جدید تهیه کنید یا تمدید نمایید.",
        inline([[btn('🔄 تمدید / خرید مجدد', 'renew:' . $o['id'])]]));
}

function cron_warn($o, $type, $days, $remainBytes) {
    if ($type === 'time') {
        $msg = "⚠️ <b>هشدار نزدیک شدن به پایان سرویس</b>\n\n🧾 سفارش #{$o['id']}\n📦 {$o['plan_title']}\n\n⏳ تنها <b>{$days} روز</b> تا پایان سرویس شما باقی مانده است.\nبرای جلوگیری از قطع شدن، سرویس خود را تمدید کنید.";
    } else {
        $msg = "⚠️ <b>هشدار اتمام حجم سرویس</b>\n\n🧾 سفارش #{$o['id']}\n📦 {$o['plan_title']}\n\n📉 تنها <b>" . bytes_human($remainBytes) . "</b> از حجم سرویس شما باقی مانده است.\nبرای جلوگیری از قطع شدن، سرویس خود را تمدید کنید.";
    }
    send($o['user_tg'], $msg, inline([[btn('🔄 تمدید سرویس', 'renew:' . $o['id'])]]));
}
