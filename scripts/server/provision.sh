#!/usr/bin/env bash
# First-time provisioning of a single Ubuntu 24.04 VM for the starter
# (native Nginx + PHP-FPM, MySQL 8.4, Redis 8, Node 24 for SSR; doc 10).
# Run as root, or through sudo from the image's admin account (then root
# login is disabled), on a copy of the repository's deploy/ and
# scripts/server/ directories (same layout), e.g. from your machine:
#
#   rsync -a --relative deploy scripts/server HOST:starter/
#   ssh HOST 'sudo STARTER_DOMAIN=example.com STARTER_ACME_EMAIL=ops@example.com \
#       STARTER_DEPLOY_KEY="ssh-ed25519 AAAA... github-deploy" \
#       bash starter/scripts/server/provision.sh'
#
# After the first successful `dep deploy`:
#   sudo bash starter/scripts/server/provision.sh start
#
# Configuration (environment):
#   STARTER_DOMAIN       public host name of APP_URL (required; DNS A/AAAA
#                        must already point here for the TLS step)
#   STARTER_ACME_EMAIL   Let's Encrypt account e-mail (required); running the
#                        script accepts the Let's Encrypt subscriber agreement
#   STARTER_DEPLOY_KEY   public SSH key of the CI deploy identity (required)
#   STARTER_APP_NAME     APP_NAME (default: Software House)
#   STARTER_ALERT_EMAIL  OPS_ALERT_EMAIL (default: STARTER_ACME_EMAIL)
#   STARTER_SWAP_SIZE    swap file size when the VM has none (default: 2G)
#
# Idempotent: repositories and templates are rewritten, existing secrets
# (shared/.env, /etc/starter/backup.env) are kept and reused. Generated
# secrets are written only to those root/deploy-owned files, never printed.
# ClamAV is intentionally not installed (MEDIA_SCANNER_ENABLED=false).
set -Eeuo pipefail
umask 022

readonly APP_ROOT=/var/www/starter
readonly SHARED_ENV=$APP_ROOT/shared/.env
readonly BACKUP_ENV=/etc/starter/backup.env
readonly BACKUP_DIR=/var/backups/starter
readonly ACME_ROOT=/var/www/letsencrypt
readonly DEPLOY_USER=deploy
readonly HTTP_USER=www-data
readonly PHP_VERSION=8.5
readonly NODE_MAJOR=24

SOURCE_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
readonly SOURCE_ROOT
readonly TEMPLATES=$SOURCE_ROOT/deploy

log() { printf '\n==> %s\n' "$*"; }
fail() { printf 'provision: %s\n' "$*" >&2; exit 1; }
trap 'fail "failed at line $LINENO: $BASH_COMMAND"' ERR

random_hex() { openssl rand -hex "$1"; }

# Value of KEY in an env file (first match, surrounding quotes removed).
env_value() {
    sed -n "s/^$2=//p" "$1" | head -n 1 | sed -e 's/^"//' -e 's/"$//'
}

preflight() {
    [[ $EUID -eq 0 ]] || fail 'run as root (or through sudo)'
    # shellcheck source=/dev/null
    . /etc/os-release
    [[ ${ID:-} == ubuntu && ${VERSION_ID:-} == 24.04 ]] || fail "Ubuntu 24.04 required, found ${PRETTY_NAME:-unknown}"
    [[ -d $TEMPLATES/nginx && -d $TEMPLATES/systemd ]] || fail "templates not found in $TEMPLATES (copy deploy/ next to scripts/server/)"

    : "${STARTER_DOMAIN:?STARTER_DOMAIN is required}"
    : "${STARTER_ACME_EMAIL:?STARTER_ACME_EMAIL is required}"
    : "${STARTER_DEPLOY_KEY:?STARTER_DEPLOY_KEY is required}"
    STARTER_APP_NAME=${STARTER_APP_NAME:-Software House}
    STARTER_ALERT_EMAIL=${STARTER_ALERT_EMAIL:-$STARTER_ACME_EMAIL}
    STARTER_SWAP_SIZE=${STARTER_SWAP_SIZE:-2G}

    [[ $STARTER_DOMAIN =~ ^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$ ]] || fail 'STARTER_DOMAIN must be a lower-case host name'
    for email in "$STARTER_ACME_EMAIL" "$STARTER_ALERT_EMAIL"; do
        [[ $email =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]] || fail "invalid e-mail: $email"
    done
    [[ $STARTER_APP_NAME =~ ^[A-Za-z0-9\ ._-]+$ ]] || fail 'STARTER_APP_NAME: letters, digits, spaces, . _ - only'
    [[ $STARTER_DEPLOY_KEY =~ ^ssh-ed25519\ [A-Za-z0-9+/=]+(\ .*)?$ ]] || fail 'STARTER_DEPLOY_KEY must be one ssh-ed25519 public key'
    [[ $STARTER_SWAP_SIZE =~ ^[0-9]+[MG]$ ]] || fail 'STARTER_SWAP_SIZE like 2G'

    # Key-only SSH is enforced below; refuse before it can lock the operator
    # out. Run through sudo, the invoking account (e.g. the cloud image's
    # `ubuntu`) is the operator and root login is disabled entirely.
    if [[ -n ${SUDO_USER:-} && $SUDO_USER != root ]]; then
        OPERATOR_KEYS=$(getent passwd "$SUDO_USER" | cut -d: -f6)/.ssh/authorized_keys
        ROOT_LOGIN=no
    else
        OPERATOR_KEYS=/root/.ssh/authorized_keys
        ROOT_LOGIN=prohibit-password
    fi
    [[ -s $OPERATOR_KEYS ]] || fail "no SSH key in $OPERATOR_KEYS; add your SSH key first"
}

