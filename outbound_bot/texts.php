<?php
/* =======================================================
 *  شخصی‌سازی متن‌ها و دکمه‌های کاربر
 *  هر متن یک «کلید تنظیمات» دارد؛ اگر مقداری ذخیره نشده باشد،
 *  مقدار پیش‌فرض (default) استفاده می‌شود.
 * ======================================================= */

/* مقدار سفارشی یک متن/دکمه را برمی‌گرداند (یا پیش‌فرض) */
function lbl($skey, $default) {
    $v = setting($skey, null);
    return ($v === null || $v === '') ? $default : $v;
}

/* برچسب‌های منوی اصلی (دکمه‌های کیبورد کاربر) */
function mm_labels() {
    return [
        'buy'      => lbl('txt_mm_buy', '🛒 خرید کانفیگ'),
        'wallet'   => lbl('txt_mm_wallet', '👛 کیف پول'),
        'orders'   => lbl('txt_mm_orders', '📦 سفارش‌های من'),
        'discount' => lbl('txt_mm_discount', '🎁 کد تخفیف'),
        'referral' => lbl('txt_mm_referral', '👥 زیرمجموعه‌گیری'),
        'support'  => lbl('txt_mm_support', '☎️ پشتیبانی'),
    ];
}

/* رجیستری همه‌ی متن‌های قابل ویرایش (برای پنل ادمین) */
function texts_registry() {
    return [
        ['cat' => '🔘 دکمه‌های منوی اصلی', 'items' => [
            ['key' => 'txt_mm_buy',      'title' => 'دکمه خرید',          'default' => '🛒 خرید کانفیگ'],
            ['key' => 'txt_mm_wallet',   'title' => 'دکمه کیف پول',        'default' => '👛 کیف پول'],
            ['key' => 'txt_mm_orders',   'title' => 'دکمه سفارش‌های من',    'default' => '📦 سفارش‌های من'],
            ['key' => 'txt_mm_discount', 'title' => 'دکمه کد تخفیف',       'default' => '🎁 کد تخفیف'],
            ['key' => 'txt_mm_referral', 'title' => 'دکمه زیرمجموعه‌گیری',  'default' => '👥 زیرمجموعه‌گیری'],
            ['key' => 'txt_mm_support',  'title' => 'دکمه پشتیبانی',       'default' => '☎️ پشتیبانی'],
        ]],
        ['cat' => '🛒 دکمه‌های خرید و پرداخت', 'items' => [
            ['key' => 'txt_btn_buy_plan',       'title' => 'دکمه خرید این پلن',        'default' => '🛒 خرید این پلن'],
            ['key' => 'txt_btn_pay_wallet',     'title' => 'دکمه پرداخت از کیف پول',    'default' => '👛 پرداخت از کیف پول'],
            ['key' => 'txt_btn_pay_card',       'title' => 'دکمه کارت به کارت',         'default' => '💳 کارت به کارت'],
            ['key' => 'txt_btn_pay_support',    'title' => 'دکمه پرداخت با پشتیبانی',   'default' => '☎️ پرداخت از طریق پشتیبانی'],
            ['key' => 'txt_btn_apply_discount', 'title' => 'دکمه اعمال کد تخفیف',       'default' => '🎁 اعمال کد تخفیف'],
            ['key' => 'txt_btn_charge',         'title' => 'دکمه شارژ کیف پول',         'default' => '➕ شارژ کیف پول'],
            ['key' => 'txt_btn_tx',             'title' => 'دکمه تاریخچه تراکنش‌ها',     'default' => '📜 تاریخچه تراکنش‌ها'],
            ['key' => 'txt_btn_renew',          'title' => 'دکمه تمدید سفارش',          'default' => '🔄 تمدید این سفارش'],
        ]],
        ['cat' => '💬 پیام‌ها', 'items' => [
            ['key' => 'welcome_text',      'title' => 'متن خوش‌آمدگویی',        'default' => "🌐 به ربات فروش کانفیگ خوش آمدید!\n\nاز منوی زیر یکی از گزینه‌ها را انتخاب کنید."],
            ['key' => 'txt_choose_menu',   'title' => 'پیام «انتخاب از منو»',    'default' => 'یکی از گزینه‌های منو را انتخاب کنید 👇'],
            ['key' => 'txt_cancelled',     'title' => 'پیام «عملیات لغو شد»',   'default' => '✅ عملیات لغو شد.'],
            ['key' => 'txt_support_intro', 'title' => 'متن معرفی پشتیبانی',     'default' => "☎️ <b>پشتیبانی</b>\n\nبرای ارتباط با پشتیبانی به آیدی زیر پیام دهید:"],
            ['key' => 'txt_discount_hint', 'title' => 'راهنمای کد تخفیف',       'default' => '🎁 برای استفاده از کد تخفیف، ابتدا یک پلن را برای خرید انتخاب کنید و سپس گزینه «اعمال کد تخفیف» را بزنید.'],
        ]],
    ];
}

/* یافتن یک آیتم بر اساس کلید */
function texts_find($key) {
    foreach (texts_registry() as $cat) {
        foreach ($cat['items'] as $it) {
            if ($it['key'] === $key) return $it;
        }
    }
    return null;
}
