<?php
/* =======================================================
 *  ادغام با پنل 3x-ui (تحویل خودکار اوت‌باند)
 *  https://github.com/MHSanaei/3x-ui
 * ======================================================= */

function panel_server_address() {
    $a = trim(setting('panel_address', ''));
    if ($a !== '') return $a;
    $h = parse_url(setting('panel_url', ''), PHP_URL_HOST);
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
    $url = rtrim(setting('panel_url', ''), '/');
    if (!$url) return false;
    $ch = curl_init($url . '/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'username' => setting('panel_user', ''),
            'password' => setting('panel_pass', ''),
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
    set_setting('panel_cookie', implode('; ', $m[1]));
    set_setting('panel_cookie_time', (string)time());
    return true;
}

function panel_curl($full, $method = 'GET', $fields = null) {
    $ch = curl_init($full);
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_COOKIE => setting('panel_cookie', ''),
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
    $url = rtrim(setting('panel_url', ''), '/');
    if (!$url) return null;
    $age = time() - (int)setting('panel_cookie_time', '0');
    if (!setting('panel_cookie', '') || $age > 3000) { if (!panel_login()) return null; }
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

function panel_get_client_traffic($email) {
    $r = panel_api('GET', '/panel/api/inbounds/getClientTraffics/' . rawurlencode($email));
    if (is_array($r) && !empty($r['success']) && isset($r['obj'])) return $r['obj'];
    return null;
}

/* تست اتصال + نمایش اینباندها */
function panel_test() {
    if (!setting('panel_url', '')) return [false, 'آدرس پنل تنظیم نشده است.'];
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
    if (!setting('panel_url', '')) return false;
    $st = db()->prepare("SELECT * FROM orders WHERE id=?"); $st->execute([$oid]); $o = $st->fetch();
    if (!$o) return false;
    $plan = get_plan($o['plan_id']);
    if (!$plan || (int)($plan['inbound_id'] ?? 0) <= 0) return false;

    // اگر تمدید است و سفارش اصلی روی پنل کلاینت دارد → همان کلاینت را تمدید کن
    $renewOf = (int)($o['renew_of'] ?? 0);
    if ($renewOf > 0) {
        $os = db()->prepare("SELECT * FROM orders WHERE id=?"); $os->execute([$renewOf]); $orig = $os->fetch();
        if ($orig && !empty($orig['panel_client_id']) && (int)$orig['panel_inbound'] > 0) {
            $link = panel_renew_client($orig, $plan);
            if ($link !== false && $link !== '') {
                db()->prepare("UPDATE orders SET panel_inbound=?, panel_client_id=?, panel_email=?, panel_sub_id=? WHERE id=?")
                    ->execute([$orig['panel_inbound'], $orig['panel_client_id'], $orig['panel_email'], $orig['panel_sub_id'], $oid]);
                deliver_order($oid, $link, true);
                return true;
            }
            // اگر تمدید ناموفق بود، به ساخت کلاینت جدید برمی‌گردیم
        }
    }

    return auto_create_client_and_deliver($oid, $o, $plan);
}

function auto_create_client_and_deliver($oid, $o, $plan) {
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

    $subUrl = trim(setting('panel_sub_url', ''));
    $link = $subUrl !== '' ? rtrim($subUrl, '/') . '/' . $subId : panel_build_link($inbound, $secret, $email);
    if (!$link) return false;

    db()->prepare("UPDATE orders SET panel_inbound=?, panel_client_id=?, panel_email=?, panel_sub_id=? WHERE id=?")
        ->execute([$plan['inbound_id'], $secret, $email, $subId, $oid]);
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
    $nowMs = time() * 1000;
    $base = ($curExp > $nowMs) ? $curExp : $nowMs;
    $newExp = ((int)$plan['duration_days'] > 0) ? $base + (int)$plan['duration_days'] * 86400 * 1000 : 0;
    $totalGB = ((int)$plan['traffic_gb'] > 0) ? (int)$plan['traffic_gb'] * 1073741824 : 0;

    $client = [
        'email' => $email, 'enable' => true, 'limitIp' => 0,
        'totalGB' => $totalGB, 'expiryTime' => $newExp,
        'tgId' => (string)$orig['user_tg'], 'subId' => $subId, 'reset' => 0, 'flow' => '',
    ];
    if ($protocol === 'trojan') $client['password'] = $secret; else $client['id'] = $secret;

    if (!panel_update_client($inbound_id, $secret, $client)) return false;
    // ریست حجم مصرفی تا حجم تمدیدشده تازه باشد
    panel_reset_client_traffic($inbound_id, $email);

    $subUrl = trim(setting('panel_sub_url', ''));
    if ($subUrl !== '') return rtrim($subUrl, '/') . '/' . $subId;
    return panel_build_link($inbound, $secret, $email);
}