install_base() {
    log 'Base system, swap, time zone, automatic security updates'
    export DEBIAN_FRONTEND=noninteractive
    # Our third-party lists are recreated by add_repositories; a stale one
    # from an earlier run (e.g. an expired key) must not break this update.
    rm -f /etc/apt/sources.list.d/{nginx,mysql,redis,nodesource}.list
    apt-get update -q
    apt-get upgrade -yq
    apt-get install -yq --no-install-recommends \
        acl age ca-certificates certbot curl fail2ban gnupg jq logrotate \
        openssl python3-systemd software-properties-common ufw unattended-upgrades unzip

    timedatectl set-timezone UTC

    if ! swapon --show --noheadings | grep -q .; then
        fallocate -l "$STARTER_SWAP_SIZE" /swapfile
        chmod 600 /swapfile
        mkswap /swapfile >/dev/null
        swapon /swapfile
        grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    fi
    printf 'vm.swappiness=10\n' > /etc/sysctl.d/60-starter.conf
    sysctl -q --system

    cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF
}

add_repositories() {
    log 'Package repositories: ondrej/php, nginx.org, MySQL 8.4 LTS, Redis, NodeSource'
    install -d -m 0755 /etc/apt/keyrings

    add-apt-repository -y ppa:ondrej/php >/dev/null

    curl -fsSL https://nginx.org/keys/nginx_signing.key | gpg --dearmor --yes -o /etc/apt/keyrings/nginx.gpg
    echo 'deb [signed-by=/etc/apt/keyrings/nginx.gpg] http://nginx.org/packages/ubuntu noble nginx' > /etc/apt/sources.list.d/nginx.list
    # Prefer nginx.org (>= 1.28, `http2 on;`) over the Ubuntu 1.24 package.
    printf 'Package: *\nPin: origin nginx.org\nPin-Priority: 900\n' > /etc/apt/preferences.d/99nginx

    # Same signing key as the 2023 file (B7B3B788A8D3785C), renewed to 2027-10.
    curl -fsSL https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 | gpg --dearmor --yes -o /etc/apt/keyrings/mysql.gpg
    echo 'deb [signed-by=/etc/apt/keyrings/mysql.gpg] http://repo.mysql.com/apt/ubuntu noble mysql-8.4-lts' > /etc/apt/sources.list.d/mysql.list

    curl -fsSL https://packages.redis.io/gpg | gpg --dearmor --yes -o /etc/apt/keyrings/redis.gpg
    echo 'deb [signed-by=/etc/apt/keyrings/redis.gpg] https://packages.redis.io/deb noble main' > /etc/apt/sources.list.d/redis.list

    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor --yes -o /etc/apt/keyrings/nodesource.gpg
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_MAJOR}.x nodistro main" > /etc/apt/sources.list.d/nodesource.list

    apt-get update -q
}

