<?php
/* =======================================================
 *  هندلرهای کاربر (سمت مشتری)
 * ======================================================= */

function user_handle_message($msg, $u) {
    $chat = $msg['chat']['id'];
    $tg   = $msg['from']['id'];
    $text = trim($msg['text'] ?? '');
    $step = $u['step'];

    // شروع
    if (strpos($text, '/start') === 0) {
        handle_start($msg, $text, $u);
        return;
    }
    if ($text === '/cancel') {
        set_step($tg, ''); set_temp($tg, []);
        send($chat, '✅ عملیات لغو شد.', main_menu_kb($tg));
        return;
    }

    // دروازه جوین اجباری
    if (!check_join($tg)) { send_join_prompt($chat); return; }

    // مراحل ورودی (ارسال رسید با عکس)
    if (isset($msg['photo']) && in_array($step, ['order_receipt', 'charge_receipt'], true)) {
        $file_id = end($msg['photo'])['file_id'];
        if ($step === 'order_receipt') handle_order_receipt($tg, $chat, $file_id);
        else handle_charge_receipt($tg, $chat, $file_id);
        return;
    }

    // مراحل ورودی متنی
    if ($step === 'charge_amount') { handle_charge_amount($tg, $chat, $text); return; }
    if ($step === 'discount_input') { handle_discount_input($tg, $chat, $text); return; }
    if (in_array($step, ['order_receipt', 'charge_receipt'], true)) {
        send($chat, '📷 لطفاً تصویر رسید پرداخت را ارسال کنید.');
        return;
    }

    // منوی اصلی
    switch ($text) {
        case '🛒 خرید اوت‌باند': show_categories($chat); break;
        case '👛 کیف پول':       show_wallet($chat, get_user($tg)); break;
        case '📦 سفارش‌های من':   show_my_orders($chat, $tg); break;
        case '🎁 کد تخفیف':       send($chat, "🎁 برای استفاده از کد تخفیف، ابتدا یک پلن را برای خرید انتخاب کنید و سپس گزینه «اعمال کد تخفیف» را بزنید."); break;
        case '👥 زیرمجموعه‌گیری':  show_referral($chat, $tg); break;
        case '☎️ پشتیبانی':       show_support($chat); break;
        default:
            send($chat, 'یکی از گزینه‌های منو را انتخاب کنید 👇', main_menu_kb($tg));
    }
}

function handle_start($msg, $text, $u) {
    $chat = $msg['chat']['id'];
    $tg   = $msg['from']['id'];
    // رفرال
    $parts = explode(' ', $text);
    if (isset($parts[1]) && ctype_digit($parts[1])) {
        $ref = (int)$parts[1];
        if ($ref !== (int)$tg && empty($u['referred_by'])) {
            $ru = get_user($ref);
            if ($ru) {
                db()->prepare("UPDATE users SET referred_by=? WHERE tg_id=? AND referred_by IS NULL")
                    ->execute([$ref, $tg]);
                send($ref, "🎉 یک کاربر جدید با لینک دعوت شما وارد ربات شد!");
            }
        }
    }
    if (!check_join($tg)) { send_join_prompt($chat); return; }
    send($chat, setting('welcome_text'), main_menu_kb($tg));
}

