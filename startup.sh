#!/bin/sh
set -e

# Ensure the latest sources from this container lives in the volume.
# Working dir is /var/www from the container.
tar cf - --one-file-system -C /seatplus . | tar xf -

chown -R www-data:www-data storage

# Ensure we have .env, if we dont, try and fix it automatically
if [ ! -f .env ]; then \
    cp .env.example .env && \

    # Fix up MariaDB and Redis connection info
    sed -i -- 's/DB_USERNAME=seatplus/DB_USERNAME='$MYSQL_USER'/g' .env
    sed -i -- 's/DB_PASSWORD=secret/DB_PASSWORD='$MYSQL_PASSWORD'/g' .env
    sed -i -- 's/DB_DATABASE=seatplus/DB_DATABASE='$MYSQL_DATABASE'/g' .env
    sed -i -- 's/DB_HOST=127.0.0.1/DB_HOST=mariadb/g' .env
    sed -i -- 's/APP_DEBUG=false/APP_DEBUG=true/g' .env
    sed -i -- 's/REDIS_HOST=127.0.0.1/REDIS_HOST=redis/g' .env

    php artisan key:generate
fi

# Wait for the database
while ! mysqladmin ping -hmariadb -u$MYSQL_USER -p$MYSQL_PASSWORD --silent; do

    echo "MariaDB container might not be ready yet... sleeping..."
    sleep 3
done

php artisan vendor:publish --force --all
php artisan migrate

php-fpm -F