install_runtime() {
    log "PHP $PHP_VERSION, Nginx, MySQL, Redis, Node $NODE_MAJOR"
    # Root authenticates over the unix socket; no MySQL root password exists.
    debconf-set-selections <<'EOF'
mysql-community-server mysql-community-server/root-pass password
mysql-community-server mysql-community-server/re-root-pass password
EOF
    # Extensions mirror docker/local/Dockerfile (GD for intervention/image;
    # OPcache is built into PHP 8.5). libgd3 is named explicitly so the
    # ondrej build (with AVIF) replaces the Ubuntu one already on the image,
    # which reports AVIF support but cannot write it.
    apt-get install -yq --no-install-recommends \
        "php$PHP_VERSION-fpm" "php$PHP_VERSION-cli" "php$PHP_VERSION-common" \
        "php$PHP_VERSION-mysql" "php$PHP_VERSION-redis" "php$PHP_VERSION-mbstring" \
        "php$PHP_VERSION-xml" "php$PHP_VERSION-curl" "php$PHP_VERSION-zip" \
        "php$PHP_VERSION-intl" "php$PHP_VERSION-bcmath" "php$PHP_VERSION-gd" libgd3 \
        nginx mysql-server redis nodejs

    php -m | grep -qi '^Zend OPcache$' || fail 'OPcache is not available in PHP CLI'
    # Without AVIF the app still serves WebP and JPEG/PNG; ops:doctor warns.
    php -r 'error_reporting(E_ALL); set_error_handler(fn () => exit(1)); ob_start(); imageavif(imagecreatetruecolor(1, 1)); exit(ob_get_clean() === "" ? 1 : 0);' \
        || log 'warning: GD cannot write AVIF; image variants will skip it'
    nginx_version=$(nginx -v 2>&1 | sed -E 's/.*nginx\/([0-9.]+).*/\1/')
    dpkg --compare-versions "$nginx_version" ge 1.28 || fail "nginx >= 1.28 required, found $nginx_version"
    mysql --version | grep -q ' 8\.4\.' || fail 'MySQL 8.4 expected'
}

create_accounts() {
    log 'Deploy account and directories'
    if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
        useradd --create-home --shell /bin/bash "$DEPLOY_USER"
    fi
    install -d -m 0700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh"
    # Deployer needs a shell but no forwarding; the account has no sudo.
    printf 'no-port-forwarding,no-agent-forwarding,no-X11-forwarding %s\n' "$STARTER_DEPLOY_KEY" \
        > "/home/$DEPLOY_USER/.ssh/authorized_keys"
    chown "$DEPLOY_USER:$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh/authorized_keys"
    chmod 0600 "/home/$DEPLOY_USER/.ssh/authorized_keys"

    install -d -m 0755 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "$APP_ROOT" "$APP_ROOT/shared"
    install -d -m 0755 "$ACME_ROOT"
    install -d -m 0700 -o "$HTTP_USER" -g "$HTTP_USER" "$BACKUP_DIR"
    install -d -m 0750 /etc/starter
    install -d -m 0755 "/var/log/php$PHP_VERSION-fpm"
}

configure_mysql() {
    log 'MySQL: tuning, application and backup accounts'
    cat > /etc/mysql/mysql.conf.d/zz-starter.cnf <<'EOF'
[mysqld]
bind-address = 127.0.0.1
mysqlx = OFF
innodb_buffer_pool_size = 512M
max_connections = 60
performance_schema = OFF
character-set-server = utf8mb4
collation-server = utf8mb4_0900_ai_ci
EOF
    systemctl restart mysql
    mysql -e 'SELECT 1' >/dev/null || fail 'cannot reach MySQL as root over the unix socket'

    local app_password backup_password
    if [[ -f $SHARED_ENV ]]; then
        app_password=$(env_value "$SHARED_ENV" DB_PASSWORD)
    else
        app_password=$(random_hex 24)
    fi
    if [[ -f $BACKUP_ENV ]]; then
        backup_password=$(env_value "$BACKUP_ENV" MYSQL_PASSWORD)
    else
        backup_password=$(random_hex 24)
    fi
    [[ $app_password =~ ^[A-Za-z0-9]+$ && $backup_password =~ ^[A-Za-z0-9]+$ ]] \
        || fail 'existing DB passwords must be alphanumeric'

    # TCP to 127.0.0.1 and the socket resolve to different MySQL hosts.
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS starter CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER IF NOT EXISTS 'starter'@'127.0.0.1' IDENTIFIED BY '$app_password';
CREATE USER IF NOT EXISTS 'starter'@'localhost' IDENTIFIED BY '$app_password';
GRANT ALL PRIVILEGES ON starter.* TO 'starter'@'127.0.0.1', 'starter'@'localhost';
CREATE USER IF NOT EXISTS 'starter_backup'@'127.0.0.1' IDENTIFIED BY '$backup_password';
GRANT SELECT, SHOW VIEW, TRIGGER ON starter.* TO 'starter_backup'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

    APP_DB_PASSWORD=$app_password
    BACKUP_DB_PASSWORD=$backup_password
}

