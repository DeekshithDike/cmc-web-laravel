# City Max Crypto — Laravel web

See the repository [README](../README.md) for local setup.

## Deploy

```bash
cd cmc-web-laravel
cp .env.example .env
# Set APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, CALC_*, SEED_* passwords

composer install --no-dev --optimize-autoloader
php artisan key:generate
chown -R www-data:www-data storage bootstrap/cache
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Cron:

```cron
* * * * * cd /var/www/App/cmc-web-laravel && php artisan schedule:run >> /dev/null 2>&1
```

After `git pull`:

```bash
cd cmc-web-laravel
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
