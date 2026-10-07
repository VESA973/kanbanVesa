#!/usr/bin/env bash
# Installs the Mercure hub for TaskBoard (Debian + Apache). Run as root from the project root:
#   sudo deploy/mercure/install.sh
set -euo pipefail

VERSION="${MERCURE_VERSION:-1.0.4}"
PROJECT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
DEPLOY_DIR="$PROJECT_DIR/deploy/mercure"
VHOST="${APACHE_VHOST:-/etc/apache2/sites-available/kanban.conf}"

[[ $EUID -eq 0 ]] || { echo "Run as root (sudo)." >&2; exit 1; }

# The hub must verify tokens with the same secret as Symfony. Read from the env files
# (not with bin/console: running it as root would leave root-owned cache files).
SECRET="$(cat "$PROJECT_DIR"/.env.prod.local "$PROJECT_DIR"/.env.local "$PROJECT_DIR"/.env.dev.local 2>/dev/null \
    | grep -oE '^MERCURE_JWT_SECRET="?[0-9a-f]{64}' | grep -oE '[0-9a-f]{64}' | head -1 || true)"
[[ -n "$SECRET" ]] || { echo "MERCURE_JWT_SECRET (64 hex characters) not found in .env.prod.local, .env.local or .env.dev.local." >&2; exit 1; }

echo "1/5 Downloading Mercure $VERSION"
TMP="$(mktemp -d)"
curl -fsSL -o "$TMP/mercure.tgz" "https://github.com/dunglas/mercure/releases/download/v${VERSION}/mercure_Linux_x86_64.tar.gz"
tar -xzf "$TMP/mercure.tgz" -C "$TMP" mercure
install -m 0755 "$TMP/mercure" /usr/local/bin/mercure
rm -rf "$TMP"

echo "2/5 Writing /etc/mercure"
install -d -m 0755 /etc/mercure
install -m 0644 "$DEPLOY_DIR/Caddyfile" /etc/mercure/Caddyfile
umask 077
cat > /etc/mercure/mercure.env <<ENV
MERCURE_PUBLISHER_JWT_KEY=$SECRET
MERCURE_SUBSCRIBER_JWT_KEY=$SECRET
ENV
chmod 0600 /etc/mercure/mercure.env
umask 022
env $(cat /etc/mercure/mercure.env) MERCURE_DB_PATH=/tmp/mercure-validate.db /usr/local/bin/mercure validate --config /etc/mercure/Caddyfile >/dev/null

echo "3/5 Installing the systemd service"
install -m 0644 "$DEPLOY_DIR/mercure.service" /etc/systemd/system/mercure.service
systemctl daemon-reload
systemctl enable --now mercure
systemctl restart mercure

echo "4/5 Configuring Apache"
install -m 0644 "$DEPLOY_DIR/apache-mercure.conf" /etc/apache2/conf-available/taskboard-mercure.conf
a2enmod -q proxy proxy_http
if ! grep -q "taskboard-mercure.conf" "$VHOST"; then
    cp "$VHOST" "$VHOST.bak-$(date +%Y%m%d%H%M%S)"
    sed -i 's#</VirtualHost>#    Include /etc/apache2/conf-available/taskboard-mercure.conf\n</VirtualHost>#' "$VHOST"
fi
apache2ctl configtest
systemctl reload apache2

echo "5/5 Checking"
sleep 1
systemctl is-active --quiet mercure && echo "Mercure is running." || { journalctl -u mercure -n 20 --no-pager; exit 1; }
code="$(curl -s -o /dev/null -w '%{http_code}' 'http://127.0.0.1:3000/.well-known/mercure?match=test')"
echo "Hub answers on 127.0.0.1:3000 (HTTP $code, 401 expected without token)."
echo "Done. Open a board in two browsers: changes now appear without reloading."
