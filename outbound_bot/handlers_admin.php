<?php
/* =======================================================
 *  هندلرهای ادمین (پنل مدیریت داخل تلگرام)
 * ======================================================= */

function admin_menu($chat, $mid = null) {
    $kb = inline([
        [btn('📊 آمار', 'a_stats'), btn('🧾 سفارش‌ها', 'a_orders')],
        [btn('🗂 دسته‌بندی‌ها', 'a_cats'), btn('🌍 لوکیشن‌ها', 'a_locs')],
        [btn('📦 پلن‌ها', 'a_plans'), btn('💳 شارژها', 'a_charges')],
        [btn('🎟 کد تخفیف', 'a_dcs'), btn('👤 کاربران', 'a_users')],
        [btn('👥 زیرمجموعه‌گیری', 'a_ref'), btn('📢 پیام همگانی', 'a_bc')],
        [btn('⚙️ تنظیمات', 'a_settings')],
    ]);
    $t = "🛠 <b>پنل مدیریت</b>\nیکی از بخش‌ها را انتخاب کنید:";
    $mid ? edit($chat, $mid, $t, $kb) : send($chat, $t, $kb);
}

/* کیبورد ثابت پایین صفحه برای پنل مدیریت */
function admin_menu_kb() {
    return [
        'keyboard' => [
            [['text' => '📊 آمار'], ['text' => '🧾 سفارش‌ها']],
            [['text' => '🗂 دسته‌بندی‌ها'], ['text' => '🌍 لوکیشن‌ها']],
            [['text' => '📦 پلن‌ها'], ['text' => '💳 شارژها']],
            [['text' => '🎟 کد تخفیف'], ['text' => '👤 کاربران']],
            [['text' => '👥 تنظیمات زیرمجموعه'], ['text' => '📢 پیام همگانی']],
            [['text' => '⚙️ تنظیمات'], ['text' => '🔙 منوی کاربر']],
        ],
        'resize_keyboard' => true,
    ];
}

function admin_panel_open($chat) {
    send($chat, "🛠 <b>پنل مدیریت</b>\nاز دکمه‌های پایین صفحه استفاده کنید 👇", admin_menu_kb());
}

/* آیا متن یکی از دکمه‌های کیبورد پنل مدیریت است؟ */
function is_admin_menu_text($text) {
    return in_array($text, [
        '🛠 پنل مدیریت', '📊 آمار', '🧾 سفارش‌ها', '🗂 دسته‌بندی‌ها', '🌍 لوکیشن‌ها',
        '📦 پلن‌ها', '💳 شارژها', '🎟 کد تخفیف', '👤 کاربران', '👥 تنظیمات زیرمجموعه',
        '📢 پیام همگانی', '⚙️ تنظیمات', '🔙 منوی کاربر',
    ], true);
}

/* ---------- پیام‌های متنی ادمین (مراحل ورودی) ---------- */
function admin_handle_message($msg, $u) {
    $chat = $msg['chat']['id'];
    $tg   = $msg['from']['id'];
    $text = trim($msg['text'] ?? '');

    if ($text === '/admin' || $text === '🛠 پنل مدیریت') { set_step($tg, ''); set_temp($tg, []); admin_panel_open($chat); return; }
    if ($text === '/cancel') { set_step($tg, ''); set_temp($tg, []); send($chat, '✅ لغو شد.', admin_menu_kb()); return; }

    // دکمه‌های کیبوردی پنل مدیریت
    if (is_admin_menu_text($text)) {
        set_step($tg, ''); set_temp($tg, []);
        switch ($text) {
            case '📊 آمار':            admin_stats($chat, null); return;
            case '🧾 سفارش‌ها':         admin_list_orders($chat, null, 'pending', 0); return;
            case '🗂 دسته‌بندی‌ها':      admin_list_cats($chat, null); return;
            case '🌍 لوکیشن‌ها':        admin_list_locs($chat, null); return;
            case '📦 پلن‌ها':           admin_list_plans($chat, null); return;
            case '💳 شارژها':           admin_list_charges($chat, null); return;
            case '🎟 کد تخفیف':         admin_list_dcs($chat, null); return;
            case '👤 کاربران':          admin_users_home($chat, null); return;
            case '👥 تنظیمات زیرمجموعه': admin_ref($chat, null); return;
            case '📢 پیام همگانی':      set_step($tg, 'admin_broadcast'); send($chat, "📢 متن پیام همگانی را ارسال کنید:\n/cancel برای لغو"); return;
            case '⚙️ تنظیمات':          admin_settings($chat, null); return;
            case '🔙 منوی کاربر':       send($chat, "به منوی کاربری بازگشتید 👇", main_menu_kb($tg)); return;
        }
        return;
    }

    $step = $u['step'];
    $temp = get_temp($tg);

    switch ($step) {
        case 'admin_cat_add':
            db()->prepare("INSERT INTO categories(name, created_at) VALUES(?,?)")->execute([$text, now()]);
            set_step($tg, ''); send($chat, "✅ دسته‌بندی «{$text}» اضافه شد."); admin_list_cats($chat);
            break;

        case 'admin_loc_add':
            $a = array_map('trim', explode('|', $text));
            $name = $a[0]; $flag = $a[1] ?? '';
            db()->prepare("INSERT INTO locations(name, flag, created_at) VALUES(?,?,?)")->execute([$name, $flag, now()]);
            set_step($tg, ''); send($chat, "✅ لوکیشن «{$flag} {$name}» اضافه شد."); admin_list_locs($chat);
            break;

        case 'admin_plan_title':
            $temp['title'] = $text; set_temp($tg, $temp);
            set_step($tg, 'admin_plan_desc');
            send($chat, "📝 توضیحات پلن را وارد کنید (یا - برای خالی):");
            break;
        case 'admin_plan_desc':
            $temp['desc'] = ($text === '-') ? '' : $text; set_temp($tg, $temp);
            set_step($tg, 'admin_plan_price');
            send($chat, "💰 قیمت پلن را به تومان وارد کنید:");
            break;
        case 'admin_plan_price':
            $temp['price'] = (int)preg_replace('/\D/', '', $text); set_temp($tg, $temp);
            admin_plan_ask_panel($chat, $tg, 'a_pcpanel');
            break;
        case 'admin_plan_inbound':
            $temp['inbound_id'] = (int)preg_replace('/\D/', '', $text); set_temp($tg, $temp);
            set_step($tg, 'admin_plan_traffic');
            send($chat, "📦 حجم پلن را به <b>گیگابایت</b> وارد کنید:\n<b>۰ = نامحدود</b>");
            break;
        case 'admin_plan_traffic':
            $temp['traffic_gb'] = (int)preg_replace('/\D/', '', $text); set_temp($tg, $temp);
            set_step($tg, 'admin_plan_duration');
            send($chat, "⏳ مدت اعتبار پلن را به <b>روز</b> وارد کنید:\n<b>۰ = نامحدود</b>");
            break;
        case 'admin_plan_duration':
            $dur = (int)preg_replace('/\D/', '', $text);
            $pnl = (int)($temp['panel_id'] ?? 0);
            db()->prepare("INSERT INTO plans(category_id, location_id, title, description, price, panel_id, inbound_id, traffic_gb, duration_days, created_at) VALUES(?,?,?,?,?,?,?,?,?,?)")
                ->execute([$temp['cat'], $temp['loc'], $temp['title'], $temp['desc'], $temp['price'], $pnl, $temp['inbound_id'], $temp['traffic_gb'], $dur, now()]);
            set_step($tg, ''); set_temp($tg, []);
            $pn = $pnl > 0 ? panel_get($pnl) : null;
            $mode = $temp['inbound_id'] > 0 ? "تحویل خودکار (" . ($pn['name'] ?? 'پنل') . " / اینباند #{$temp['inbound_id']})" : "تحویل دستی";
            send($chat, "✅ پلن «{$temp['title']}» با قیمت " . fmt($temp['price']) . " تومان اضافه شد.\nنوع تحویل: {$mode}");
            admin_list_plans($chat);
            break;

        case 'admin_planset_inbound':
            $temp['inbound_id'] = (int)preg_replace('/\D/', '', $text); set_temp($tg, $temp);
            set_step($tg, 'admin_planset_traffic');
            send($chat, "📦 حجم پلن را به <b>گیگابایت</b> وارد کنید (۰ = نامحدود):");
            break;
        case 'admin_planset_traffic':
            $temp['traffic_gb'] = (int)preg_replace('/\D/', '', $text); set_temp($tg, $temp);
            set_step($tg, 'admin_planset_duration');
            send($chat, "⏳ مدت اعتبار را به <b>روز</b> وارد کنید (۰ = نامحدود):");
            break;
        case 'admin_planset_duration':
            $dur = (int)preg_replace('/\D/', '', $text);
            db()->prepare("UPDATE plans SET panel_id=?, inbound_id=?, traffic_gb=?, duration_days=? WHERE id=?")
                ->execute([(int)($temp['panel_id'] ?? 0), $temp['inbound_id'], $temp['traffic_gb'], $dur, $temp['plan_edit']]);
            $pid = $temp['plan_edit'];
            set_step($tg, ''); set_temp($tg, []);
            send($chat, "✅ تنظیمات تحویل خودکار پلن ذخیره شد.");
            admin_show_plan($chat, null, $pid);
            break;

        case 'admin_planprice':
            $newp = (int)preg_replace('/\D/', '', $text);
            $pid = (int)($temp['plan_edit'] ?? 0);
            db()->prepare("UPDATE plans SET price=? WHERE id=?")->execute([$newp, $pid]);
            set_step($tg, ''); set_temp($tg, []);
            send($chat, "✅ قیمت پلن به " . fmt($newp) . " تومان تغییر کرد.");
            admin_show_plan($chat, null, $pid);
            break;

        case 'admin_panel_addname':
            db()->prepare("INSERT INTO panels(name, is_active, created_at) VALUES(?,?,?)")->execute([$text, 1, now()]);
            $newPanelId = (int)db()->lastInsertId();
            set_step($tg, ''); set_temp($tg, []);
            send($chat, "✅ پنل «{$text}» ساخته شد. اکنون آدرس، یوزرنیم و پسورد آن را تنظیم کنید 👇");
            admin_panel_view($chat, null, $newPanelId);
            break;

        case 'admin_pset':
            $pid = (int)($temp['panel_id'] ?? 0);
            $field = $temp['field'] ?? '';
            $allowed = ['name', 'url', 'username', 'password', 'address', 'sub_url'];
            if ($pid > 0 && in_array($field, $allowed, true)) {
                db()->prepare("UPDATE panels SET $field=?, cookie='', cookie_time=0 WHERE id=?")->execute([trim($text), $pid]);
            }
            set_step($tg, ''); set_temp($tg, []);
            send($chat, "✅ مقدار ذخیره شد.");
            admin_panel_view($chat, null, $pid);
            break;

        case 'admin_txtset':
            $skey = $temp['skey'] ?? '';
            if ($skey && texts_find($skey)) { set_setting($skey, $text); }
            set_step($tg, ''); set_temp($tg, []);
            send($chat, "✅ متن ذخیره شد.");
            if ($skey) admin_text_item($chat, null, $skey);
            break;

        case 'admin_dc_add':
            // فرمت: CODE|نوع|مقدار|حداکثر|تاریخ انقضا|آیدی پلن‌ها
            $a = array_map('trim', explode('|', $text));
            if (count($a) < 3) { send($chat, "❌ فرمت اشتباه است. مثال:\n<code>OFF20|percent|20|100</code>"); break; }
            $code = strtoupper($a[0]); $type = $a[1]; $val = (float)$a[2];
            $max = isset($a[3]) ? (int)$a[3] : 0;
            $exp = $a[4] ?? '';
            $plan_ids = isset($a[5]) ? preg_replace('/[^0-9,]/', '', $a[5]) : '';
            $plan_ids = trim($plan_ids, ', ');
            if (!in_array($type, ['percent', 'amount'])) { send($chat, "❌ نوع باید percent یا amount باشد."); break; }
            try {
                db()->prepare("INSERT INTO discount_codes(code,type,value,max_uses,expire_at,plan_ids,created_at) VALUES(?,?,?,?,?,?,?)")
                    ->execute([$code, $type, $val, $max, $exp, $plan_ids, now()]);
                $scope = $plan_ids === '' ? 'همه پلن‌ها' : 'پلن‌های: ' . $plan_ids;
                set_step($tg, ''); send($chat, "✅ کد تخفیف «{$code}» ساخته شد.\n🎯 محدوده: {$scope}"); admin_list_dcs($chat);
            } catch (Exception $e) { send($chat, "❌ این کد قبلاً وجود دارد."); }
            break;

        case 'admin_set_setting':
            $key = $temp['key'] ?? '';
            if ($key) {
                $val = $text;
                if ($key === 'backup_interval_hours') { $val = (string)max(1, bk_int($text)); }
                set_setting($key, $val);
            }
            set_step($tg, ''); set_temp($tg, []);
            send($chat, "✅ مقدار ذخیره شد.");
            if ($key === 'backup_interval_hours') { admin_backup_menu($chat); }
            else { admin_settings($chat); }
            break;

        case 'admin_user_search':
            $q = ltrim($text, '@');
            $usr = null;
            if (ctype_digit($q)) { $usr = get_user((int)$q); }
            if (!$usr) {
                $st = db()->prepare("SELECT * FROM users WHERE username=? COLLATE NOCASE");
                $st->execute([$q]); $usr = $st->fetch();
            }
            set_step($tg, '');
            if (!$usr) { send($chat, "❌ کاربر یافت نشد."); break; }
            admin_show_user($chat, null, $usr['tg_id']);
            break;

        case 'admin_plan_order_user':
            $q = ltrim($text, '@');
            $usr = null;
            if (ctype_digit($q)) { $usr = get_user((int)$q); }
            if (!$usr) {
                $st = db()->prepare("SELECT * FROM users WHERE username=? COLLATE NOCASE");
                $st->execute([$q]); $usr = $st->fetch();
            }
            if (!$usr) { send($chat, "❌ کاربر یافت نشد. کاربر باید حداقل یک‌بار ربات را /start کرده باشد.\nدوباره آیدی/یوزرنیم را بفرستید یا /cancel بزنید."); break; }
            $pid = (int)($temp['po_plan'] ?? 0);
            set_step($tg, ''); set_temp($tg, []);
            admin_create_order_for_user($tg, $chat, null, $usr['tg_id'], $pid);
            break;

        case 'admin_ubal':
            $amount = (int)preg_replace('/[^\d\-]/', '', $text);
            $uid = $temp['uid'];
            add_balance($uid, $amount);
            add_tx($uid, $amount, 'admin', 'تنظیم موجودی توسط ادمین');
            set_step($tg, ''); set_temp($tg, []);
            send($chat, "✅ موجودی کاربر " . ($amount >= 0 ? 'افزایش' : 'کاهش') . " یافت (" . fmt($amount) . " تومان).");
            send($uid, "💰 موجودی کیف پول شما توسط ادمین تغییر کرد: " . fmt($amount) . " تومان");
            break;

        case 'admin_send_config':
            $oid = $temp['order_id'];
            $prev = db()->prepare("SELECT status FROM orders WHERE id=?"); $prev->execute([$oid]); $prow = $prev->fetch();
            $was = ($prow && $prow['status'] === 'delivered');
            deliver_order($oid, $msg['text'] ?? '');
            set_step($tg, ''); set_temp($tg, []);
            send($chat, $was
                ? "✅ کانفیگ سفارش #{$oid} تغییر کرد و نسخه‌ی جدید برای کاربر ارسال شد."
                : "✅ کانفیگ برای کاربر ارسال شد و سفارش #{$oid} تحویل داده شد.");
            break;

        case 'admin_cancel_amount':
            $oid = $temp['order_id'];
            $amount = (int)preg_replace('/\D/', '', $text);
            $ok = cancel_order($oid, $amount);
            set_step($tg, ''); set_temp($tg, []);
            if ($ok) send($chat, "✅ سفارش #{$oid} لغو شد و " . fmt($amount) . " تومان به کیف پول کاربر بازگردانده شد.");
            else send($chat, "این سفارش قبلاً لغو شده است.");
            break;

        case 'admin_broadcast':
            set_step($tg, '');
            $all = db()->query("SELECT tg_id FROM users WHERE is_blocked=0")->fetchAll();
            $ok = 0;
            foreach ($all as $row) {
                $r = send($row['tg_id'], $text);
                if (isset($r['ok']) && $r['ok']) $ok++;
                usleep(40000);
            }
            send($chat, "📢 پیام همگانی برای {$ok} کاربر ارسال شد.");
            break;

        default:
            send($chat, "از دکمه «🛠 پنل مدیریت» در منو استفاده کنید.");
    }
}

