# ربات تلگرام فروش اوت‌باند (VPN/Proxy) — PRD

## شرح مسئله
ربات تلگرام پر‌قابلیت با PHP برای فروش «اوت‌باند» (VPN/proxy). تحویل دستی و خودکار (از طریق پنل 3x-ui)، پرداخت کارت‌به‌کارت با تایید رسید، پنل ادمین کامل داخل ربات، کیف پول، کد تخفیف اختصاصی پلن، جوین اجباری کانال و جریان تمدید.

- زبان کاربر: **فارسی**
- استک: PHP 8.x + SQLite + Nginx + Webhook. بدون فریم‌ورک فرانت.

## معماری کد
- `config.php` — توکن، آیدی ادمین، مسیر دیتابیس
- `bot.php` — راه‌اندازی SQLite، توابع کمکی، wrapper تلگرام، صفحه‌بندی
- `handlers_user.php` — جریان کاربر (خرید، کیف پول، سفارش‌ها، تمدید، کارت‌به‌کارت، مدیریت کانفیگ)
- `handlers_admin.php` — جریان ادمین (پلن، کاربر، سفارش، تنظیمات، پنل)
- `panel.php` — wrapper API پنل 3x-ui (login, addClient, updateClient, resetClientTraffic, getClientTraffics, toggle, change-link)
- `index.php` — روتر اصلی Webhook
- `set_webhook.php` — ابزار تنظیم وبهوک
- `install.sh` — اسکریپت نصب/مدیریت روی اوبونتو

## اسکیمای دیتابیس کلیدی
- `users`, `plans` (cat_id, name, price, traffic_gb, duration_days, inbound_id)
- `orders` (status, config_text, renew_of, panel_inbound, panel_client_id, panel_email, panel_sub_id)
- `discount_codes` (code, type, value, plan_ids), `transactions`, `payments`, `settings`

## انجام‌شده
- راه‌اندازی پایه ربات (SQLite + Webhook)، اسکریپت نصب اوبونتو
- پنل ادمین با کیبورد ریپلای
- لغو سفارش با گزینه‌های بازگشت وجه، تغییر لینک سفارش‌های تحویل‌شده توسط ادمین
- بهبود جریان تمدید (مستقیم به پرداخت)
- کد تخفیف اختصاصی پلن، سوییچ کارت‌به‌کارت + fallback پشتیبانی
- ادغام خودکار 3x-ui (تحویل خودکار VLESS/VMESS/Trojan)
- منطق تمدید 3x-ui (افزودن حجم، تمدید انقضا، بدون ریست مصرف)
- **[2026-06] قابلیت مدیریت کانفیگ توسط کاربر در «سفارش‌های من»:**
  - نمایش حجم زنده (مصرف/باقی‌مانده/کل) و روزهای باقی‌مانده تا انقضا از پنل
  - فعال/غیرفعال‌سازی سرویس (حفظ حجم و انقضا)
  - تغییر لینک (ساخت مجدد UUID/subId و باطل‌کردن لینک قبلی، حفظ حجم و انقضا)
  - توابع جدید panel.php: `bytes_human`, `order_has_panel`, `panel_set_client_enable`, `panel_change_client`
  - کال‌بک‌ها: `oref`, `otgl`, `ochg`, `ochgok`
  - تست: ۹ assertion با mock 3x-ui — همه PASS
- **[2025-07] سیستم بکاپ‌گیری و بازگردانی دیتابیس (فایل جدید `backup.php`):**
  - بکاپ دستی: ارسال اسنپ‌شات SQLite (با `VACUUM INTO`) به‌صورت فایل به پیوی همه‌ی ادمین‌ها
  - بکاپ خودکار: قابل روشن/خاموش + فاصله‌ی زمانی قابل تنظیم (ساعت) از پنل؛ اجرا توسط `run_backup_cron()` در `cron.php`
  - بازگردانی: ادمین فایل `.db` را آپلود می‌کند → دانلود (`getFile`)، اعتبارسنجی (هدر SQLite + `integrity_check` + وجود جداول users/orders/settings)، مقایسه‌ی آمار، تایید دو‌مرحله‌ای، نسخه‌ی ایمنی خودکار از دیتابیس فعلی، سپس جایگزینی امن (unlink+rename)
  - تنظیمات جدید: `backup_auto`, `backup_interval_hours`, `backup_last_at`
  - UI: دکمه «💾 بکاپ و بازگردانی» در پنل تنظیمات؛ روتینگ `document` در `index.php` برای دریافت فایل restore
  - تست: ۱۵ assertion محلی با PHP (بدون شبکه) — همه PASS

## بک‌لاگ (اولویت‌بندی)
- **P1**: تست با پنل 3x-ui واقعی کاربر (تاکنون فقط mock تست شده). تطبیق endpointها با نسخه پنل کاربر.
- **P2**: تمدید خودکار SSL (cron).

## نکات مهم
- توسعه در محیط PHP خام؛ تست با شبیه‌سازی webhook تلگرام و mock سرور 3x-ui (`php -S`).
- ساختار payload پنل 3x-ui حساس است؛ هنگام updateClient کل اسکیمای کلاینت باید پاس شود.
- مشکل git dubious ownership قبلاً با `git config --global --add safe.directory` در install.sh حل شد.
