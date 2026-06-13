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
            $price = (int)preg_replace('/\D/', '', $text);
            db()->prepare("INSERT INTO plans(category_id, location_id, title, description, price, created_at) VALUES(?,?,?,?,?,?)")
                ->execute([$temp['cat'], $temp['loc'], $temp['title'], $temp['desc'], $price, now()]);
            set_step($tg, ''); set_temp($tg, []);
            send($chat, "✅ پلن «{$temp['title']}» با قیمت " . fmt($price) . " تومان اضافه شد.");
            admin_list_plans($chat);
            break;

        case 'admin_dc_add':
            // فرمت: CODE|percent|20|100|2025-12-30
            $a = array_map('trim', explode('|', $text));
            if (count($a) < 3) { send($chat, "❌ فرمت اشتباه است. مثال:\n<code>OFF20|percent|20|100</code>"); break; }
            $code = strtoupper($a[0]); $type = $a[1]; $val = (float)$a[2];
            $max = isset($a[3]) ? (int)$a[3] : 0;
            $exp = $a[4] ?? '';
            if (!in_array($type, ['percent', 'amount'])) { send($chat, "❌ نوع باید percent یا amount باشد."); break; }
            try {
                db()->prepare("INSERT INTO discount_codes(code,type,value,max_uses,expire_at,created_at) VALUES(?,?,?,?,?,?)")
                    ->execute([$code, $type, $val, $max, $exp, now()]);
                set_step($tg, ''); send($chat, "✅ کد تخفیف «{$code}» ساخته شد."); admin_list_dcs($chat);
            } catch (Exception $e) { send($chat, "❌ این کد قبلاً وجود دارد."); }
            break;

        case 'admin_set_setting':
            $key = $temp['key'] ?? '';
            if ($key) { set_setting($key, $text); }
            set_step($tg, ''); set_temp($tg, []);
            send($chat, "✅ مقدار ذخیره شد.");
            admin_settings($chat);
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
            deliver_order($oid, $msg['text'] ?? '');
            set_step($tg, ''); set_temp($tg, []);
            send($chat, "✅ اوت‌باند برای کاربر ارسال شد و سفارش #{$oid} تحویل داده شد.");
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

