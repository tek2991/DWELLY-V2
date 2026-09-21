# syntax=docker/dockerfile:1

# Global build argument must be declared before the first FROM
ARG BASE_IMAGE=sites-infra/shared-php-base:latest

# -----------------------------------------------------------
# Stage 1: Build Frontend Assets (Vite & Tailwind CSS)
# -----------------------------------------------------------
FROM node:22-alpine AS frontend

WORKDIR /app

# Copy package manifests and install dependencies
COPY package*.json ./
RUN npm ci

# Copy application files for Tailwind CSS class scanning and Vite bundle
COPY . .

# Build production assets into /app/public/build
RUN npm run build

# -----------------------------------------------------------
# Stage 2: Application Container (PHP-FPM)
# -----------------------------------------------------------
FROM ${BASE_IMAGE}

WORKDIR /var/www/html

# Copy application source code
COPY . .

# Copy compiled frontend assets from the node builder stage
COPY --from=frontend /app/public/build ./public/build

# Copy custom low-resource ondemand PHP-FPM configuration
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Install production dependencies
# (packages/tek2991/accounting is included in build context)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set correct permissions for Laravel runtime directories
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
