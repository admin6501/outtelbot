#!/usr/bin/env bash
# =====================================================================
#  Outbound Sales Bot - Auto Installer & Manager  (Ubuntu 20/22/24)
#  Mode: Webhook (Nginx + PHP-FPM + Let's Encrypt SSL)
#  Repo: https://github.com/admin6501/outtelbot
# =====================================================================
set -o pipefail

# ---------- ثابت‌ها ----------
REPO_URL="https://github.com/admin6501/outtelbot"
DEFAULT_DIR="/opt/outtelbot"
STATE_FILE="/etc/outtelbot.conf"
NGINX_SITE="/etc/nginx/sites-available/outtelbot.conf"
NGINX_LINK="/etc/nginx/sites-enabled/outtelbot.conf"

# ---------- رنگ‌ها ----------
RED=$'\e[31m'; GREEN=$'\e[32m'; YELLOW=$'\e[33m'; BLUE=$'\e[36m'; BOLD=$'\e[1m'; NC=$'\e[0m'

msg()  { echo -e "${BLUE}▶ ${NC}$1"; }
ok()   { echo -e "${GREEN}✅ ${NC}$1"; }
warn() { echo -e "${YELLOW}⚠️  ${NC}$1"; }
err()  { echo -e "${RED}❌ ${NC}$1"; }
line() { echo -e "${BLUE}────────────────────────────────────────────${NC}"; }

# ---------- بررسی روت ----------
require_root() {
  if [[ $EUID -ne 0 ]]; then
    err "این اسکریپت باید با کاربر root اجرا شود. از 'sudo bash install.sh' استفاده کنید."
    exit 1
  fi
}

# ---------- بارگذاری/ذخیره وضعیت ----------
load_state() { [[ -f "$STATE_FILE" ]] && source "$STATE_FILE"; }
save_state() {
  cat > "$STATE_FILE" <<EOF
INSTALL_DIR="$INSTALL_DIR"
BOT_DIR="$BOT_DIR"
DOMAIN="$DOMAIN"
PHP_FPM="$PHP_FPM"
EOF
}

detect_php_fpm() {
  local ver
  ver="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)"
  if [[ -n "$ver" ]]; then
    PHP_FPM="php${ver}-fpm"
    PHP_SOCK="/run/php/php${ver}-fpm.sock"
  else
    PHP_FPM="$(systemctl list-unit-files 2>/dev/null | grep -oE 'php[0-9.]+-fpm' | head -1)"
    PHP_SOCK="/run/php/${PHP_FPM%-fpm}-fpm.sock"
  fi
}

# ---------- نصب پیش‌نیازها ----------
install_prereqs() {
  msg "به‌روزرسانی لیست بسته‌ها..."
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y >/dev/null 2>&1
  msg "نصب پیش‌نیازها (nginx, php-fpm, php-sqlite3, php-curl, certbot, git, curl)..."
  apt-get install -y nginx php-fpm php-cli php-sqlite3 php-curl php-mbstring \
      git curl certbot python3-certbot-nginx ufw >/dev/null 2>&1
  if [[ $? -ne 0 ]]; then err "نصب پیش‌نیازها با خطا مواجه شد."; return 1; fi
  ok "پیش‌نیازها نصب شدند."
}

# ---------- باز کردن فایروال ----------
open_firewall() {
  if command -v ufw >/dev/null 2>&1; then
    ufw allow 80/tcp  >/dev/null 2>&1
    ufw allow 443/tcp >/dev/null 2>&1
    ufw allow 22/tcp  >/dev/null 2>&1
  fi
}

# ---------- نوشتن config.php ----------
write_config() {
  local php_ids="$1"
  cat > "$BOT_DIR/config.php" <<EOF
<?php
/* فایل تنظیمات - توسط نصاب خودکار ساخته شد */
define('BOT_TOKEN', '${BOT_TOKEN}');
\$ADMIN_IDS = [${php_ids}];

define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('DB_PATH', __DIR__ . '/data/bot.db');
date_default_timezone_set('Asia/Tehran');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
EOF
}