configure_redis() {
    log 'Redis: loopback only, durable queue data'
    cat > /etc/redis/starter.conf <<'EOF'
bind 127.0.0.1 -::1
protected-mode yes
supervised systemd
maxmemory 256mb
# Queue and session keys must never be evicted silently.
maxmemory-policy noeviction
appendonly yes
EOF
    grep -q '^include /etc/redis/starter.conf' /etc/redis/redis.conf \
        || echo 'include /etc/redis/starter.conf' >> /etc/redis/redis.conf
    systemctl enable --now redis-server
    systemctl restart redis-server
    redis-cli ping | grep -q PONG || fail 'Redis does not answer PING'
}

configure_php() {
    log 'PHP-FPM pool'
    install -m 0644 "$TEMPLATES/php-fpm/starter.conf" "/etc/php/$PHP_VERSION/fpm/pool.d/starter.conf"
    rm -f "/etc/php/$PHP_VERSION/fpm/pool.d/www.conf"
    "php-fpm$PHP_VERSION" -t
    systemctl enable "php$PHP_VERSION-fpm"
    systemctl restart "php$PHP_VERSION-fpm"
}

write_env_files() {
    log 'shared/.env and backup configuration (existing files are kept)'
    if [[ ! -f $SHARED_ENV ]]; then
        local app_key health_token
        app_key="base64:$(openssl rand -base64 32)"
        health_token=$(random_hex 32)
        sed -e "s|__APP_NAME__|$STARTER_APP_NAME|g" \
            -e "s|__DOMAIN__|$STARTER_DOMAIN|g" \
            -e "s|__APP_KEY__|$app_key|g" \
            -e "s|__DB_PASSWORD__|$APP_DB_PASSWORD|g" \
            -e "s|__ALERT_EMAIL__|$STARTER_ALERT_EMAIL|g" \
            -e "s|__HEALTH_TOKEN__|$health_token|g" \
            "$TEMPLATES/env/production.env.example" > "$SHARED_ENV.partial"
        if grep -q '__[A-Z_]*__' "$SHARED_ENV.partial"; then
            rm -f "$SHARED_ENV.partial"
            fail 'unreplaced placeholder in deploy/env/production.env.example'
        fi
        chown "$DEPLOY_USER:$HTTP_USER" "$SHARED_ENV.partial"
        chmod 0640 "$SHARED_ENV.partial"
        mv "$SHARED_ENV.partial" "$SHARED_ENV"
    fi

    if [[ ! -f $BACKUP_ENV ]]; then
        # Local copies only until an off-host target and an age key exist
        # (doc 10, "Backup i restore"); set BACKUP_ENCRYPT=age before shipping.
        cat > "$BACKUP_ENV.partial" <<EOF
BACKUP_DB_ENGINE=mysql
MYSQL_HOST=127.0.0.1
MYSQL_TCP_PORT=3306
MYSQL_DATABASE=starter
MYSQL_USER=starter_backup
MYSQL_PASSWORD=$BACKUP_DB_PASSWORD
BACKUP_DIR=$BACKUP_DIR
BACKUP_STORAGE_PATH=$APP_ROOT/shared/storage/app
BACKUP_STORAGE_DIRS="media public"
BACKUP_KEEP=7
BACKUP_STATUS_FILE=$BACKUP_DIR/last-success
BACKUP_ENCRYPT=none
EOF
        chmod 0600 "$BACKUP_ENV.partial"
        mv "$BACKUP_ENV.partial" "$BACKUP_ENV"
    fi
}

configure_nginx() {
    log "Nginx and TLS for $STARTER_DOMAIN"
    # nginx.org packages run as `nginx`; the FPM socket belongs to www-data.
    sed -i -E "s/^user +[a-z-]+;/user $HTTP_USER;/" /etc/nginx/nginx.conf
    rm -f /etc/nginx/conf.d/default.conf
    install -d -m 0755 /etc/nginx/snippets
    install -m 0644 "$TEMPLATES/nginx/security-headers.conf" /etc/nginx/snippets/starter-security-headers.conf

    local live_dir=/etc/letsencrypt/live/$STARTER_DOMAIN
    if [[ ! -f $live_dir/fullchain.pem ]]; then
        # HTTP-only vhost for the first ACME challenge.
        cat > /etc/nginx/conf.d/starter.conf <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $STARTER_DOMAIN;
    location ^~ /.well-known/acme-challenge/ {
        root $ACME_ROOT;
        default_type text/plain;
    }
    location / {
        return 503;
    }
}
EOF
        nginx -t
        systemctl enable --now nginx
        systemctl reload nginx
        certbot certonly --webroot -w "$ACME_ROOT" -d "$STARTER_DOMAIN" \
            --email "$STARTER_ACME_EMAIL" --agree-tos --non-interactive --no-eff-email
    fi

    sed -e "s/example\.test/$STARTER_DOMAIN/g" "$TEMPLATES/nginx/starter.conf" > /etc/nginx/conf.d/starter.conf
    install -d -m 0755 /etc/letsencrypt/renewal-hooks/deploy
    printf '#!/bin/sh\nsystemctl reload nginx\n' > /etc/letsencrypt/renewal-hooks/deploy/reload-nginx
    chmod 0755 /etc/letsencrypt/renewal-hooks/deploy/reload-nginx
    nginx -t
    systemctl enable nginx
    systemctl reload nginx
    systemctl enable --now certbot.timer
}