function deliver_order($oid, $config_text, $renew = false) {
    $st = db()->prepare("SELECT * FROM orders WHERE id=?");
    $st->execute([$oid]); $o = $st->fetch();
    if (!$o) return;
    $was_delivered = ($o['status'] === 'delivered');
    db()->prepare("UPDATE orders SET status='delivered', config_text=?, warn_time_at='', warn_vol_at='', depleted_at='', updated_at=? WHERE id=?")
        ->execute([$config_text, now(), $oid]);
    if ($was_delivered) {
        send($o['user_tg'], "🔄 <b>کانفیگ سفارش #{$oid} به‌روزرسانی شد!</b>\n\n📦 پلن: {$o['plan_title']}\n\n🔻 کانفیگ جدید شما:\n\n{$config_text}");
        return; // ویرایش است؛ پاداش زیرمجموعه دوباره داده نمی‌شود
    }
    if ($renew) {
        send($o['user_tg'], "🔄 <b>تمدید سفارش #{$oid} انجام شد!</b>\n\n📦 پلن: {$o['plan_title']}\n⏳ سرویس شما تمدید و حجم آن تازه‌سازی شد.\n\n🔻 کانفیگ شما (همان لینک قبلی):\n\n{$config_text}");
    } else {
        send($o['user_tg'], "✅ <b>سفارش #{$oid} آماده شد!</b>\n\n📦 پلن: {$o['plan_title']}\n\n🔻 کانفیگ شما:\n\n{$config_text}");
    }
    // پاداش زیرمجموعه‌گیری (در تحویل اول و تمدید)
    if (setting('referral_enabled', '1') === '1') {
        $buyer = get_user($o['user_tg']);
        if ($buyer && $buyer['referred_by']) {
            $percent = (float)setting('referral_percent', '0');
            if ($percent > 0) {
                $reward = round($o['price'] * $percent / 100);
                if ($reward > 0) {
                    add_balance($buyer['referred_by'], $reward);
                    add_tx($buyer['referred_by'], $reward, 'referral', 'پاداش خرید زیرمجموعه');
                    send($buyer['referred_by'], "🎉 پاداش زیرمجموعه: <b>" . fmt($reward) . "</b> تومان به کیف پول شما اضافه شد.");
                }
            }
        }
    }
}