# ---------- ساخت کانفیگ nginx ----------
write_nginx() {
  cat > "$NGINX_SITE" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    root ${BOT_DIR};
    index index.php;

    access_log /var/log/nginx/outtelbot.access.log;
    error_log  /var/log/nginx/outtelbot.error.log;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_SOCK};
    }

    # جلوگیری از دسترسی به دیتابیس
    location ~* /data/ { deny all; return 403; }
    location ~ /\.ht   { deny all; }
}
EOF
  ln -sf "$NGINX_SITE" "$NGINX_LINK"
  nginx -t >/dev/null 2>&1 && systemctl reload nginx
}

# ---------- دریافت SSL ----------
setup_ssl() {
  msg "دریافت گواهی SSL برای ${DOMAIN} ..."
  certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos \
      -m "admin@${DOMAIN}" --redirect >/dev/null 2>&1
  if [[ $? -eq 0 ]]; then
    ok "گواهی SSL با موفقیت نصب شد."
    return 0
  else
    warn "دریافت SSL ناموفق بود. مطمئن شوید رکورد DNS دامنه به IP این سرور اشاره می‌کند و پورت ۸۰ باز است."
    return 1
  fi
}

# ---------- ست کردن وبهوک ----------
set_webhook() {
  msg "ثبت وبهوک تلگرام..."
  local res
  res="$(curl -s "https://${DOMAIN}/set_webhook.php")"
  if echo "$res" | grep -q "Success"; then
    ok "وبهوک با موفقیت ثبت شد."
  else
    warn "ثبت وبهوک ممکن است ناموفق بوده باشد. خروجی:"
    echo "$res" | sed 's/<[^>]*>//g' | grep -v '^$' | head -n 8
  fi
}

# =====================================================================
#  نصب کامل
# =====================================================================
do_install() {
  line; echo -e "${BOLD}🚀 نصب ربات فروش اوت‌باند${NC}"; line

  read -rp "$(echo -e ${YELLOW}'📁 مسیر نصب [پیش‌فرض '"$DEFAULT_DIR"']: '${NC})" INSTALL_DIR
  INSTALL_DIR="${INSTALL_DIR:-$DEFAULT_DIR}"

  read -rp "$(echo -e ${YELLOW}'🌐 دامنه (مثال: bot.example.com): '${NC})" DOMAIN
  while [[ -z "$DOMAIN" ]]; do read -rp "دامنه نمی‌تواند خالی باشد: " DOMAIN; done

  read -rp "$(echo -e ${YELLOW}'🤖 توکن ربات (از BotFather): '${NC})" BOT_TOKEN
  while [[ -z "$BOT_TOKEN" ]]; do read -rp "توکن نمی‌تواند خالی باشد: " BOT_TOKEN; done

  read -rp "$(echo -e ${YELLOW}'👤 آیدی عددی ادمین (چند ادمین با کاما: 111,222): '${NC})" ADMIN_IDS_RAW
  while [[ -z "$ADMIN_IDS_RAW" ]]; do read -rp "آیدی ادمین نمی‌تواند خالی باشد: " ADMIN_IDS_RAW; done
  # تبدیل به آرایه PHP
  local php_ids
  php_ids="$(echo "$ADMIN_IDS_RAW" | tr ',' '\n' | sed 's/[^0-9]//g' | grep -v '^$' | paste -sd, -)"

  install_prereqs || return 1
  detect_php_fpm
  open_firewall

  # کلون پروژه
  if [[ -d "$INSTALL_DIR/.git" ]]; then
    msg "پروژه از قبل وجود دارد، در حال به‌روزرسانی..."
    git -C "$INSTALL_DIR" pull >/dev/null 2>&1
  else
    msg "در حال کلون پروژه از گیت‌هاب..."
    rm -rf "$INSTALL_DIR"
    git clone "$REPO_URL" "$INSTALL_DIR" >/dev/null 2>&1
    if [[ $? -ne 0 ]]; then
      err "کلون پروژه ناموفق بود. مطمئن شوید ریپو Public است: $REPO_URL"
      return 1
    fi
  fi
  ok "پروژه آماده شد."

  # تشخیص محل فایل‌های ربات (ریشه یا زیرپوشه outbound_bot)
  if [[ -f "$INSTALL_DIR/index.php" ]]; then
    BOT_DIR="$INSTALL_DIR"
  elif [[ -f "$INSTALL_DIR/outbound_bot/index.php" ]]; then
    BOT_DIR="$INSTALL_DIR/outbound_bot"
  else
    err "فایل index.php در پروژه پیدا نشد. ساختار ریپو را بررسی کنید."
    return 1
  fi
  msg "مسیر فایل‌های ربات: $BOT_DIR"

  # ساخت config و دسترسی‌ها
  write_config "$php_ids"
  mkdir -p "$BOT_DIR/data"
  chown -R www-data:www-data "$INSTALL_DIR"
  chmod -R 755 "$INSTALL_DIR"
  chmod -R 775 "$BOT_DIR/data"
  ok "تنظیمات نوشته شد."

  save_state

  # nginx + ssl + webhook
  write_nginx
  ok "وب‌سرور Nginx پیکربندی شد."
  setup_ssl
  set_webhook

  line
  ok "نصب کامل شد!"
  echo -e "  🌐 آدرس وبهوک: ${BOLD}https://${DOMAIN}/index.php${NC}"
  echo -e "  📁 مسیر نصب:  ${BOLD}${INSTALL_DIR}${NC}"
  echo -e "  ▶️  حالا در تلگرام به ربات /start و سپس /admin بدهید."
  line
}

