#!/bin/bash
set -e

php artisan migrate --force
php artisan feed:rescore --rebuild || true
php artisan config:cache
php artisan route:clear
php artisan view:cache

php artisan schedule:work > /dev/null 2>&1 &

apache2-foreground
