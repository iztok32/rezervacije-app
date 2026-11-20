# ─── Frontend asset build (Vite) ──────────────────────────────────────────────
# Compiles public/build/ inside Docker so the server needs no local Node/assets.
FROM node:22 AS assets

WORKDIR /app

# Install JS deps (cached layer)
COPY package.json package-lock.json ./
RUN npm ci

# Copy the rest of the source and build the Vite bundle
COPY . .
RUN npm run build

# ─── Production image ─────────────────────────────────────────────────────────
# Frontend assets (public/build/) are compiled by the `assets` stage above and
# copied into this image below — no separate CI/local pre-build is required.
FROM php:8.4-fpm AS production

LABEL maintainer="Iztok Vozlič"


# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    libpq-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    curl \
    git \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        bcmath \
        opcache \
        intl \
        pcntl \
        exif

# Composer — installed directly to avoid pulling a separate image from Docker Hub
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

WORKDIR /var/www/html

# Install PHP dependencies (cached layer)
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --optimize-autoloader \
    --prefer-dist

# Copy application source
COPY . .

# Copy compiled frontend assets from the Node build stage
COPY --from=assets /app/public/build ./public/build

# Finalize composer autoloader
RUN composer dump-autoload --optimize --no-dev

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Copy Docker configs
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
