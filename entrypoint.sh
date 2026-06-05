#!/bin/bash
set -e

BASE_URL=${BASE_URL:-http://localhost}
LANGUAGE=${LANGUAGE:-english}
DEBUG_MODE=${DEBUG_MODE:-false}
DB_HOST=${DB_HOST:-localhost}
DB_NAME=${DB_NAME:-easyappointments}
DB_USERNAME=${DB_USERNAME:-root}
DB_PASSWORD=${DB_PASSWORD:-}

# Generate config.php from environment variables
cat > /var/www/html/config.php << EOF
<?php
class Config
{
    const BASE_URL = '${BASE_URL}';
    const LANGUAGE = '${LANGUAGE}';
    const DEBUG_MODE = ${DEBUG_MODE};

    const DB_HOST = '${DB_HOST}';
    const DB_NAME = '${DB_NAME}';
    const DB_USERNAME = '${DB_USERNAME}';
    const DB_PASSWORD = '${DB_PASSWORD}';
}
EOF

chown www-data:www-data /var/www/html/config.php

# Configure Apache to listen on Railway's PORT
APP_PORT=${PORT:-80}
sed -i "s/Listen 80/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-enabled/000-default.conf

echo "Starting Easy!Appointments on port ${APP_PORT}..."

# Fix Apache MPM: ensure only mpm_prefork is loaded
find /etc/apache2/mods-enabled/ \( -name 'mpm_*.conf' -o -name 'mpm_*.load' \) -delete 2>/dev/null || true
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load

exec apache2-foreground