/* ---------- خرید ---------- */
function show_categories($chat, $mid = null) {
    $rows = db()->query("SELECT * FROM categories WHERE is_active=1 ORDER BY id")->fetchAll();
    if (!$rows) {
        $t = "❌ در حال حاضر دسته‌بندی فعالی وجود ندارد.";
        $mid ? edit($chat, $mid, $t) : send($chat, $t);
        return;
    }
    $kb = [];
    foreach ($rows as $r) $kb[] = [btn('🗂 ' . $r['name'], 'cat:' . $r['id'])];
    $t = "🛒 <b>یک دسته‌بندی را انتخاب کنید:</b>";
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

function show_locations($chat, $mid, $cat_id) {
    $st = db()->prepare("SELECT DISTINCT l.* FROM locations l
        JOIN plans p ON p.location_id=l.id
        WHERE l.is_active=1 AND p.is_active=1 AND p.category_id=? ORDER BY l.id");
    $st->execute([$cat_id]);
    $rows = $st->fetchAll();
    if (!$rows) { edit($chat, $mid, "❌ برای این دسته‌بندی لوکیشنی موجود نیست.", inline([[btn('🔙 بازگشت', 'buy_home')]])); return; }
    $kb = [];
    foreach ($rows as $r) $kb[] = [btn(trim($r['flag'] . ' ' . $r['name']), 'loc:' . $cat_id . ':' . $r['id'])];
    $kb[] = [btn('🔙 بازگشت', 'buy_home')];
    edit($chat, $mid, "🌍 <b>یک لوکیشن را انتخاب کنید:</b>", inline($kb));
}

function show_plans($chat, $mid, $cat_id, $loc_id) {
    $st = db()->prepare("SELECT * FROM plans WHERE is_active=1 AND category_id=? AND location_id=? ORDER BY price");
    $st->execute([$cat_id, $loc_id]);
    $rows = $st->fetchAll();
    if (!$rows) { edit($chat, $mid, "❌ پلنی موجود نیست.", inline([[btn('🔙 بازگشت', 'cat:' . $cat_id)]])); return; }
    $kb = [];
    foreach ($rows as $r) $kb[] = [btn($r['title'] . ' — ' . fmt($r['price']) . ' تومان', 'plan:' . $r['id'])];
    $kb[] = [btn('🔙 بازگشت', 'cat:' . $cat_id)];
    edit($chat, $mid, "📦 <b>یک پلن را انتخاب کنید:</b>", inline($kb));
}

function show_plan_detail($chat, $mid, $tg, $plan_id) {
    $p = get_plan($plan_id);
    if (!$p || !$p['is_active']) { edit($chat, $mid, "❌ این پلن دیگر موجود نیست."); return; }
    $temp = get_temp($tg);
    $code = ($temp['plan_id'] ?? null) == $plan_id ? ($temp['discount'] ?? null) : null;
    list($final, $off, $valid) = apply_discount($p['price'], $code);

    $t  = "📦 <b>{$p['title']}</b>\n\n";
    if ($p['description']) $t .= $p['description'] . "\n\n";
    $t .= "💰 قیمت: <b>" . fmt($p['price']) . "</b> تومان\n";
    if ($valid && $off > 0) {
        $t .= "🎁 تخفیف ({$valid}): " . fmt($off) . " تومان\n";
        $t .= "💳 مبلغ قابل پرداخت: <b>" . fmt($final) . "</b> تومان\n";
    }
    $kb = [
        [btn('🛒 خرید این پلن', 'buyplan:' . $plan_id)],
        [btn('🎁 اعمال کد تخفیف', 'dc:' . $plan_id)],
        [btn('🔙 بازگشت', 'loc:' . $p['category_id'] . ':' . $p['location_id'])],
    ];
    edit($chat, $mid, $t, inline($kb));
}

function show_payment_options($chat, $mid, $tg, $plan_id) {
    $p = get_plan($plan_id);
    if (!$p) return;
    $u = get_user($tg);
    $temp = get_temp($tg);
    $code = ($temp['plan_id'] ?? null) == $plan_id ? ($temp['discount'] ?? null) : null;
    list($final) = apply_discount($p['price'], $code);

    $t = "💳 <b>روش پرداخت را انتخاب کنید</b>\n\nمبلغ قابل پرداخت: <b>" . fmt($final) . "</b> تومان\nموجودی کیف پول شما: " . fmt($u['balance']) . " تومان";
    $kb = [
        [btn('👛 پرداخت از کیف پول', 'pw:' . $plan_id)],
        [btn('💳 کارت به کارت', 'pc:' . $plan_id)],
        [btn('🔙 بازگشت', 'plan:' . $plan_id)],
    ];
    edit($chat, $mid, $t, inline($kb));
}

function create_order($tg, $plan, $method) {
    $temp = get_temp($tg);
    $code = ($temp['plan_id'] ?? null) == $plan['id'] ? ($temp['discount'] ?? null) : null;
    list($final, $off, $valid) = apply_discount($plan['price'], $code);
    $title = $plan['title'];
    if (!empty($temp['renew_of'])) $title .= ' (تمدید #' . $temp['renew_of'] . ')';
    $st = db()->prepare("INSERT INTO orders(user_tg, plan_id, plan_title, price, status, payment_method, discount_code, created_at, updated_at)
        VALUES(?,?,?,?,?,?,?,?,?)");
    $status = $method === 'wallet' ? 'paid' : 'awaiting_receipt';
    $st->execute([$tg, $plan['id'], $title, $final, $status, $method, $valid, now(), now()]);
    $oid = db()->lastInsertId();
    if ($valid) db()->prepare("UPDATE discount_codes SET used_count=used_count+1 WHERE code=?")->execute([$valid]);
    // پاک کردن کد تخفیف/علامت تمدید از temp
    set_temp($tg, []);
    return [$oid, $final];
}

function pay_with_wallet($chat, $mid, $tg, $plan_id) {
    $p = get_plan($plan_id);
    if (!$p || !$p['is_active']) { edit($chat, $mid, "❌ این پلن موجود نیست."); return; }
    $u = get_user($tg);
    $temp = get_temp($tg);
    $code = ($temp['plan_id'] ?? null) == $plan_id ? ($temp['discount'] ?? null) : null;
    list($final) = apply_discount($p['price'], $code);
    if ($u['balance'] < $final) {
        edit($chat, $mid, "❌ موجودی کیف پول شما کافی نیست.\nموجودی: " . fmt($u['balance']) . " تومان\nمبلغ لازم: " . fmt($final) . " تومان",
            inline([[btn('➕ شارژ کیف پول', 'wallet_charge')], [btn('🔙 بازگشت', 'plan:' . $plan_id)]]));
        return;
    }
    add_balance($tg, -$final);
    add_tx($tg, -$final, 'purchase', 'خرید پلن: ' . $p['title']);
    list($oid) = create_order($tg, $p, 'wallet');
    edit($chat, $mid, "✅ پرداخت از کیف پول با موفقیت انجام شد.\n\n🧾 شماره سفارش: <b>#{$oid}</b>\n📦 اوت‌باند شما به‌صورت دستی توسط ادمین آماده و ارسال خواهد شد. لطفاً منتظر بمانید.");
    $un = $u['username'] ? '@' . $u['username'] : $tg;
    notify_admins("🆕 <b>سفارش جدید (پرداخت از کیف پول)</b>\n\n🧾 سفارش #{$oid}\n👤 کاربر: {$un}\n📦 پلن: {$p['title']}\n💰 مبلغ: " . fmt($final) . " تومان\n\nنیازمند ارسال کانفیگ ✍️",
        inline([[btn('📤 رسیدگی به سفارش', 'a_order:' . $oid)]]));
}

function pay_card_to_card($chat, $mid, $tg, $plan_id) {
    $p = get_plan($plan_id);
    if (!$p || !$p['is_active']) { edit($chat, $mid, "❌ این پلن موجود نیست."); return; }
    list($oid, $final) = create_order($tg, $p, 'card');
    set_step($tg, 'order_receipt');
    set_temp($tg, ['order_id' => $oid]);
    $t = "💳 <b>پرداخت کارت به کارت</b>\n\n"
       . "لطفاً مبلغ <b>" . fmt($final) . "</b> تومان را به کارت زیر واریز کنید:\n\n"
       . "💳 شماره کارت:\n<code>" . setting('card_number') . "</code>\n"
       . "👤 به نام: " . setting('card_holder') . "\n\n"
       . "🧾 شماره سفارش: #{$oid}\n\n"
       . "📷 پس از واریز، <b>تصویر رسید</b> را همینجا ارسال کنید.";
    edit($chat, $mid, $t);
}

function handle_order_receipt($tg, $chat, $file_id) {
    $temp = get_temp($tg);
    $oid = $temp['order_id'] ?? 0;
    $st = db()->prepare("SELECT * FROM orders WHERE id=? AND user_tg=?");
    $st->execute([$oid, $tg]);
    $o = $st->fetch();
    if (!$o) { set_step($tg, ''); send($chat, "❌ سفارش یافت نشد.", main_menu_kb($tg)); return; }
    db()->prepare("UPDATE orders SET status='pending_approval', receipt_file_id=?, updated_at=? WHERE id=?")
        ->execute([$file_id, now(), $oid]);
    set_step($tg, ''); set_temp($tg, []);
    send($chat, "✅ رسید شما ثبت شد و در انتظار تایید ادمین است.\n🧾 شماره سفارش: #{$oid}", main_menu_kb($tg));
    $u = get_user($tg);
    $un = $u['username'] ? '@' . $u['username'] : $tg;
    global $ADMIN_IDS;
    foreach ($ADMIN_IDS as $aid) {
        send_photo($aid, $file_id,
            "🧾 <b>رسید سفارش #{$oid}</b>\n👤 کاربر: {$un}\n📦 پلن: {$o['plan_title']}\n💰 مبلغ: " . fmt($o['price']) . " تومان",
            inline([[btn('✅ تایید پرداخت', 'a_oappr:' . $oid), btn('🚫 لغو سفارش', 'a_ocancel:' . $oid)]]));
    }
}

/* ---------- کیف پول ---------- */
function show_wallet($chat, $u, $mid = null) {
    $t = "👛 <b>کیف پول شما</b>\n\n💰 موجودی: <b>" . fmt($u['balance']) . "</b> تومان";
    $kb = inline([
        [btn('➕ شارژ کیف پول', 'wallet_charge')],
        [btn('📜 تاریخچه تراکنش‌ها', 'wallet_tx')],
    ]);
    $mid ? edit($chat, $mid, $t, $kb) : send($chat, $t, $kb);
}
function start_charge($chat, $mid, $tg) {
    set_step($tg, 'charge_amount');
    edit($chat, $mid, "➕ <b>شارژ کیف پول</b>\n\nمبلغ مورد نظر (به تومان) را وارد کنید:\nحداقل مبلغ: " . fmt(setting('min_charge')) . " تومان\n\nبرای لغو /cancel را بزنید.");
}
function handle_charge_amount($tg, $chat, $text) {
    $amount = (int)preg_replace('/\D/', '', $text);
    $min = (int)setting('min_charge');
    if ($amount < $min) { send($chat, "❌ مبلغ نامعتبر است. حداقل " . fmt($min) . " تومان."); return; }
    $st = db()->prepare("INSERT INTO payments(user_tg, amount, status, created_at, updated_at) VALUES(?,?,?,?,?)");
    $st->execute([$tg, $amount, 'awaiting_receipt', now(), now()]);
    $pid = db()->lastInsertId();
    set_step($tg, 'charge_receipt');
    set_temp($tg, ['payment_id' => $pid]);
    send($chat, "💳 لطفاً مبلغ <b>" . fmt($amount) . "</b> تومان را به کارت زیر واریز کنید:\n\n💳 <code>" . setting('card_number') . "</code>\n👤 " . setting('card_holder') . "\n\n📷 سپس تصویر رسید را ارسال کنید.");
}
function handle_charge_receipt($tg, $chat, $file_id) {
    $temp = get_temp($tg);
    $pid = $temp['payment_id'] ?? 0;
    $st = db()->prepare("SELECT * FROM payments WHERE id=? AND user_tg=?");
    $st->execute([$pid, $tg]);
    $p = $st->fetch();
    if (!$p) { set_step($tg, ''); send($chat, "❌ درخواست یافت نشد.", main_menu_kb($tg)); return; }
    db()->prepare("UPDATE payments SET status='pending', receipt_file_id=?, updated_at=? WHERE id=?")
        ->execute([$file_id, now(), $pid]);
    set_step($tg, ''); set_temp($tg, []);
    send($chat, "✅ رسید شما ثبت شد و در انتظار تایید ادمین است.", main_menu_kb($tg));
    $u = get_user($tg);
    $un = $u['username'] ? '@' . $u['username'] : $tg;
    global $ADMIN_IDS;
    foreach ($ADMIN_IDS as $aid) {
        send_photo($aid, $file_id,
            "💳 <b>درخواست شارژ کیف پول #{$pid}</b>\n👤 کاربر: {$un}\n💰 مبلغ: " . fmt($p['amount']) . " تومان",
            inline([[btn('✅ تایید', 'a_chappr:' . $pid), btn('❌ رد', 'a_chrej:' . $pid)]]));
    }
}
function show_transactions($chat, $mid, $tg) {
    $st = db()->prepare("SELECT * FROM transactions WHERE user_tg=? ORDER BY id DESC LIMIT 15");
    $st->execute([$tg]);
    $rows = $st->fetchAll();
    $t = "📜 <b>۱۵ تراکنش اخیر</b>\n\n";
    if (!$rows) $t .= "تراکنشی ثبت نشده است.";
    foreach ($rows as $r) {
        $sign = $r['amount'] >= 0 ? '➕' : '➖';
        $t .= "{$sign} " . fmt(abs($r['amount'])) . " تومان — {$r['description']}\n<code>{$r['created_at']}</code>\n\n";
    }
    edit($chat, $mid, $t, inline([[btn('🔙 بازگشت', 'wallet_home')]]));
}

/* ---------- کد تخفیف ---------- */
function start_discount($chat, $mid, $tg, $plan_id) {
    set_step($tg, 'discount_input');
    set_temp($tg, ['plan_id' => $plan_id]);
    edit($chat, $mid, "🎁 کد تخفیف خود را ارسال کنید:\n\nبرای لغو /cancel را بزنید.");
}
function handle_discount_input($tg, $chat, $text) {
    $temp = get_temp($tg);
    $plan_id = $temp['plan_id'] ?? 0;
    $p = get_plan($plan_id);
    if (!$p) { set_step($tg, ''); send($chat, "❌ پلن یافت نشد.", main_menu_kb($tg)); return; }
    list($final, $off, $valid) = apply_discount($p['price'], trim($text));
    set_step($tg, '');
    if (!$valid) {
        set_temp($tg, ['plan_id' => $plan_id]);
        send($chat, "❌ کد تخفیف نامعتبر یا منقضی شده است.");
    } else {
        set_temp($tg, ['plan_id' => $plan_id, 'discount' => $valid]);
        send($chat, "✅ کد تخفیف اعمال شد!\n🎁 تخفیف: " . fmt($off) . " تومان\n💳 مبلغ نهایی: " . fmt($final) . " تومان");
    }
    $kb = inline([[btn('🛒 ادامه خرید', 'plan:' . $plan_id)]]);
    send($chat, "برای ادامه روی دکمه زیر بزنید:", $kb);
}

/* ---------- سفارش‌های من ---------- */
function show_my_orders($chat, $tg, $mid = null) {
    $st = db()->prepare("SELECT * FROM orders WHERE user_tg=? ORDER BY id DESC LIMIT 20");
    $st->execute([$tg]);
    $rows = $st->fetchAll();
    $kb = [];
    if (!$rows) {
        $t = "📦 هنوز سفارشی ثبت نکرده‌اید.";
    } else {
        $t = "📦 <b>سفارش‌های شما</b>\nبرای مشاهده جزئیات، دریافت کانفیگ یا تمدید، روی هر سفارش بزنید:";
        foreach ($rows as $r) {
            $kb[] = [btn("🧾 #{$r['id']} | {$r['plan_title']} | " . status_label($r['status']), 'myorder:' . $r['id'])];
        }
    }
    $mid ? edit($chat, $mid, $t, inline($kb)) : send($chat, $t, inline($kb));
}

function show_my_order_detail($chat, $mid, $tg, $oid) {
    $st = db()->prepare("SELECT * FROM orders WHERE id=? AND user_tg=?");
    $st->execute([$oid, $tg]);
    $o = $st->fetch();
    if (!$o) { edit($chat, $mid, "❌ سفارش یافت نشد."); return; }
    $t = "🧾 <b>سفارش #{$o['id']}</b>\n\n"
       . "📦 پلن: {$o['plan_title']}\n"
       . "💰 مبلغ: " . fmt($o['price']) . " تومان\n"
       . "💳 روش پرداخت: " . ($o['payment_method'] === 'wallet' ? 'کیف پول' : 'کارت به کارت') . "\n"
       . "📌 وضعیت: " . status_label($o['status']) . "\n"
       . "🕒 تاریخ: <code>{$o['created_at']}</code>";
    if ($o['status'] === 'delivered' && $o['config_text']) {
        $t .= "\n\n🔻 <b>اوت‌باند شما:</b>\n" . $o['config_text'];
    }
    $kb = [];
    $plan = get_plan($o['plan_id']);
    if ($plan && $plan['is_active']) {
        $kb[] = [btn('🔄 تمدید این سفارش', 'renew:' . $o['id'])];
    } else {
        $kb[] = [btn('🛒 مشاهده پلن‌های موجود', 'buy_home')];
    }
    $kb[] = [btn('🔙 بازگشت به سفارش‌ها', 'myorders_home')];
    edit($chat, $mid, $t, inline($kb));
}

/* تمدید مستقیم همان پلن (بدون عبور از مراحل انتخاب دسته/لوکیشن/پلن) */
function show_renew($chat, $mid, $tg, $oid) {
    $st = db()->prepare("SELECT * FROM orders WHERE id=? AND user_tg=?");
    $st->execute([$oid, $tg]);
    $o = $st->fetch();
    if (!$o) { edit($chat, $mid, "❌ سفارش یافت نشد."); return; }
    $p = get_plan($o['plan_id']);
    if (!$p || !$p['is_active']) {
        edit($chat, $mid, "❌ متأسفانه این پلن دیگر فعال نیست و امکان تمدید وجود ندارد.", inline([[btn('🔙 بازگشت', 'myorder:' . $oid)]]));
        return;
    }
    $u = get_user($tg);
    set_temp($tg, ['renew_of' => $oid]); // علامت‌گذاری تمدید + پاک‌سازی کد تخفیف قبلی
    $t = "🔄 <b>تمدید سفارش #{$oid}</b>\n\n"
       . "📦 پلن: {$p['title']}\n"
       . "💰 مبلغ تمدید: <b>" . fmt($p['price']) . "</b> تومان\n"
       . "👛 موجودی کیف پول: " . fmt($u['balance']) . " تومان\n\n"
       . "روش پرداخت را برای تمدید انتخاب کنید:";
    $kb = [
        [btn('👛 پرداخت از کیف پول', 'pw:' . $p['id'])],
        [btn('💳 کارت به کارت', 'pc:' . $p['id'])],
        [btn('🔙 بازگشت به سفارش', 'myorder:' . $oid)],
    ];
    edit($chat, $mid, $t, inline($kb));
}

/* ---------- زیرمجموعه‌گیری ---------- */
function show_referral($chat, $tg, $mid = null) {
    if (setting('referral_enabled', '1') !== '1') {
        send($chat, "👥 سیستم زیرمجموعه‌گیری در حال حاضر غیرفعال است.");
        return;
    }
    $bu = bot_username();
    $link = "https://t.me/{$bu}?start={$tg}";
    $cnt = db()->prepare("SELECT COUNT(*) c FROM users WHERE referred_by=?");
    $cnt->execute([$tg]);
    $count = $cnt->fetch()['c'];
    $earn = db()->prepare("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE user_tg=? AND type='referral'");
    $earn->execute([$tg]);
    $total = $earn->fetch()['s'];
    $percent = setting('referral_percent', '0');
    $t = "👥 <b>زیرمجموعه‌گیری</b>\n\n"
       . "با دعوت دوستان خود، در هر خرید آن‌ها <b>{$percent}%</b> پاداش به کیف پول شما اضافه می‌شود!\n\n"
       . "🔗 لینک دعوت شما:\n<code>{$link}</code>\n\n"
       . "👤 تعداد زیرمجموعه: <b>{$count}</b>\n"
       . "💰 مجموع پاداش: <b>" . fmt($total) . "</b> تومان";
    $mid ? edit($chat, $mid, $t) : send($chat, $t);
}

/* ---------- پشتیبانی ---------- */
function show_support($chat) {
    $s = setting('support_username', '');
    send($chat, "☎️ <b>پشتیبانی</b>\n\nبرای ارتباط با پشتیبانی به آیدی زیر پیام دهید:\n@" . ltrim($s, '@'));
}

/* ---------- کال‌بک‌های کاربر ---------- */
function user_handle_callback($cb, $u) {
    $chat = $cb['message']['chat']['id'];
    $mid  = $cb['message']['message_id'];
    $tg   = $cb['from']['id'];
    $data = $cb['data'];
    $parts = explode(':', $data);
    $cmd = $parts[0];
    answer($cb['id']);

    if ($cmd === 'check_join') {
        if (check_join($tg)) {
            tg('deleteMessage', ['chat_id' => $chat, 'message_id' => $mid]);
            send($chat, setting('welcome_text'), main_menu_kb($tg));
        } else {
            answer($cb['id'], 'هنوز عضو کانال نشده‌اید!', true);
        }
        return;
    }

    if (!check_join($tg)) { send_join_prompt($chat); return; }

    switch ($cmd) {
        case 'buy_home': show_categories($chat, $mid); break;
        case 'cat':      show_locations($chat, $mid, $parts[1]); break;
        case 'loc':      show_plans($chat, $mid, $parts[1], $parts[2]); break;
        case 'plan':     show_plan_detail($chat, $mid, $tg, $parts[1]); break;
        case 'buyplan':  show_payment_options($chat, $mid, $tg, $parts[1]); break;
        case 'pw':       pay_with_wallet($chat, $mid, $tg, $parts[1]); break;
        case 'pc':       pay_card_to_card($chat, $mid, $tg, $parts[1]); break;
        case 'dc':       start_discount($chat, $mid, $tg, $parts[1]); break;
        case 'myorders_home': show_my_orders($chat, $tg, $mid); break;
        case 'myorder':  show_my_order_detail($chat, $mid, $tg, $parts[1]); break;
        case 'renew':    show_renew($chat, $mid, $tg, $parts[1]); break;
        case 'wallet_home':   show_wallet($chat, get_user($tg), $mid); break;
        case 'wallet_charge': start_charge($chat, $mid, $tg); break;
        case 'wallet_tx':     show_transactions($chat, $mid, $tg); break;
    }
}
