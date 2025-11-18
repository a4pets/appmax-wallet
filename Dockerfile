FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    sqlite \
    sqlite-dev \
    nginx \
    supervisor \
    linux-headers \
    openssl-dev \
    postgresql-dev \
    $PHPIZE_DEPS

RUN docker-php-ext-install pdo pdo_sqlite opcache pcntl

RUN pecl install redis && docker-php-ext-enable redis

RUN yes no | pecl install swoole && docker-php-ext-enable swoole

RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=256"; \
    echo "opcache.interned_strings_buffer=16"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.revalidate_freq=60"; \
    echo "opcache.fast_shutdown=1"; \
    echo "opcache.enable_cli=1"; \
    echo "opcache.jit_buffer_size=120M"; \
    echo "opcache.jit=tracing"; \
    echo "opcache.validate_timestamps=0"; \
} > /usr/local/etc/php/conf.d/opcache.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock artisan ./

RUN mkdir -p bootstrap/cache storage/framework/sessions storage/framework/views storage/framework/cache

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

COPY . .

RUN composer run-script post-autoload-dump

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 storage bootstrap/cache

RUN mkdir -p database \
    && touch database/database.sqlite \
    && chown www-data:www-data database/database.sqlite

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN ln -sf /dev/stdout /var/log/nginx/access.log \
 && ln -sf /dev/stderr /var/log/nginx/error.log

RUN mkdir -p /var/log/supervisor

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
