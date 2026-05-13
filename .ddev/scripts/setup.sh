#!/bin/bash
# Runs inside the DDEV web container on every `ddev start`.
# All steps are idempotent — safe to run multiple times.

set -euo pipefail

WP_PATH=/var/www/html/wordpress
PLUGIN_LINK=$WP_PATH/wp-content/plugins/partner-directory
PLUGIN_SRC=/var/www/html

# ── 1. Download WordPress core ────────────────────────────────────────────────
if [ ! -f "$WP_PATH/wp-load.php" ]; then
    echo "→ Downloading WordPress core..."
    wp core download --path="$WP_PATH" --skip-content --quiet
fi

# ── 2. Create wp-config.php ───────────────────────────────────────────────────
if [ ! -f "$WP_PATH/wp-config.php" ]; then
    echo "→ Creating wp-config.php..."
    wp config create \
        --path="$WP_PATH" \
        --dbname=db \
        --dbuser=db \
        --dbpass=db \
        --dbhost=db \
        --quiet
fi

# ── 3. Install WordPress database ─────────────────────────────────────────────
if ! wp core is-installed --path="$WP_PATH" --quiet 2>/dev/null; then
    echo "→ Installing WordPress..."
    wp core install \
        --path="$WP_PATH" \
        --url="${DDEV_PRIMARY_URL:-https://partner-directory.ddev.site}" \
        --title="Partner Directory Dev" \
        --admin_user=admin \
        --admin_password=admin \
        --admin_email=admin@example.com \
        --skip-email \
        --quiet
fi

# ── 4. Symlink the plugin ─────────────────────────────────────────────────────
mkdir -p "$WP_PATH/wp-content/plugins"
if [ ! -L "$PLUGIN_LINK" ]; then
    echo "→ Symlinking plugin..."
    ln -sf "$PLUGIN_SRC" "$PLUGIN_LINK"
fi

# ── 5. Activate the plugin ────────────────────────────────────────────────────
if ! wp plugin is-active partner-directory --path="$WP_PATH" --quiet 2>/dev/null; then
    echo "→ Activating partner-directory plugin..."
    wp plugin activate partner-directory --path="$WP_PATH" --quiet
fi

# ── 6. Build front-end assets ─────────────────────────────────────────────────
if [ ! -d "$PLUGIN_SRC/node_modules" ]; then
    echo "→ Installing npm dependencies..."
    cd "$PLUGIN_SRC" && npm install --silent
fi

echo "→ Building block assets..."
cd "$PLUGIN_SRC" && npm run build --silent

echo ""
echo "✓ Setup complete."
echo "  Site URL  : ${DDEV_PRIMARY_URL:-https://partner-directory.ddev.site}"
echo "  Admin URL : ${DDEV_PRIMARY_URL:-https://partner-directory.ddev.site}/wp-admin"
echo "  Username  : admin"
echo "  Password  : admin"
echo ""