install_units() {
    log 'systemd units, timers and logrotate'
    install -m 0644 "$TEMPLATES"/systemd/*.service "$TEMPLATES"/systemd/*.timer /etc/systemd/system/
    install -m 0644 "$TEMPLATES/logrotate/starter" /etc/logrotate.d/starter
    systemctl daemon-reload

    # Before the first deploy `current` does not exist yet, so those findings
    # are filtered out; anything else is shown for the operator to review.
    local unit report
    for unit in "$TEMPLATES"/systemd/*.service "$TEMPLATES"/systemd/*.timer; do
        report=$(systemd-analyze verify "/etc/systemd/system/$(basename "$unit")" 2>&1 || true)
        report=$(grep -v "$APP_ROOT/current" <<<"$report" || true)
        [[ -z $report ]] || printf 'WARNING systemd-analyze verify %s:\n%s\n' "$(basename "$unit")" "$report" >&2
    done

    # Enabled for boot; started by `provision.sh start` after the first deploy.
    systemctl enable starter-horizon.service starter-ssr.service \
        starter-scheduler.timer starter-backup.timer >/dev/null
}

harden_access() {
    log 'Firewall, fail2ban and key-only SSH'
    ufw allow OpenSSH >/dev/null
    ufw allow 80/tcp >/dev/null
    ufw allow 443/tcp >/dev/null
    ufw --force enable >/dev/null
    # Minimal 24.04 images have no /var/log/auth.log; read sshd from the journal.
    printf '[sshd]\nenabled = true\nbackend = systemd\n' > /etc/fail2ban/jail.d/starter.local
    systemctl enable fail2ban
    systemctl restart fail2ban

    # sshd keeps the first value it reads; 00- precedes 50-cloud-init.conf.
    cat > /etc/ssh/sshd_config.d/00-starter.conf <<EOF
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin $ROOT_LOGIN
X11Forwarding no
MaxAuthTries 3
EOF
    sshd -t
    systemctl reload ssh
}

start_services() {
    [[ $EUID -eq 0 ]] || fail 'run as root'
    [[ -L $APP_ROOT/current ]] || fail "$APP_ROOT/current does not exist; run the first deploy first"
    systemctl start starter-horizon.service starter-ssr.service starter-scheduler.timer starter-backup.timer
    systemctl --no-pager --lines=0 status starter-horizon.service starter-ssr.service starter-scheduler.timer starter-backup.timer || true
}

summary() {
    local host_key
    host_key=$(cut -d ' ' -f 1,2 /etc/ssh/ssh_host_ed25519_key.pub)
    cat <<EOF

Provisioning finished for $STARTER_DOMAIN.

GitHub Environment "production":
  vars     DEPLOY_HOST=$STARTER_DOMAIN  DEPLOY_USER=$DEPLOY_USER
           DEPLOY_PATH=$APP_ROOT  DEPLOY_SMOKE_HOST=$STARTER_DOMAIN
  secrets  DEPLOY_SSH_KEY = private half of STARTER_DEPLOY_KEY
           DEPLOY_KNOWN_HOSTS = the line below
$STARTER_DOMAIN $host_key

Secrets were written to $SHARED_ENV and $BACKUP_ENV (not printed).
Review MAIL_* and OPS_ALERT_EMAIL in $SHARED_ENV. Keep this SSH session open
and confirm a new key-based login works before closing it.
Next: first deploy (allow_migrations=true), then
  bash $SOURCE_ROOT/scripts/server/provision.sh start
EOF
}

main() {
    case ${1:-} in
        start)
            start_services
            return
            ;;
        '') ;;
        *) fail "unknown command: $1 (expected: start)" ;;
    esac

    preflight
    install_base
    add_repositories
    install_runtime
    create_accounts
    configure_mysql
    configure_redis
    configure_php
    write_env_files
    configure_nginx
    install_units
    harden_access
    summary
}

main "$@"
