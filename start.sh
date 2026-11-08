#!/bin/bash
set -e

php artisan migrate --force
php artisan feed:rescore --rebuild || true
php artisan config:cache
php artisan route:clear
php artisan view:cache

php artisan schedule:work > /dev/null 2>&1 &
   php artisan route:list --path=rules || true
   ls -la bootstrap/cache routes || true
   (sleep 10; echo "=== SELF TEST ==="; curl -si http://localhost/rules | head -15; echo "=== INDEX TEST ==="; curl -si http://localhost/index.php | head -8) &
apache2-foreground