function deliver_order($oid, $config_text) {
    $st = db()->prepare("SELECT * FROM orders WHERE id=?");
    $st->execute([$oid]); $o = $st->fetch();
    if (!$o) return;
    db()->prepare("UPDATE orders SET status='delivered', config_text=?, updated_at=? WHERE id=?")
        ->execute([$config_text, now(), $oid]);
    send($o['user_tg'], "✅ <b>سفارش #{$oid} آماده شد!</b>\n\n📦 پلن: {$o['plan_title']}\n\n🔻 اوت‌باند شما:\n\n{$config_text}");
    // پاداش زیرمجموعه‌گیری
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
        case 'a_plan_add': admin_plan_pick_cat($chat, $mid); break;
        case 'a_pcat': $t = get_temp($tg); $t['cat'] = $p1; set_temp($tg, $t); admin_plan_pick_loc($chat, $mid); break;
        case 'a_ploc': $t = get_temp($tg); $t['loc'] = $p1; set_temp($tg, $t); set_step($tg, 'admin_plan_title'); edit($chat, $mid, "📦 عنوان پلن را وارد کنید:\n/cancel برای لغو"); break;
        case 'a_plan_tg': db()->prepare("UPDATE plans SET is_active=1-is_active WHERE id=?")->execute([$p1]); admin_list_plans($chat, $mid); break;
        case 'a_plan_del': db()->prepare("DELETE FROM plans WHERE id=?")->execute([$p1]); admin_list_plans($chat, $mid); break;

        /* سفارش‌ها */
        case 'a_orders': admin_list_orders($chat, $mid, 'pending', 0); break;
        case 'a_orders_pending': admin_list_orders($chat, $mid, 'pending', (int)$p1); break;
        case 'a_orders_all': admin_list_orders($chat, $mid, 'all', (int)$p1); break;
        case 'a_order': admin_show_order($chat, $mid, $p1); break;
        case 'a_oappr':
            db()->prepare("UPDATE orders SET status='paid', updated_at=? WHERE id=?")->execute([now(), $p1]);
            set_step($tg, 'admin_send_config'); set_temp($tg, ['order_id' => $p1]);
            send($chat, "✅ پرداخت سفارش #{$p1} تایید شد.\n\n✍️ اکنون متن اوت‌باند/کانفیگ را ارسال کنید تا برای کاربر فرستاده شود:\n/cancel برای لغو");
            break;
        case 'a_osend':
            set_step($tg, 'admin_send_config'); set_temp($tg, ['order_id' => $p1]);
            send($chat, "✍️ متن اوت‌باند/کانفیگ سفارش #{$p1} را ارسال کنید:\n/cancel برای لغو");
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
        case 'a_dc_add': set_step($tg, 'admin_dc_add'); edit($chat, $mid, "🎟 کد تخفیف جدید را به صورت زیر وارد کنید:\n<code>کد|نوع|مقدار|حداکثر استفاده|تاریخ انقضا</code>\n\nنوع: percent یا amount\nمثال درصدی: <code>OFF20|percent|20|100</code>\nمثال مبلغی: <code>SALE|amount|15000|0|2025-12-30</code>\n(حداکثر استفاده 0 = نامحدود)\n/cancel برای لغو"); break;
        case 'a_dc_tg': db()->prepare("UPDATE discount_codes SET is_active=1-is_active WHERE id=?")->execute([$p1]); admin_list_dcs($chat, $mid); break;
        case 'a_dc_del': db()->prepare("DELETE FROM discount_codes WHERE id=?")->execute([$p1]); admin_list_dcs($chat, $mid); break;

        /* کاربران */
        case 'a_users': admin_users_home($chat, $mid); break;
        case 'a_users_list': admin_list_users($chat, $mid, (int)$p1); break;
        case 'a_user_search': set_step($tg, 'admin_user_search'); edit($chat, $mid, "👤 آیدی عددی یا یوزرنیم کاربر را ارسال کنید:\n/cancel برای لغو"); break;
        case 'a_user': admin_show_user($chat, $mid, $p1); break;
        case 'a_user_block': db()->prepare("UPDATE users SET is_blocked=1-is_blocked WHERE tg_id=?")->execute([$p1]); admin_show_user($chat, $mid, $p1); break;
        case 'a_user_bal': set_step($tg, 'admin_ubal'); set_temp($tg, ['uid' => $p1]); edit($chat, $mid, "💰 مبلغ افزایش/کاهش موجودی را وارد کنید (برای کاهش از - استفاده کنید):\nمثال: <code>50000</code> یا <code>-20000</code>\n/cancel برای لغو"); break;

        /* پیام همگانی */
        case 'a_bc': set_step($tg, 'admin_broadcast'); edit($chat, $mid, "📢 متن پیام همگانی را ارسال کنید:\n/cancel برای لغو"); break;

        /* زیرمجموعه‌گیری */
        case 'a_ref': admin_ref($chat, $mid); break;
        case 'a_ref_toggle': set_setting('referral_enabled', setting('referral_enabled') === '1' ? '0' : '1'); admin_ref($chat, $mid); break;
        case 'a_ref_percent': set_step($tg, 'admin_set_setting'); set_temp($tg, ['key' => 'referral_percent']); edit($chat, $mid, "👥 درصد پاداش زیرمجموعه را وارد کنید (عدد):\n/cancel برای لغو"); break;

        /* تنظیمات */
        case 'a_settings': admin_settings($chat, $mid); break;
        case 'a_set':
            $key = $p1;
            $labels = [
                'card_number' => 'شماره کارت', 'card_holder' => 'نام صاحب کارت',
                'support_username' => 'یوزرنیم پشتیبانی (بدون @)', 'channel_username' => 'یوزرنیم کانال جوین اجباری (بدون @)',
                'min_charge' => 'حداقل مبلغ شارژ', 'welcome_text' => 'متن خوش‌آمدگویی',
            ];
            set_step($tg, 'admin_set_setting'); set_temp($tg, ['key' => $key]);
            edit($chat, $mid, "✏️ مقدار جدید برای «" . ($labels[$key] ?? $key) . "» را وارد کنید:\n/cancel برای لغو");
            break;
        case 'a_toggle_join': set_setting('forced_join', setting('forced_join') === '1' ? '0' : '1'); admin_settings($chat, $mid); break;
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
        $label = "{$st} {$r['title']} | {$r['cat']} | {$r['flag']}{$r['loc']} | " . fmt($r['price']);
        $kb[] = [btn(mb_substr($label, 0, 60), 'a_plan_tg:' . $r['id']), btn('🗑', 'a_plan_del:' . $r['id'])];
    }
    $kb[] = [btn('🔙 بازگشت', 'a_back')];
    $t = "📦 <b>مدیریت پلن‌ها</b>\nروی پلن بزنید تا فعال/غیرفعال شود.";
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
    $kb = [];
    if ($o['status'] === 'pending_approval') {
        $kb[] = [btn('✅ تایید پرداخت', 'a_oappr:' . $oid)];
        if ($o['receipt_file_id']) send_photo($chat, $o['receipt_file_id'], "🧾 رسید سفارش #{$oid}");
    } elseif ($o['status'] === 'paid') {
        $kb[] = [btn('📤 ارسال کانفیگ', 'a_osend:' . $oid)];
    } elseif ($o['status'] === 'delivered') {
        $kb[] = [btn('📤 ارسال مجدد کانفیگ', 'a_osend:' . $oid)];
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
        $kb[] = [btn("{$st} {$r['code']} ({$v}) {$r['used_count']}/" . ($r['max_uses'] ?: '∞'), 'a_dc_tg:' . $r['id']), btn('🗑', 'a_dc_del:' . $r['id'])];
    }
    $kb[] = [btn('🔙 بازگشت', 'a_back')];
    $t = "🎟 <b>مدیریت کدهای تخفیف</b>";
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
        [btn('💰 تنظیم موجودی', 'a_user_bal:' . $usr['tg_id'])],
        [btn($usr['is_blocked'] ? '✅ رفع مسدودی' : '🚫 مسدود کردن', 'a_user_block:' . $usr['tg_id'])],
        [btn('🔙 بازگشت', 'a_users')],
    ];
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
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
    $t = "⚙️ <b>تنظیمات ربات</b>\n\n"
       . "💳 شماره کارت: <code>" . setting('card_number') . "</code>\n"
       . "👤 صاحب کارت: " . setting('card_holder') . "\n"
       . "☎️ پشتیبانی: @" . setting('support_username') . "\n"
       . "📢 کانال جوین: " . (setting('channel_username') ? '@' . setting('channel_username') : '—') . "\n"
       . "🔒 جوین اجباری: " . ($join ? '🟢 فعال' : '🔴 غیرفعال') . "\n"
       . "💵 حداقل شارژ: " . fmt(setting('min_charge')) . " تومان";
    $kb = [
        [btn('💳 شماره کارت', 'a_set:card_number'), btn('👤 صاحب کارت', 'a_set:card_holder')],
        [btn('☎️ پشتیبانی', 'a_set:support_username'), btn('💵 حداقل شارژ', 'a_set:min_charge')],
        [btn('📢 کانال جوین', 'a_set:channel_username'), btn($join ? '🔴 خاموش‌کردن جوین' : '🟢 روشن‌کردن جوین', 'a_toggle_join')],
        [btn('📝 متن خوش‌آمد', 'a_set:welcome_text')],
        [btn('🔙 بازگشت', 'a_back')],
    ];
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}