# =====================================================================
#  مدیریت سرویس
# =====================================================================
svc_start()   { detect_php_fpm; systemctl start nginx "$PHP_FPM"   && ok "سرویس‌ها اجرا شدند."; }
svc_stop()    { detect_php_fpm; systemctl stop nginx "$PHP_FPM"    && ok "سرویس‌ها متوقف شدند."; }
svc_restart() { detect_php_fpm; systemctl restart nginx "$PHP_FPM" && ok "سرویس‌ها ری‌استارت شدند."; }

svc_status() {
  detect_php_fpm
  line
  echo -e "${BOLD}وضعیت سرویس‌ها:${NC}"
  systemctl is-active nginx     >/dev/null 2>&1 && echo -e "  Nginx:   ${GREEN}فعال${NC}"   || echo -e "  Nginx:   ${RED}غیرفعال${NC}"
  systemctl is-active "$PHP_FPM" >/dev/null 2>&1 && echo -e "  PHP-FPM: ${GREEN}فعال${NC}" || echo -e "  PHP-FPM: ${RED}غیرفعال${NC}"
  if [[ -n "$DOMAIN" ]]; then
    echo -e "\n${BOLD}وضعیت وبهوک تلگرام:${NC}"
    curl -s "https://${DOMAIN}/set_webhook.php" >/dev/null 2>&1 && echo "  بررسی شد"
  fi
  line
}

view_logs() {
  load_state
  line
  echo -e "${BOLD}📜 لاگ خطای Nginx (۳۰ خط آخر):${NC}"
  tail -n 30 /var/log/nginx/outtelbot.error.log 2>/dev/null || echo "لاگی موجود نیست."
  echo ""
  echo -e "${BOLD}📜 لاگ دسترسی (۱۰ خط آخر):${NC}"
  tail -n 10 /var/log/nginx/outtelbot.access.log 2>/dev/null || echo "لاگی موجود نیست."
  echo ""
  detect_php_fpm
  echo -e "${BOLD}📜 لاگ PHP-FPM (۱۵ خط آخر):${NC}"
  journalctl -u "$PHP_FPM" -n 15 --no-pager 2>/dev/null || echo "لاگی موجود نیست."
  line
}

reset_webhook() {
  load_state
  if [[ -z "$DOMAIN" ]]; then err "ابتدا باید نصب انجام شود."; return; fi
  set_webhook
}

