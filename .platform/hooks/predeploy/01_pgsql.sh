#!/bin/bash
set -e
php -m > /var/log/php-modules.log 2>&1
if ! php -r 'exit(extension_loaded("pdo_pgsql") ? 0 : 1);'; then
  echo "pdo_pgsql is not loaded" | tee -a /var/log/php-modules.log
  php -m | tee -a /var/log/php-modules.log
  exit 1
fi
echo "pdo_pgsql=yes" >> /var/log/php-modules.log