/* ---------- کال‌بک‌های ادمین ---------- */
function admin_handle_callback($cb, $u) {
    $chat = $cb['message']['chat']['id'];
    $mid  = $cb['message']['message_id'];
    $tg   = $cb['from']['id'];
    $data = $cb['data'];
    $parts = explode(':', $data);
    $cmd = $parts[0];
    $p1 = $parts[1] ?? null;
    answer($cb['id']);

    switch ($cmd) {
        case 'a_back': admin_menu($chat, $mid); break;
        case 'a_stats': admin_stats($chat, $mid); break;

        /* دسته‌بندی‌ها */
        case 'a_cats': admin_list_cats($chat, $mid); break;
        case 'a_cat_add': set_step($tg, 'admin_cat_add'); edit($chat, $mid, "🗂 نام دسته‌بندی جدید را وارد کنید:\n/cancel برای لغو"); break;
        case 'a_cat_tg': db()->prepare("UPDATE categories SET is_active=1-is_active WHERE id=?")->execute([$p1]); admin_list_cats($chat, $mid); break;
        case 'a_cat_del': db()->prepare("DELETE FROM categories WHERE id=?")->execute([$p1]); db()->prepare("DELETE FROM plans WHERE category_id=?")->execute([$p1]); admin_list_cats($chat, $mid); break;

        /* لوکیشن‌ها */
        case 'a_locs': admin_list_locs($chat, $mid); break;
        case 'a_loc_add': set_step($tg, 'admin_loc_add'); edit($chat, $mid, "🌍 لوکیشن جدید را به صورت زیر وارد کنید:\n<code>نام | پرچم</code>\nمثال: <code>آلمان | 🇩🇪</code>\n/cancel برای لغو"); break;
        case 'a_loc_tg': db()->prepare("UPDATE locations SET is_active=1-is_active WHERE id=?")->execute([$p1]); admin_list_locs($chat, $mid); break;
        case 'a_loc_del': db()->prepare("DELETE FROM locations WHERE id=?")->execute([$p1]); db()->prepare("DELETE FROM plans WHERE location_id=?")->execute([$p1]); admin_list_locs($chat, $mid); break;

        /* پلن‌ها */
        case 'a_plans': admin_list_plans($chat, $mid); break;
        case 'a_planv': admin_show_plan($chat, $mid, $p1); break;
        case 'a_plan_add': admin_plan_pick_cat($chat, $mid); break;
        case 'a_pcat': $t = get_temp($tg); $t['cat'] = $p1; set_temp($tg, $t); admin_plan_pick_loc($chat, $mid); break;
        case 'a_ploc': $t = get_temp($tg); $t['loc'] = $p1; set_temp($tg, $t); set_step($tg, 'admin_plan_title'); edit($chat, $mid, "📦 عنوان پلن را وارد کنید:\n/cancel برای لغو"); break;
        case 'a_plan_tg': db()->prepare("UPDATE plans SET is_active=1-is_active WHERE id=?")->execute([$p1]); admin_show_plan($chat, $mid, $p1); break;
        case 'a_plan_hide': db()->prepare("UPDATE plans SET is_hidden=1-COALESCE(is_hidden,0) WHERE id=?")->execute([$p1]); admin_show_plan($chat, $mid, $p1); break;
        case 'a_plan_del': db()->prepare("DELETE FROM plans WHERE id=?")->execute([$p1]); admin_list_plans($chat, $mid); break;
        case 'a_planprice':
            set_step($tg, 'admin_planprice'); set_temp($tg, ['plan_edit' => $p1]);
            edit($chat, $mid, "💰 قیمت جدید پلن را به تومان وارد کنید:\n/cancel برای لغو");
            break;
        case 'a_plan_order':
            set_step($tg, 'admin_plan_order_user'); set_temp($tg, ['po_plan' => $p1]);
            edit($chat, $mid, "🎁 <b>ثبت این پلن برای کاربر</b>\n\nآیدی عددی یا یوزرنیم کاربر را ارسال کنید (کاربر باید قبلاً ربات را /start کرده باشد):\n/cancel برای لغو");
            break;
        case 'a_planset':
            set_temp($tg, ['plan_edit' => $p1]);
            admin_plan_ask_panel($chat, $tg, 'a_pspanel', $mid);
            break;
        case 'a_pcpanel': // انتخاب پنل هنگام ساخت پلن
            $t = get_temp($tg); $t['panel_id'] = (int)$p1; set_temp($tg, $t);
            if ((int)$p1 === 0) {
                // تحویل دستی → ساخت پلن بدون پنل
                $t['inbound_id'] = 0; $t['traffic_gb'] = 0; set_temp($tg, $t);
                db()->prepare("INSERT INTO plans(category_id, location_id, title, description, price, panel_id, inbound_id, traffic_gb, duration_days, created_at) VALUES(?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$t['cat'], $t['loc'], $t['title'], $t['desc'], $t['price'], 0, 0, 0, 0, now()]);
                set_step($tg, ''); set_temp($tg, []);
                send($chat, "✅ پلن «{$t['title']}» با قیمت " . fmt($t['price']) . " تومان (تحویل دستی) اضافه شد.");
                admin_list_plans($chat);
            } else {
                set_step($tg, 'admin_plan_inbound');
                send($chat, "🔌 آیدی اینباند این پنل را وارد کنید:\n(برای دیدن آیدی اینباندها: پنل را در «🔌 پنل‌ها» تست کنید)\n/cancel برای لغو");
            }
            break;
        case 'a_pspanel': // انتخاب پنل هنگام ویرایش تحویل پلن
            $t = get_temp($tg); $t['panel_id'] = (int)$p1; set_temp($tg, $t);
            $pid = (int)($t['plan_edit'] ?? 0);
            if ((int)$p1 === 0) {
                db()->prepare("UPDATE plans SET panel_id=0, inbound_id=0, traffic_gb=0, duration_days=0 WHERE id=?")->execute([$pid]);
                set_step($tg, ''); set_temp($tg, []);
                send($chat, "✅ پلن به حالت تحویل دستی (بدون پنل) تنظیم شد.");
                admin_show_plan($chat, null, $pid);
            } else {
                set_step($tg, 'admin_planset_inbound');
                send($chat, "🔌 آیدی اینباند این پنل را وارد کنید:\n/cancel برای لغو");
            }
            break;

        /* سفارش‌ها */
        case 'a_orders': admin_list_orders($chat, $mid, 'pending', 0); break;
        case 'a_orders_pending': admin_list_orders($chat, $mid, 'pending', (int)$p1); break;
        case 'a_orders_all': admin_list_orders($chat, $mid, 'all', (int)$p1); break;
        case 'a_order': admin_show_order($chat, $mid, $p1); break;
        case 'a_oappr':
            db()->prepare("UPDATE orders SET status='paid', updated_at=? WHERE id=?")->execute([now(), $p1]);
            if (try_auto_deliver($p1)) {
                edit($chat, $mid, "✅ پرداخت سفارش #{$p1} تایید و کانفیگ به‌صورت <b>خودکار</b> از پنل ارسال شد.", inline([[btn('🔙 بازگشت', 'a_orders')]]));
            } else {
                set_step($tg, 'admin_send_config'); set_temp($tg, ['order_id' => $p1]);
                send($chat, "✅ پرداخت سفارش #{$p1} تایید شد.\n\n✍️ اکنون متن کانفیگ را ارسال کنید تا برای کاربر فرستاده شود:\n(تحویل خودکار انجام نشد یا غیرفعال است)\n/cancel برای لغو");
            }
            break;
        case 'a_osend':
            set_step($tg, 'admin_send_config'); set_temp($tg, ['order_id' => $p1]);
            $st = db()->prepare("SELECT config_text, status FROM orders WHERE id=?"); $st->execute([$p1]); $oo = $st->fetch();
            if ($oo && $oo['status'] === 'delivered' && $oo['config_text']) {
                send($chat, "✏️ <b>تغییر کانفیگ سفارش #{$p1}</b>\n\n🔻 کانفیگ فعلی:\n{$oo['config_text']}\n\n👇 متن جدید کانفیگ را ارسال کنید تا جایگزین شود و برای کاربر ارسال گردد:\n/cancel برای لغو");
            } else {
                send($chat, "✍️ متن کانفیگ سفارش #{$p1} را ارسال کنید:\n/cancel برای لغو");
            }
            break;
        case 'a_ocancel': admin_cancel_menu($chat, $mid, $p1); break;
        case 'a_ocfull':
            $st = db()->prepare("SELECT price FROM orders WHERE id=?"); $st->execute([$p1]); $row = $st->fetch();
            if (cancel_order($p1, $row['price'] ?? 0)) edit($chat, $mid, "✅ سفارش #{$p1} لغو و کل مبلغ به کیف پول کاربر بازگردانده شد.", inline([[btn('🔙 بازگشت', 'a_orders')]]));
            else edit($chat, $mid, "این سفارش قبلاً لغو شده است.", inline([[btn('🔙 بازگشت', 'a_orders')]]));
            break;
        case 'a_ocnone':
            if (cancel_order($p1, 0)) edit($chat, $mid, "✅ سفارش #{$p1} بدون بازگشت وجه لغو شد.", inline([[btn('🔙 بازگشت', 'a_orders')]]));
            else edit($chat, $mid, "این سفارش قبلاً لغو شده است.", inline([[btn('🔙 بازگشت', 'a_orders')]]));
            break;
        case 'a_occustom':
            set_step($tg, 'admin_cancel_amount'); set_temp($tg, ['order_id' => $p1]);
            edit($chat, $mid, "✏️ مبلغی که باید به کیف پول کاربر بازگردد را وارد کنید (تومان):\nمثال: <code>30000</code>\n/cancel برای انصراف");
            break;

        /* شارژها */
        case 'a_charges': admin_list_charges($chat, $mid); break;
        case 'a_chappr':
            $st = db()->prepare("SELECT * FROM payments WHERE id=?"); $st->execute([$p1]); $pay = $st->fetch();
            if ($pay && $pay['status'] === 'pending') {
                db()->prepare("UPDATE payments SET status='approved', updated_at=? WHERE id=?")->execute([now(), $p1]);
                add_balance($pay['user_tg'], $pay['amount']);
                add_tx($pay['user_tg'], $pay['amount'], 'charge', 'شارژ کیف پول');
                send($pay['user_tg'], "✅ شارژ کیف پول شما به مبلغ " . fmt($pay['amount']) . " تومان تایید شد.");
                edit($chat, $mid, "✅ شارژ #{$p1} تایید و موجودی کاربر افزایش یافت.");
            }
            break;
        case 'a_chrej':
            $st = db()->prepare("SELECT * FROM payments WHERE id=?"); $st->execute([$p1]); $pay = $st->fetch();
            db()->prepare("UPDATE payments SET status='rejected', updated_at=? WHERE id=?")->execute([now(), $p1]);
            if ($pay) send($pay['user_tg'], "❌ درخواست شارژ کیف پول شما رد شد.");
            edit($chat, $mid, "درخواست شارژ #{$p1} رد شد.");
            break;

        /* کد تخفیف */
        case 'a_dcs': admin_list_dcs($chat, $mid); break;
        case 'a_dc_add': set_step($tg, 'admin_dc_add'); edit($chat, $mid, "🎟 کد تخفیف جدید را به صورت زیر وارد کنید:\n<code>کد|نوع|مقدار|حداکثر|تاریخ انقضا|آیدی پلن‌ها</code>\n\nنوع: percent یا amount\n• حداکثر استفاده: 0 = نامحدود\n• تاریخ انقضا: اختیاری (YYYY-MM-DD)\n• آیدی پلن‌ها: اختیاری — اگر خالی باشد برای <b>همه پلن‌ها</b>، در غیر این صورت فقط برای پلن‌های مشخص‌شده (با کاما)\n\nمثال همه پلن‌ها: <code>OFF20|percent|20|100</code>\nمثال یک پلن: <code>VIP10|percent|10|0||3</code>\nمثال چند پلن: <code>SALE|amount|15000|0|2025-12-30|3,5,8</code>\n\nℹ️ آیدی پلن‌ها در بخش «📦 پلن‌ها» کنار هر پلن نمایش داده می‌شود.\n/cancel برای لغو"); break;
        case 'a_dc_tg': db()->prepare("UPDATE discount_codes SET is_active=1-is_active WHERE id=?")->execute([$p1]); admin_list_dcs($chat, $mid); break;
        case 'a_dc_del': db()->prepare("DELETE FROM discount_codes WHERE id=?")->execute([$p1]); admin_list_dcs($chat, $mid); break;

        /* کاربران */
        case 'a_users': admin_users_home($chat, $mid); break;
        case 'a_users_list': admin_list_users($chat, $mid, (int)$p1); break;
        case 'a_user_search': set_step($tg, 'admin_user_search'); edit($chat, $mid, "👤 آیدی عددی یا یوزرنیم کاربر را ارسال کنید:\n/cancel برای لغو"); break;
        case 'a_user': admin_show_user($chat, $mid, $p1); break;
        case 'a_user_block': db()->prepare("UPDATE users SET is_blocked=1-is_blocked WHERE tg_id=?")->execute([$p1]); admin_show_user($chat, $mid, $p1); break;
        case 'a_user_bal': set_step($tg, 'admin_ubal'); set_temp($tg, ['uid' => $p1]); edit($chat, $mid, "💰 مبلغ افزایش/کاهش موجودی را وارد کنید (برای کاهش از - استفاده کنید):\nمثال: <code>50000</code> یا <code>-20000</code>\n/cancel برای لغو"); break;
        case 'a_user_order': admin_uo_pick_plan($chat, $mid, $p1); break;
        case 'a_uo_pick': admin_create_order_for_user($tg, $chat, $mid, $p1, ($parts[2] ?? 0)); break;

        /* پیام همگانی */
        case 'a_bc': set_step($tg, 'admin_broadcast'); edit($chat, $mid, "📢 متن پیام همگانی را ارسال کنید:\n/cancel برای لغو"); break;

        /* زیرمجموعه‌گیری */
        case 'a_ref': admin_ref($chat, $mid); break;
        case 'a_ref_toggle': set_setting('referral_enabled', setting('referral_enabled') === '1' ? '0' : '1'); admin_ref($chat, $mid); break;
        case 'a_ref_percent': set_step($tg, 'admin_set_setting'); set_temp($tg, ['key' => 'referral_percent']); edit($chat, $mid, "👥 درصد پاداش زیرمجموعه را وارد کنید (عدد):\n/cancel برای لغو"); break;

        /* تنظیمات */
        case 'a_settings': admin_settings($chat, $mid); break;
        case 'a_expiry': admin_expiry_config($chat, $mid); break;
        case 'a_set':
            $key = $p1;
            $labels = [
                'card_number' => 'شماره کارت', 'card_holder' => 'نام صاحب کارت',
                'support_username' => 'یوزرنیم پشتیبانی (بدون @)', 'channel_username' => 'یوزرنیم کانال جوین اجباری (بدون @)',
                'min_charge' => 'حداقل مبلغ شارژ', 'welcome_text' => 'متن خوش‌آمدگویی',
                'panel_url' => 'آدرس پنل (شامل مسیر پایه، مثل https://host:port/path)',
                'panel_user' => 'یوزرنیم پنل', 'panel_pass' => 'پسورد پنل',
                'panel_address' => 'آدرس/دامنه سرور برای لینک کانفیگ (مثل dns یا IP)',
                'panel_sub_url' => 'آدرس Subscription (اختیاری، مثل https://host:2096/sub)',
                'warn_days' => 'تعداد روز باقی‌مانده برای هشدار انقضا (عدد)',
                'warn_gb' => 'مقدار گیگ باقی‌مانده برای هشدار حجم (عدد، می‌تواند اعشاری باشد)',
                'del_grace_time' => 'مهلت حذف کانفیگ زمان‌دار پس از انقضا (روز)',
                'del_grace_vol' => 'مهلت حذف کانفیگ فقط‌حجمی پس از اتمام حجم (روز)',
            ];
            set_step($tg, 'admin_set_setting'); set_temp($tg, ['key' => $key]);
            $hint = "✏️ مقدار جدید برای «" . ($labels[$key] ?? $key) . "» را وارد کنید:\n/cancel برای لغو";
            if ($key === 'channel_username') {
                $hint = "📢 <b>یوزرنیم کانال جوین اجباری</b> را وارد کنید (بدون @):\n\n"
                      . "⚠️ <b>مهم:</b> ربات حتماً باید در این کانال <b>ادمین</b> باشد، در غیر این صورت بررسی عضویت کار نمی‌کند و کاربران نمی‌توانند از ربات استفاده کنند.\n\n"
                      . "/cancel برای لغو";
            }
            edit($chat, $mid, $hint);
            break;
        case 'a_toggle_join': set_setting('forced_join', setting('forced_join') === '1' ? '0' : '1'); admin_settings($chat, $mid); break;
        case 'a_toggle_card': set_setting('card_enabled', setting('card_enabled') === '1' ? '0' : '1'); admin_settings($chat, $mid); break;

        /* پنل‌های 3x-ui (چندتایی) */
        case 'a_panel': admin_panels_list($chat, $mid); break;
        case 'a_panels': admin_panels_list($chat, $mid); break;
        case 'a_toggle_pauto': set_setting('panel_auto', setting('panel_auto') === '1' ? '0' : '1'); admin_panels_list($chat, $mid); break;
        case 'a_panel_add':
            set_step($tg, 'admin_panel_addname'); set_temp($tg, []);
            edit($chat, $mid, "➕ <b>افزودن پنل جدید</b>\n\nیک نام دلخواه برای این پنل وارد کنید (مثلاً: آلمان ۱):\n/cancel برای لغو");
            break;
        case 'a_panelv': admin_panel_view($chat, $mid, $p1); break;
        case 'a_panel_tg': db()->prepare("UPDATE panels SET is_active=1-is_active WHERE id=?")->execute([$p1]); admin_panel_view($chat, $mid, $p1); break;
        case 'a_panel_del':
            db()->prepare("DELETE FROM panels WHERE id=?")->execute([$p1]);
            db()->prepare("UPDATE plans SET panel_id=0, inbound_id=0 WHERE panel_id=?")->execute([$p1]);
            admin_panels_list($chat, $mid);
            break;
        case 'a_pset':
            $field = $parts[2] ?? '';
            $labels = [
                'name' => 'نام پنل', 'url' => 'آدرس پنل (شامل مسیر پایه، مثل https://host:port/path)',
                'username' => 'یوزرنیم پنل', 'password' => 'پسورد پنل',
                'address' => 'آدرس/دامنه سرور برای لینک کانفیگ (مثل dns یا IP)',
                'sub_url' => 'آدرس Subscription (اختیاری، مثل https://host:2096/sub)',
            ];
            set_step($tg, 'admin_pset'); set_temp($tg, ['panel_id' => (int)$p1, 'field' => $field]);
            edit($chat, $mid, "✏️ مقدار جدید برای «" . ($labels[$field] ?? $field) . "» را وارد کنید:\n/cancel برای لغو");
            break;
        case 'a_ptest':
            edit($chat, $mid, "⏳ در حال تست اتصال به پنل...");
            list($ok, $report) = panel_test((int)$p1);
            edit($chat, $mid, ($ok ? "" : "❌ ") . $report, inline([[btn('🔙 بازگشت', 'a_panelv:' . $p1)]]));
            break;

        /* بکاپ و بازگردانی */
        case 'a_backup': admin_backup_menu($chat, $mid); break;
        case 'a_backup_now':
            edit($chat, $mid, "⏳ در حال ساخت و ارسال بکاپ...");
            list($ok, $why, $cnt) = backup_send_to_admins('📦 بکاپ دستی دیتابیس');
            edit($chat, $mid, ($ok ? "✅ بکاپ ساخته و برای <b>{$cnt}</b> ادمین ارسال شد." : "❌ " . $why), inline([[btn('🔙 بازگشت', 'a_backup')]]));
            break;
        case 'a_backup_toggle':
            set_setting('backup_auto', setting('backup_auto', '0') === '1' ? '0' : '1');
            admin_backup_menu($chat, $mid);
            break;
        case 'a_backup_interval':
            set_step($tg, 'admin_set_setting'); set_temp($tg, ['key' => 'backup_interval_hours']);
            edit($chat, $mid, "⏱ فاصله‌ی زمانی بکاپ خودکار را به <b>ساعت</b> وارد کنید (مثلاً 24):\n/cancel برای لغو");
            break;
        case 'a_backup_restore':
            set_step($tg, 'admin_restore_wait'); set_temp($tg, []);
            edit($chat, $mid, "📥 <b>بازگردانی دیتابیس</b>\n\nفایل بکاپ <code>.db</code> را همین‌جا به‌صورت <b>فایل/سند</b> ارسال (آپلود) کنید.\n\n⚠️ مطمئن شوید این فایل، بکاپ معتبر همین ربات است. پس از ارسال، قبل از جایگزینی از شما تایید گرفته می‌شود.\n/cancel برای لغو");
            break;
        case 'a_restore_do':
            list($ok, $why) = restore_do_pending();
            if ($ok) {
                edit($chat, $mid, "✅ <b>بازگردانی با موفقیت انجام شد.</b>\n\nدیتابیس جدید جایگزین شد و یک نسخه‌ی پشتیبان از دیتابیس قبلی در پوشه‌ی data ذخیره گردید.", inline([[btn('🔙 بازگشت', 'a_backup')]]));
            } else {
                edit($chat, $mid, "❌ بازگردانی ناموفق: " . $why, inline([[btn('🔙 بازگشت', 'a_backup')]]));
            }
            break;
        case 'a_restore_cancel':
            @unlink(backup_pending_path());
            edit($chat, $mid, "❌ بازگردانی لغو شد و فایل آپلودی حذف گردید.", inline([[btn('🔙 بازگشت', 'a_backup')]]));
            break;

        /* شخصی‌سازی متن‌ها و دکمه‌ها */
        case 'a_texts': admin_texts_menu($chat, $mid); break;
        case 'a_txtcat': admin_texts_cat($chat, $mid, (int)$p1); break;
        case 'a_txtedit': admin_text_item($chat, $mid, $p1); break;
        case 'a_txtset':
            $it = texts_find($p1);
            if (!$it) { edit($chat, $mid, "مورد یافت نشد.", inline([[btn('🔙 بازگشت', 'a_texts')]])); break; }
            set_step($tg, 'admin_txtset'); set_temp($tg, ['skey' => $p1]);
            edit($chat, $mid, "✏️ متن جدید برای «<b>{$it['title']}</b>» را ارسال کنید:\n\nℹ️ می‌توانید از اموجی استفاده کنید. تگ‌های HTML مثل &lt;b&gt; هم مجازند.\n/cancel برای لغو");
            break;
        case 'a_txtreset':
            $it = texts_find($p1);
            if ($it) { set_setting($p1, $it['default']); }
            admin_text_item($chat, $mid, $p1);
            break;
    }
}

/* ---------- آمار ---------- */
function admin_stats($chat, $mid = null) {
    $users = db()->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
    $orders = db()->query("SELECT COUNT(*) c FROM orders")->fetch()['c'];
    $delivered = db()->query("SELECT COUNT(*) c FROM orders WHERE status='delivered'")->fetch()['c'];
    $pending = db()->query("SELECT COUNT(*) c FROM orders WHERE status IN('pending_approval','paid')")->fetch()['c'];
    $revenue = db()->query("SELECT COALESCE(SUM(price),0) s FROM orders WHERE status='delivered'")->fetch()['s'];
    $charges = db()->query("SELECT COUNT(*) c FROM payments WHERE status='pending'")->fetch()['c'];
    $t = "📊 <b>آمار ربات</b>\n\n"
       . "👤 کاربران: <b>{$users}</b>\n"
       . "🧾 کل سفارش‌ها: <b>{$orders}</b>\n"
       . "✅ تحویل‌شده: <b>{$delivered}</b>\n"
       . "⏳ در انتظار رسیدگی: <b>{$pending}</b>\n"
       . "💳 شارژ در انتظار: <b>{$charges}</b>\n"
       . "💰 درآمد (تحویل‌شده): <b>" . fmt($revenue) . "</b> تومان";
    out($chat, $mid, $t, inline([[btn('🔙 بازگشت', 'a_back')]]));
}

/* ---------- دسته‌بندی ---------- */
function admin_list_cats($chat, $mid = null) {
    $rows = db()->query("SELECT * FROM categories ORDER BY id")->fetchAll();
    $kb = [[btn('➕ افزودن دسته‌بندی', 'a_cat_add')]];
    foreach ($rows as $r) {
        $st = $r['is_active'] ? '🟢' : '🔴';
        $kb[] = [btn("{$st} {$r['name']}", 'a_cat_tg:' . $r['id']), btn('🗑', 'a_cat_del:' . $r['id'])];
    }
    $kb[] = [btn('🔙 بازگشت', 'a_back')];
    $t = "🗂 <b>مدیریت دسته‌بندی‌ها</b>\n🟢 فعال / 🔴 غیرفعال — برای تغییر وضعیت روی نام بزنید.";
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

/* ---------- لوکیشن ---------- */
function admin_list_locs($chat, $mid = null) {
    $rows = db()->query("SELECT * FROM locations ORDER BY id")->fetchAll();
    $kb = [[btn('➕ افزودن لوکیشن', 'a_loc_add')]];
    foreach ($rows as $r) {
        $st = $r['is_active'] ? '🟢' : '🔴';
        $kb[] = [btn(trim("{$st} {$r['flag']} {$r['name']}"), 'a_loc_tg:' . $r['id']), btn('🗑', 'a_loc_del:' . $r['id'])];
    }
    $kb[] = [btn('🔙 بازگشت', 'a_back')];
    $t = "🌍 <b>مدیریت لوکیشن‌ها</b>";
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

/* ---------- پلن ---------- */
function admin_list_plans($chat, $mid = null) {
    $rows = db()->query("SELECT p.*, c.name cat, l.name loc, l.flag flag FROM plans p
        LEFT JOIN categories c ON c.id=p.category_id
        LEFT JOIN locations l ON l.id=p.location_id ORDER BY p.id DESC")->fetchAll();
    $kb = [[btn('➕ افزودن پلن', 'a_plan_add')]];
    foreach ($rows as $r) {
        $st = $r['is_active'] ? '🟢' : '🔴';
        $auto = (int)($r['inbound_id'] ?? 0) > 0 ? '⚡' : '';
        $hid = (int)($r['is_hidden'] ?? 0) ? '🔒' : '';
        $label = "{$st}{$auto}{$hid} #{$r['id']} {$r['title']} | {$r['flag']}{$r['loc']} | " . fmt($r['price']);
        $kb[] = [btn(mb_substr($label, 0, 60), 'a_planv:' . $r['id'])];
    }
    $kb[] = [btn('🔙 بازگشت', 'a_back')];
    $t = "📦 <b>مدیریت پلن‌ها</b>\nروی هر پلن بزنید تا جزئیات و تنظیمات آن باز شود.\n⚡ = تحویل خودکار | 🔒 = مخفی از کاربران | ℹ️ عدد بعد از # آیدی پلن است.";
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

function admin_show_plan($chat, $mid, $pid) {
    $st = db()->prepare("SELECT p.*, c.name cat, l.name loc, l.flag flag FROM plans p
        LEFT JOIN categories c ON c.id=p.category_id
        LEFT JOIN locations l ON l.id=p.location_id WHERE p.id=?");
    $st->execute([$pid]); $p = $st->fetch();
    if (!$p) { $mid ? edit($chat, $mid, "پلن یافت نشد.") : send($chat, "پلن یافت نشد."); return; }
    $inb = (int)($p['inbound_id'] ?? 0);
    $gb = (int)($p['traffic_gb'] ?? 0);
    $dur = (int)($p['duration_days'] ?? 0);
    $hidden = (int)($p['is_hidden'] ?? 0) === 1;
    $pnlId = (int)($p['panel_id'] ?? 0);
    $pnl = $pnlId > 0 ? panel_get($pnlId) : null;
    $pnlName = $pnl ? $pnl['name'] : 'بدون پنل (دستی)';
    $t = "📦 <b>پلن #{$p['id']} — {$p['title']}</b>\n\n"
       . "🗂 دسته: {$p['cat']}\n🌍 لوکیشن: {$p['flag']}{$p['loc']}\n"
       . "💰 قیمت: " . fmt($p['price']) . " تومان\n"
       . "وضعیت: " . ($p['is_active'] ? '🟢 فعال' : '🔴 غیرفعال') . "\n"
       . "نمایش: " . ($hidden ? '🔒 مخفی از کاربران (فقط ادمین)' : '👁 قابل مشاهده برای کاربران') . "\n\n"
       . "⚡ <b>تحویل خودکار (3x-ui):</b>\n"
       . "🖥 پنل: <b>{$pnlName}</b>\n"
       . "🔌 اینباند: " . ($inb > 0 ? "#{$inb}" : 'غیرفعال (تحویل دستی)') . "\n"
       . "📦 حجم: " . ($gb > 0 ? $gb . ' گیگ' : 'نامحدود') . "\n"
       . "⏳ مدت: " . ($dur > 0 ? $dur . ' روز' : 'نامحدود');
    $kb = [
        [btn('🎁 ثبت این پلن برای کاربر', 'a_plan_order:' . $pid)],
        [btn('💰 تغییر قیمت', 'a_planprice:' . $pid)],
        [btn($p['is_active'] ? '🔴 غیرفعال‌کردن' : '🟢 فعال‌کردن', 'a_plan_tg:' . $pid)],
        [btn($hidden ? '👁 نمایش به کاربران' : '🔒 مخفی‌کردن از کاربران', 'a_plan_hide:' . $pid)],
        [btn('⚡ تنظیم پنل/تحویل خودکار', 'a_planset:' . $pid)],
        [btn('🗑 حذف پلن', 'a_plan_del:' . $pid)],
        [btn('🔙 بازگشت', 'a_plans')],
    ];
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}
function admin_plan_pick_cat($chat, $mid) {
    $rows = db()->query("SELECT * FROM categories ORDER BY id")->fetchAll();
    if (!$rows) { edit($chat, $mid, "ابتدا یک دسته‌بندی بسازید.", inline([[btn('🔙 بازگشت', 'a_plans')]])); return; }
    $kb = [];
    foreach ($rows as $r) $kb[] = [btn($r['name'], 'a_pcat:' . $r['id'])];
    $kb[] = [btn('🔙 بازگشت', 'a_plans')];
    edit($chat, $mid, "🗂 دسته‌بندی پلن را انتخاب کنید:", inline($kb));
}
function admin_plan_pick_loc($chat, $mid) {
    $rows = db()->query("SELECT * FROM locations ORDER BY id")->fetchAll();
    if (!$rows) { edit($chat, $mid, "ابتدا یک لوکیشن بسازید.", inline([[btn('🔙 بازگشت', 'a_plans')]])); return; }
    $kb = [];
    foreach ($rows as $r) $kb[] = [btn(trim($r['flag'] . ' ' . $r['name']), 'a_ploc:' . $r['id'])];
    $kb[] = [btn('🔙 بازگشت', 'a_plans')];
    edit($chat, $mid, "🌍 لوکیشن پلن را انتخاب کنید:", inline($kb));
}

/* ---------- سفارش‌ها ---------- */
function admin_list_orders($chat, $mid, $filter = 'pending', $page = 0) {
    $per = 12; $off = $page * $per;
    if ($filter === 'all') {
        $rows = db()->query("SELECT * FROM orders ORDER BY id DESC LIMIT $per OFFSET $off")->fetchAll();
        $total = db()->query("SELECT COUNT(*) c FROM orders")->fetch()['c'];
        $title = "🧾 <b>همه سفارش‌ها</b> (کل: {$total})";
    } else {
        $rows = db()->query("SELECT * FROM orders WHERE status IN('pending_approval','paid') ORDER BY id DESC LIMIT $per OFFSET $off")->fetchAll();
        $total = db()->query("SELECT COUNT(*) c FROM orders WHERE status IN('pending_approval','paid')")->fetch()['c'];
        $title = "🧾 <b>سفارش‌های در انتظار رسیدگی</b> (کل: {$total})";
    }
    $kb = [];
    $kb[] = [
        btn(($filter === 'pending' ? '🔘 ' : '') . 'در انتظار', 'a_orders'),
        btn(($filter === 'all' ? '🔘 ' : '') . 'همه سفارش‌ها', 'a_orders_all:0'),
    ];
    foreach ($rows as $r) {
        $kb[] = [btn("#{$r['id']} | {$r['plan_title']} | " . status_label($r['status']), 'a_order:' . $r['id'])];
    }
    $nav = [];
    if ($page > 0) $nav[] = btn('⬅️ قبلی', 'a_orders_' . $filter . ':' . ($page - 1));
    if ($off + $per < $total) $nav[] = btn('بعدی ➡️', 'a_orders_' . $filter . ':' . ($page + 1));
    if ($nav) $kb[] = $nav;
    $kb[] = [btn('🔙 بازگشت', 'a_back')];
    $t = $title . "\n" . (!$rows ? "موردی وجود ندارد." : "یک سفارش را انتخاب کنید:");
    out($chat, $mid, $t, inline($kb));
}
function admin_show_order($chat, $mid, $oid) {
    $st = db()->prepare("SELECT * FROM orders WHERE id=?"); $st->execute([$oid]); $o = $st->fetch();
    if (!$o) { edit($chat, $mid, "سفارش یافت نشد."); return; }
    $usr = get_user($o['user_tg']);
    $un = $usr && $usr['username'] ? '@' . $usr['username'] : $o['user_tg'];
    $t = "🧾 <b>سفارش #{$o['id']}</b>\n👤 کاربر: {$un} ({$o['user_tg']})\n📦 پلن: {$o['plan_title']}\n💰 مبلغ: " . fmt($o['price']) . " تومان\n💳 روش: {$o['payment_method']}\n📌 وضعیت: " . status_label($o['status']) . "\n🕒 {$o['created_at']}";
    if ($o['status'] === 'delivered' && $o['config_text']) {
        $t .= "\n\n🔻 <b>کانفیگ فعلی:</b>\n" . $o['config_text'];
    }
    $kb = [];
    if ($o['status'] === 'pending_approval') {
        $kb[] = [btn('✅ تایید پرداخت', 'a_oappr:' . $oid)];
        if ($o['receipt_file_id']) send_photo($chat, $o['receipt_file_id'], "🧾 رسید سفارش #{$oid}");
    } elseif ($o['status'] === 'paid') {
        $kb[] = [btn('📤 ارسال کانفیگ', 'a_osend:' . $oid)];
    } elseif ($o['status'] === 'delivered') {
        $kb[] = [btn('✏️ تغییر کانفیگ', 'a_osend:' . $oid)];
    }
    if ($o['status'] !== 'rejected') {
        $kb[] = [btn('🚫 لغو سفارش', 'a_ocancel:' . $oid)];
    }
    $kb[] = [btn('🔙 بازگشت', 'a_orders')];
    edit($chat, $mid, $t, inline($kb));
}

/* منوی انتخاب میزان بازگشت وجه هنگام لغو */
function admin_cancel_menu($chat, $mid, $oid) {
    $st = db()->prepare("SELECT * FROM orders WHERE id=?"); $st->execute([$oid]); $o = $st->fetch();
    if (!$o) { edit($chat, $mid, "سفارش یافت نشد."); return; }
    if ($o['status'] === 'rejected') { edit($chat, $mid, "این سفارش قبلاً لغو شده است.", inline([[btn('🔙 بازگشت', 'a_order:' . $oid)]])); return; }
    $t = "🚫 <b>لغو سفارش #{$oid}</b>\n\n📦 {$o['plan_title']}\n💰 مبلغ سفارش: " . fmt($o['price']) . " تومان\n\nمیزان بازگشت وجه به کیف پول کاربر را انتخاب کنید:";
    $kb = [
        [btn('✅ لغو + بازگشت کامل (' . fmt($o['price']) . ' ت)', 'a_ocfull:' . $oid)],
        [btn('🚫 لغو بدون بازگشت وجه', 'a_ocnone:' . $oid)],
        [btn('✏️ لغو + بازگشت مبلغ دلخواه', 'a_occustom:' . $oid)],
        [btn('🔙 انصراف', 'a_order:' . $oid)],
    ];
    edit($chat, $mid, $t, inline($kb));
}

/* لغو سفارش با مبلغ بازگشتی مشخص */
function cancel_order($oid, $refund) {
    $st = db()->prepare("SELECT * FROM orders WHERE id=?"); $st->execute([$oid]); $o = $st->fetch();
    if (!$o || $o['status'] === 'rejected') return false;
    // حذف کانفیگ از پنل 3x-ui در صورت وجود اتصال
    if (function_exists('order_has_panel') && order_has_panel($o)) {
        panel_use_for_order($o);
        panel_del_client((int)$o['panel_inbound'], $o['panel_client_id']);
    }
    db()->prepare("UPDATE orders SET status='rejected', updated_at=? WHERE id=?")->execute([now(), $oid]);
    $refund = max(0, (int)$refund);
    if ($refund > 0) {
        add_balance($o['user_tg'], $refund);
        add_tx($o['user_tg'], $refund, 'refund', 'بازگشت وجه سفارش لغوشده #' . $oid);
    }
    $msg = "❌ سفارش #{$oid} شما لغو شد.";
    if ($refund > 0) $msg .= "\n💰 مبلغ " . fmt($refund) . " تومان به کیف پول شما بازگردانده شد.";
    send($o['user_tg'], $msg);
    return true;
}

/* ---------- شارژها ---------- */
function admin_list_charges($chat, $mid) {
    $rows = db()->query("SELECT * FROM payments WHERE status='pending' ORDER BY id DESC LIMIT 30")->fetchAll();
    out($chat, $mid, "💳 <b>درخواست‌های شارژ در انتظار</b>\n" . (!$rows ? "موردی نیست." : "رسیدها در پیام‌های زیر ارسال شدند 👇"), inline([[btn('🔙 بازگشت', 'a_back')]]));
    // ارسال رسیدها
    foreach ($rows as $r) {
        if ($r['receipt_file_id']) {
            $usr = get_user($r['user_tg']);
            $un = $usr && $usr['username'] ? '@' . $usr['username'] : $r['user_tg'];
            send_photo($chat, $r['receipt_file_id'], "💳 شارژ #{$r['id']} | {$un} | " . fmt($r['amount']) . " تومان",
                inline([[btn('✅ تایید', 'a_chappr:' . $r['id']), btn('❌ رد', 'a_chrej:' . $r['id'])]]));
        }
    }
}

/* ---------- کد تخفیف ---------- */
function admin_list_dcs($chat, $mid = null) {
    $rows = db()->query("SELECT * FROM discount_codes ORDER BY id DESC")->fetchAll();
    $kb = [[btn('➕ افزودن کد تخفیف', 'a_dc_add')]];
    foreach ($rows as $r) {
        $st = $r['is_active'] ? '🟢' : '🔴';
        $v = $r['type'] === 'percent' ? $r['value'] . '%' : fmt($r['value']) . 'ت';
        $scope = trim($r['plan_ids'] ?? '') === '' ? '' : ' 🎯[' . $r['plan_ids'] . ']';
        $kb[] = [btn("{$st} {$r['code']} ({$v}){$scope} {$r['used_count']}/" . ($r['max_uses'] ?: '∞'), 'a_dc_tg:' . $r['id']), btn('🗑', 'a_dc_del:' . $r['id'])];
    }
    $kb[] = [btn('🔙 بازگشت', 'a_back')];
    $t = "🎟 <b>مدیریت کدهای تخفیف</b>\n🎯[..] = مخصوص پلن‌های مشخص (آیدی پلن).";
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

/* ---------- کاربران ---------- */
function admin_users_home($chat, $mid = null) {
    $users = db()->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
    $blocked = db()->query("SELECT COUNT(*) c FROM users WHERE is_blocked=1")->fetch()['c'];
    $t = "👤 <b>مدیریت کاربران</b>\n\nتعداد کل: <b>{$users}</b>\nمسدود: <b>{$blocked}</b>";
    out($chat, $mid, $t, inline([
        [btn('📋 لیست همه کاربران', 'a_users_list:0')],
        [btn('🔍 جستجوی کاربر', 'a_user_search')],
        [btn('🔙 بازگشت', 'a_back')],
    ]));
}
function admin_list_users($chat, $mid, $page = 0) {
    $per = 10; $off = $page * $per;
    $rows = db()->query("SELECT * FROM users ORDER BY id DESC LIMIT $per OFFSET $off")->fetchAll();
    $total = db()->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
    $kb = [];
    foreach ($rows as $r) {
        $name = $r['first_name'] ?: 'کاربر';
        $uname = $r['username'] ? '@' . $r['username'] : $r['tg_id'];
        $blk = $r['is_blocked'] ? '🚫 ' : '';
        $kb[] = [btn("{$blk}{$name} | {$uname} | " . fmt($r['balance']) . "ت", 'a_user:' . $r['tg_id'])];
    }
    $nav = [];
    if ($page > 0) $nav[] = btn('⬅️ قبلی', 'a_users_list:' . ($page - 1));
    if ($off + $per < $total) $nav[] = btn('بعدی ➡️', 'a_users_list:' . ($page + 1));
    if ($nav) $kb[] = $nav;
    $kb[] = [btn('🔙 بازگشت', 'a_users')];
    $t = "📋 <b>لیست کاربران</b> (کل: {$total}) — صفحه " . ($page + 1) . "\n" . (!$rows ? "کاربری یافت نشد." : "برای مدیریت، روی کاربر بزنید:");
    edit($chat, $mid, $t, inline($kb));
}
function admin_show_user($chat, $mid, $uid) {
    $usr = get_user($uid);
    if (!$usr) { $mid ? edit($chat, $mid, "کاربر یافت نشد.") : send($chat, "کاربر یافت نشد."); return; }
    $orders = db()->prepare("SELECT COUNT(*) c FROM orders WHERE user_tg=?"); $orders->execute([$uid]);
    $t = "👤 <b>اطلاعات کاربر</b>\n\n🆔 آیدی: <code>{$usr['tg_id']}</code>\n📛 نام: {$usr['first_name']}\n🔖 یوزرنیم: " . ($usr['username'] ? '@' . $usr['username'] : '—') . "\n💰 موجودی: " . fmt($usr['balance']) . " تومان\n🧾 سفارش‌ها: " . $orders->fetch()['c'] . "\n🚫 وضعیت: " . ($usr['is_blocked'] ? 'مسدود' : 'فعال');
    $kb = [
        [btn('🎁 ثبت سفارش / فعال‌سازی پلن', 'a_user_order:' . $usr['tg_id'])],
        [btn('💰 تنظیم موجودی', 'a_user_bal:' . $usr['tg_id'])],
        [btn($usr['is_blocked'] ? '✅ رفع مسدودی' : '🚫 مسدود کردن', 'a_user_block:' . $usr['tg_id'])],
        [btn('🔙 بازگشت', 'a_users')],
    ];
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

/* ---------- ثبت سفارش/فعال‌سازی پلن برای کاربر توسط ادمین ---------- */
function admin_uo_pick_plan($chat, $mid, $uid) {
    $usr = get_user($uid);
    if (!$usr) { edit($chat, $mid, "کاربر یافت نشد."); return; }
    $rows = db()->query("SELECT p.*, l.name loc, l.flag flag FROM plans p
        LEFT JOIN locations l ON l.id=p.location_id
        WHERE p.is_active=1 ORDER BY p.id DESC")->fetchAll();
    if (!$rows) { edit($chat, $mid, "❌ هیچ پلن فعالی برای ثبت وجود ندارد. ابتدا یک پلن بسازید.", inline([[btn('🔙 بازگشت', 'a_user:' . $uid)]])); return; }
    $un = $usr['username'] ? '@' . $usr['username'] : $uid;
    $kb = [];
    foreach ($rows as $r) {
        $auto = (int)($r['inbound_id'] ?? 0) > 0 ? '⚡' : '';
        $hid = (int)($r['is_hidden'] ?? 0) ? '🔒' : '';
        $label = "{$auto}{$hid}#{$r['id']} {$r['title']} | {$r['flag']}{$r['loc']} | " . fmt($r['price']);
        $kb[] = [btn(mb_substr($label, 0, 60), 'a_uo_pick:' . $uid . ':' . $r['id'])];
    }
    $kb[] = [btn('🔙 بازگشت', 'a_user:' . $uid)];
    $t = "🎁 <b>ثبت سفارش برای کاربر</b>\n👤 {$un}\n\nپلنی که می‌خواهید برای این کاربر فعال کنید را انتخاب کنید:\n⚡ = تحویل خودکار از پنل 3x-ui | 🔒 = پلن مخفی";
    edit($chat, $mid, $t, inline($kb));
}

function admin_create_order_for_user($admin_tg, $chat, $mid, $uid, $plan_id) {
    $p = get_plan($plan_id);
    if (!$p) { out($chat, $mid, "❌ پلن یافت نشد.", inline([[btn('🔙 بازگشت', 'a_user:' . $uid)]])); return; }
    $usr = get_user($uid);
    if (!$usr) { out($chat, $mid, "❌ کاربر یافت نشد."); return; }
    $st = db()->prepare("INSERT INTO orders(user_tg, plan_id, plan_title, price, status, payment_method, created_at, updated_at)
        VALUES(?,?,?,?,?,?,?,?)");
    $st->execute([$uid, $p['id'], $p['title'], $p['price'], 'paid', 'admin', now(), now()]);
    $oid = db()->lastInsertId();
    $un = $usr['username'] ? '@' . $usr['username'] : $uid;
    // تلاش برای تحویل خودکار از پنل 3x-ui
    if (try_auto_deliver($oid)) {
        out($chat, $mid, "✅ سفارش <b>#{$oid}</b> برای کاربر {$un} ثبت و کانفیگ به‌صورت <b>خودکار</b> از پنل ارسال شد.", inline([[btn('🔙 بازگشت', 'a_user:' . $uid)]]));
        return;
    }
    // تحویل دستی: درخواست متن کانفیگ از ادمین (همان جریان ارسال کانفیگ سفارش‌ها)
    set_step($admin_tg, 'admin_send_config'); set_temp($admin_tg, ['order_id' => $oid]);
    out($chat, $mid, "✅ سفارش <b>#{$oid}</b> برای کاربر {$un} ثبت شد.", inline([[btn('🔙 بازگشت', 'a_user:' . $uid)]]));
    send($chat, "✍️ اکنون متن کانفیگ سفارش #{$oid} را ارسال کنید تا برای کاربر فرستاده و سفارش تحویل شود:\n(تحویل خودکار انجام نشد یا اینباند این پلن تنظیم نشده است)\n/cancel برای لغو");
}

/* ---------- زیرمجموعه‌گیری ---------- */
function admin_ref($chat, $mid = null) {
    $en = setting('referral_enabled', '1') === '1';
    $t = "👥 <b>تنظیمات زیرمجموعه‌گیری</b>\n\nوضعیت: " . ($en ? '🟢 فعال' : '🔴 غیرفعال') . "\nدرصد پاداش: <b>" . setting('referral_percent') . "%</b>\n\nپاداش هنگام تحویل خرید زیرمجموعه به کیف پول دعوت‌کننده اضافه می‌شود.";
    $kb = [
        [btn($en ? '🔴 غیرفعال کردن' : '🟢 فعال کردن', 'a_ref_toggle')],
        [btn('✏️ تغییر درصد پاداش', 'a_ref_percent')],
        [btn('🔙 بازگشت', 'a_back')],
    ];
    out($chat, $mid, $t, inline($kb));
}

/* ---------- تنظیمات ---------- */
function admin_settings($chat, $mid = null) {
    $join = setting('forced_join', '0') === '1';
    $card = setting('card_enabled', '1') === '1';
    $t = "⚙️ <b>تنظیمات ربات</b>\n\n"
       . "💳 شماره کارت: <code>" . setting('card_number') . "</code>\n"
       . "👤 صاحب کارت: " . setting('card_holder') . "\n"
       . "☎️ پشتیبانی: @" . setting('support_username') . "\n"
       . "📢 کانال جوین: " . (setting('channel_username') ? '@' . setting('channel_username') : '—') . "\n"
       . "🔒 جوین اجباری: " . ($join ? '🟢 فعال' : '🔴 غیرفعال') . "\n"
       . "🏧 درگاه کارت‌به‌کارت: " . ($card ? '🟢 فعال' : '🔴 غیرفعال (پرداخت با پشتیبانی)') . "\n"
       . "💵 حداقل شارژ: " . fmt(setting('min_charge')) . " تومان";
    $kb = [
        [btn('💳 شماره کارت', 'a_set:card_number'), btn('👤 صاحب کارت', 'a_set:card_holder')],
        [btn('☎️ پشتیبانی', 'a_set:support_username'), btn('💵 حداقل شارژ', 'a_set:min_charge')],
        [btn('📢 کانال جوین', 'a_set:channel_username'), btn($join ? '🔴 خاموش‌کردن جوین' : '🟢 روشن‌کردن جوین', 'a_toggle_join')],
        [btn($card ? '🔴 خاموش‌کردن کارت‌به‌کارت' : '🟢 روشن‌کردن کارت‌به‌کارت', 'a_toggle_card')],
        [btn('🔌 پنل‌های 3x-ui (چندتایی / تحویل خودکار)', 'a_panel')],
        [btn('⏰ هشدار و انقضای خودکار', 'a_expiry')],
        [btn('💾 بکاپ و بازگردانی', 'a_backup')],
        [btn('✏️ متن‌ها و دکمه‌های کاربر', 'a_texts')],
        [btn('📝 متن خوش‌آمد', 'a_set:welcome_text')],
        [btn('🔙 بازگشت', 'a_back')],
    ];
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

function admin_expiry_config($chat, $mid = null) {
    $t = "⏰ <b>هشدار و انقضای خودکار</b>\n\n"
       . "🔔 هشدار زمان: وقتی <b>" . setting('warn_days', '2') . " روز</b> به پایان مانده باشد\n"
       . "🔔 هشدار حجم: وقتی <b>" . setting('warn_gb', '1') . " گیگ</b> حجم باقی مانده باشد\n\n"
       . "🗑 حذف کانفیگ‌های زمان‌دار: <b>" . setting('del_grace_time', '1') . " روز</b> پس از انقضا\n"
       . "🗑 حذف کانفیگ‌های فقط‌حجمی: <b>" . setting('del_grace_vol', '7') . " روز</b> پس از اتمام حجم\n\n"
       . "ℹ️ این بررسی به‌صورت زمان‌بندی‌شده (کرون) اجرا می‌شود. کانفیگ‌های فقط‌حجمی (بدون زمان) فقط هشدار حجم دریافت می‌کنند.";
    $kb = [
        [btn('🔔 روز هشدار', 'a_set:warn_days'), btn('🔔 گیگ هشدار', 'a_set:warn_gb')],
        [btn('🗑 مهلت حذف (زمان‌دار)', 'a_set:del_grace_time')],
        [btn('🗑 مهلت حذف (فقط‌حجمی)', 'a_set:del_grace_vol')],
        [btn('🔙 بازگشت', 'a_settings')],
    ];
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

/* ---------- مدیریت پنل‌های 3x-ui (چندتایی) ---------- */
function admin_panels_list($chat, $mid = null) {
    $auto = setting('panel_auto', '0') === '1';
    $rows = db()->query("SELECT * FROM panels ORDER BY id")->fetchAll();
    $t = "🔌 <b>پنل‌های 3x-ui</b>\n\n"
       . "⚡ تحویل خودکار (سراسری): " . ($auto ? '🟢 فعال' : '🔴 غیرفعال') . "\n"
       . "تعداد پنل‌ها: <b>" . count($rows) . "</b>\n\n"
       . (count($rows) ? "برای مدیریت هر پنل روی آن بزنید. هر پلن می‌تواند از یک پنل دلخواه استفاده کند." : "هنوز پنلی اضافه نکرده‌اید. با دکمه زیر یک پنل اضافه کنید.");
    $kb = [[btn('➕ افزودن پنل', 'a_panel_add')]];
    foreach ($rows as $r) {
        $st = $r['is_active'] ? '🟢' : '🔴';
        $kb[] = [btn("{$st} {$r['name']}", 'a_panelv:' . $r['id'])];
    }
    $kb[] = [btn($auto ? '🔴 خاموش‌کردن تحویل خودکار' : '🟢 روشن‌کردن تحویل خودکار', 'a_toggle_pauto')];
    $kb[] = [btn('🔙 بازگشت', 'a_settings')];
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

function admin_panel_view($chat, $mid, $pid) {
    $p = panel_get($pid);
    if (!$p) { out($chat, $mid, "پنل یافت نشد.", inline([[btn('🔙 بازگشت', 'a_panels')]])); return; }
    $planCount = (int)db()->query("SELECT COUNT(*) FROM plans WHERE panel_id=" . (int)$pid)->fetchColumn();
    $t = "🔌 <b>پنل: {$p['name']}</b>\n\n"
       . "وضعیت: " . ($p['is_active'] ? '🟢 فعال' : '🔴 غیرفعال') . "\n"
       . "🌐 آدرس: " . ($p['url'] ? "<code>{$p['url']}</code>" : '—') . "\n"
       . "👤 یوزرنیم: " . ($p['username'] ?: '—') . "\n"
       . "🔑 پسورد: " . ($p['password'] ? '✅ تنظیم‌شده' : '—') . "\n"
       . "🖥 آدرس سرور (برای لینک): " . ($p['address'] ?: 'خودکار از آدرس پنل') . "\n"
       . "🔗 Subscription: " . ($p['sub_url'] ?: '—') . "\n"
       . "📦 پلن‌های متصل: <b>{$planCount}</b>\n\n"
       . "ℹ️ آدرس پنل باید شامل مسیر پایه باشد، مثل:\n<code>https://example.com:54321/MyPath</code>";
    $kb = [
        [btn('✏️ نام', 'a_pset:' . $pid . ':name'), btn('🌐 آدرس پنل', 'a_pset:' . $pid . ':url')],
        [btn('👤 یوزرنیم', 'a_pset:' . $pid . ':username'), btn('🔑 پسورد', 'a_pset:' . $pid . ':password')],
        [btn('🖥 آدرس سرور لینک', 'a_pset:' . $pid . ':address')],
        [btn('🔗 آدرس Subscription', 'a_pset:' . $pid . ':sub_url')],
        [btn('🧪 تست اتصال + لیست اینباند', 'a_ptest:' . $pid)],
        [btn($p['is_active'] ? '🔴 غیرفعال‌کردن' : '🟢 فعال‌کردن', 'a_panel_tg:' . $pid)],
        [btn('🗑 حذف پنل', 'a_panel_del:' . $pid)],
        [btn('🔙 بازگشت', 'a_panels')],
    ];
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

/* نمایش انتخاب پنل برای ساخت/ویرایش پلن — $cb یکی از a_pcpanel یا a_pspanel */
function admin_plan_ask_panel($chat, $tg, $cb, $mid = null) {
    $rows = db()->query("SELECT * FROM panels WHERE is_active=1 ORDER BY id")->fetchAll();
    set_step($tg, ($cb === 'a_pcpanel') ? 'admin_plan_panel' : 'admin_planset_panel');
    $kb = [];
    foreach ($rows as $r) $kb[] = [btn('🔌 ' . $r['name'], $cb . ':' . $r['id'])];
    $kb[] = [btn('✋ تحویل دستی (بدون پنل)', $cb . ':0')];
    $t = "🔌 <b>انتخاب پنل تحویل</b>\n\nاین پلن از کدام پنل تحویل داده شود؟\n"
       . (count($rows) ? "" : "⚠️ هیچ پنل فعالی ندارید؛ می‌توانید «تحویل دستی» را انتخاب کنید یا ابتدا از «🔌 پنل‌ها» یک پنل بسازید.\n")
       . "(برای تحویل دستی توسط ادمین، «بدون پنل» را بزنید)";
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

/* ---------- شخصی‌سازی متن‌ها و دکمه‌ها ---------- */
function admin_texts_menu($chat, $mid = null) {
    $reg = texts_registry();
    $t = "✏️ <b>متن‌ها و دکمه‌های کاربر</b>\n\n"
       . "از این بخش می‌توانید متن دکمه‌های منوی کاربر و پیام‌هایی که به کاربر نمایش داده می‌شود را تغییر دهید.\n\nیک دسته را انتخاب کنید:";
    $kb = [];
    foreach ($reg as $i => $cat) {
        $kb[] = [btn($cat['cat'] . ' (' . count($cat['items']) . ')', 'a_txtcat:' . $i)];
    }
    $kb[] = [btn('🔙 بازگشت', 'a_settings')];
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

function admin_texts_cat($chat, $mid, $ci) {
    $reg = texts_registry();
    if (!isset($reg[$ci])) { out($chat, $mid, "دسته یافت نشد.", inline([[btn('🔙 بازگشت', 'a_texts')]])); return; }
    $cat = $reg[$ci];
    $kb = [];
    foreach ($cat['items'] as $it) {
        $cur = lbl($it['key'], $it['default']);
        $preview = mb_substr(trim(preg_replace('/\s+/', ' ', $cur)), 0, 22);
        $kb[] = [btn($it['title'] . ' › ' . $preview, 'a_txtedit:' . $it['key'])];
    }
    $kb[] = [btn('🔙 بازگشت', 'a_texts')];
    $t = "✏️ <b>{$cat['cat']}</b>\n\nروی هر مورد بزنید تا متن آن را ببینید و تغییر دهید:";
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

function admin_text_item($chat, $mid, $skey) {
    $it = texts_find($skey);
    if (!$it) { out($chat, $mid, "مورد یافت نشد.", inline([[btn('🔙 بازگشت', 'a_texts')]])); return; }
    $cur = lbl($skey, $it['default']);
    $isDefault = (setting($skey, null) === null || setting($skey, null) === '' || setting($skey, null) === $it['default']);
    $t = "✏️ <b>{$it['title']}</b>\n\n"
       . "مقدار فعلی:\n<code>" . htmlspecialchars($cur, ENT_QUOTES) . "</code>\n\n"
       . "وضعیت: " . ($isDefault ? '🔵 پیش‌فرض' : '🟢 سفارشی‌شده');
    $kb = [
        [btn('✏️ تغییر متن', 'a_txtset:' . $skey)],
        [btn('♻️ بازگردانی به پیش‌فرض', 'a_txtreset:' . $skey)],
        [btn('🔙 بازگشت', 'a_texts')],
    ];
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

/* ---------- بکاپ و بازگردانی ---------- */
function admin_backup_menu($chat, $mid = null) {
    $auto     = setting('backup_auto', '0') === '1';
    $interval = bk_int(setting('backup_interval_hours', '24'));
    if ($interval < 1) $interval = 24;
    $last  = setting('backup_last_at', '');
    $size  = is_file(DB_PATH) ? bytes_human(filesize(DB_PATH)) : '—';
    $stats = backup_db_stats();
    $t = "💾 <b>بکاپ و بازگردانی دیتابیس</b>\n\n"
       . "💽 حجم دیتابیس فعلی: <b>{$size}</b>\n"
       . "👤 کاربران: <b>{$stats['users']}</b> | 🧾 سفارش‌ها: <b>{$stats['orders']}</b> | 📦 پلن‌ها: <b>{$stats['plans']}</b>\n\n"
       . "🔄 بکاپ خودکار: " . ($auto ? '🟢 فعال' : '🔴 غیرفعال') . "\n"
       . "⏱ فاصله‌ی زمانی: هر <b>{$interval}</b> ساعت\n"
       . "🕒 آخرین بکاپ خودکار: " . ($last ?: '—') . "\n\n"
       . "ℹ️ فایل بکاپ برای همه‌ی ادمین‌ها در همین‌جا ارسال می‌شود.\n"
       . "ℹ️ بکاپ خودکار توسط زمان‌بند سرور (cron / همان فایل cron.php) اجرا می‌گردد.";
    $kb = [
        [btn('📤 دریافت بکاپ همین حالا', 'a_backup_now')],
        [btn($auto ? '🔴 خاموش‌کردن بکاپ خودکار' : '🟢 روشن‌کردن بکاپ خودکار', 'a_backup_toggle')],
        [btn('⏱ تنظیم فاصله‌ی زمانی', 'a_backup_interval')],
        [btn('📥 بازگردانی از فایل', 'a_backup_restore')],
        [btn('🔙 بازگشت', 'a_settings')],
    ];
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

/* دریافت فایل بکاپ آپلودی ادمین و آماده‌سازی تایید بازگردانی */
function admin_handle_restore_document($msg, $u) {
    $chat = $msg['chat']['id'];
    $tg   = $msg['from']['id'];
    $doc  = $msg['document'];
    $size = (int)($doc['file_size'] ?? 0);
    if ($size > 18 * 1024 * 1024) {
        send($chat, "❌ حجم فایل بیش از حد مجاز ربات برای دانلود (حدود ۲۰ مگابایت) است.");
        return;
    }
    send($chat, "⏳ در حال دریافت و بررسی فایل بکاپ...");
    $pending = backup_pending_path();
    @unlink($pending);
    if (!tg_download_file($doc['file_id'], $pending)) {
        send($chat, "❌ دانلود فایل ناموفق بود. دوباره تلاش کنید یا /cancel بزنید.");
        return;
    }
    list($ok, $why) = backup_validate_sqlite($pending);
    if (!$ok) {
        @unlink($pending);
        send($chat, "❌ {$why}\n\nلطفاً فایل بکاپ صحیح این ربات را ارسال کنید یا /cancel بزنید.");
        return;
    }
    set_step($tg, ''); set_temp($tg, []);
    $new = backup_stats_of($pending);
    $cur = backup_db_stats();
    $t = "⚠️ <b>تایید بازگردانی دیتابیس</b>\n\n"
       . "فایل بکاپ معتبر است. مقایسه‌ی محتوا:\n\n"
       . "📥 <b>فایل آپلودی:</b>\n👤 کاربران: <b>{$new['users']}</b> | 🧾 سفارش‌ها: <b>{$new['orders']}</b> | 📦 پلن‌ها: <b>{$new['plans']}</b>\n\n"
       . "💽 <b>دیتابیس فعلی:</b>\n👤 کاربران: <b>{$cur['users']}</b> | 🧾 سفارش‌ها: <b>{$cur['orders']}</b> | 📦 پلن‌ها: <b>{$cur['plans']}</b>\n\n"
       . "🛑 با تایید، دیتابیس فعلی به‌طور کامل با فایل بالا <b>جایگزین</b> می‌شود.\n"
       . "(یک نسخه‌ی پشتیبان از دیتابیس فعلی هم به‌صورت خودکار ذخیره خواهد شد.)";
    $kb = inline([
        [btn('✅ بله، جایگزین کن', 'a_restore_do')],
        [btn('❌ انصراف', 'a_restore_cancel')],
    ]);
    send($chat, $t, $kb);
}