do_remove() {
  load_state
  read -rp "$(echo -e ${RED}'آیا از حذف کامل ربات مطمئن هستید؟ (yes/no): '${NC})" confirm
  [[ "$confirm" != "yes" ]] && { warn "حذف لغو شد."; return; }
  msg "حذف کانفیگ nginx..."
  rm -f "$NGINX_SITE" "$NGINX_LINK"
  systemctl reload nginx 2>/dev/null
  if [[ -n "$INSTALL_DIR" && -d "$INSTALL_DIR" ]]; then
    msg "حذف فایل‌های پروژه از $INSTALL_DIR ..."
    rm -rf "$INSTALL_DIR"
  fi
  read -rp "گواهی SSL دامنه هم حذف شود؟ (yes/no): " delc
  if [[ "$delc" == "yes" && -n "$DOMAIN" ]]; then
    certbot delete --cert-name "$DOMAIN" --non-interactive >/dev/null 2>&1
  fi
  rm -f "$STATE_FILE"
  ok "ربات با موفقیت حذف شد."
}

do_update() {
  load_state
  if [[ -z "$INSTALL_DIR" || ! -d "$INSTALL_DIR/.git" ]]; then err "نصب یافت نشد."; return; fi
  msg "به‌روزرسانی پروژه از گیت‌هاب..."
  git -C "$INSTALL_DIR" stash >/dev/null 2>&1
  git -C "$INSTALL_DIR" pull >/dev/null 2>&1
  git -C "$INSTALL_DIR" stash pop >/dev/null 2>&1
  chown -R www-data:www-data "$INSTALL_DIR"
  ok "پروژه به‌روزرسانی شد. (فایل config.php شما حفظ شد)"
}

# =====================================================================
#  منوی تعاملی
# =====================================================================
menu() {
  load_state
  while true; do
    clear
    echo -e "${BOLD}${BLUE}"
    echo "╔══════════════════════════════════════════════╗"
    echo "║        🤖  مدیریت ربات فروش اوت‌باند           ║"
    echo "╚══════════════════════════════════════════════╝"
    echo -e "${NC}"
    [[ -n "$DOMAIN" ]] && echo -e "  دامنه: ${GREEN}${DOMAIN}${NC}   مسیر: ${GREEN}${INSTALL_DIR}${NC}\n"
    echo -e "  ${BOLD}1)${NC} 🚀 نصب / نصب مجدد"
    echo -e "  ${BOLD}2)${NC} ▶️  استارت سرویس"
    echo -e "  ${BOLD}3)${NC} ⏹  استاپ سرویس"
    echo -e "  ${BOLD}4)${NC} 🔄 ری‌استارت سرویس"
    echo -e "  ${BOLD}5)${NC} 📊 وضعیت سرویس"
    echo -e "  ${BOLD}6)${NC} 📜 مشاهده لاگ‌ها"
    echo -e "  ${BOLD}7)${NC} 🔗 ثبت مجدد وبهوک"
    echo -e "  ${BOLD}8)${NC} ⬆️  به‌روزرسانی پروژه"
    echo -e "  ${BOLD}9)${NC} 🗑  حذف کامل"
    echo -e "  ${BOLD}0)${NC} 🚪 خروج"
    echo ""
    read -rp "$(echo -e ${YELLOW}'لطفاً یک گزینه را انتخاب کنید: '${NC})" choice
    echo ""
    case "$choice" in
      1) do_install ;;
      2) svc_start ;;
      3) svc_stop ;;
      4) svc_restart ;;
      5) svc_status ;;
      6) view_logs ;;
      7) reset_webhook ;;
      8) do_update ;;
      9) do_remove ;;
      0) echo "خداحافظ 👋"; exit 0 ;;
      *) err "گزینه نامعتبر است." ;;
    esac
    echo ""
    read -rp "$(echo -e ${BLUE}'برای بازگشت به منو Enter بزنید...'${NC})"
    load_state
  done
}

# ---------- شروع ----------
require_root
menu
