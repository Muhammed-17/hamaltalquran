#!/bin/bash
set -e

# Render injects PORT env var - nginx must listen on it
PORT="${PORT:-8080}"
sed -i "s/listen 80;/listen ${PORT};/" /etc/nginx/sites-available/default

cd /var/www/html

# التوقف فورًا إذا لم يُضبط APP_KEY في متغيرات Render
if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY غير مضبوط. أضفه من Environment في Render."
    exit 1
fi

# Cache config/routes/views for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations (safe to run every deploy)
php artisan migrate --force

# Create storage symlink
php artisan storage:link || true

# إعادة الصلاحيات لـ www-data بعد أوامر artisan
chown -R www-data:www-data storage bootstrap/cache

# Start PHP-FPM in background, Nginx in foreground
php-fpm -D
nginx -g "daemon off;"