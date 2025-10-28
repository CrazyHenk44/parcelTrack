# --- Builder Stage ---
# This stage installs all dependencies (including dev), and runs tests.
FROM php:8.2-fpm-alpine as builder
WORKDIR /opt/parceltrack

# Install system dependencies required for PHP extensions and Composer
# Also install python3, pip, and ncurses for the status.py CLI tool
RUN apk add --no-cache \
    curl-dev \
    gettext \
    libzip-dev \
    nginx \
    supervisor \
    icu-dev \
    python3 \
    py3-pip \
    ncurses-dev
RUN apk add --no-cache nodejs npm chromium
RUN npm install puppeteer
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

# Install PHP extensions
RUN docker-php-ext-install -j$(nproc) \
    curl \
    zip \
    intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Copy composer files first to leverage Docker cache
COPY composer.json composer.lock ./

# Copy the rest of the application source code
COPY . .

# Ensure the translations directory exists for the test suite
RUN mkdir -p translations

# Update dependencies, install them (including dev), run code-style fixer, and run the test suite
RUN composer update --optimize-autoloader \
    && composer run fix-style \
    && vendor/bin/phpunit tests

# After tests pass, reinstall with --no-dev to create a clean production vendor directory
RUN composer install --no-dev --optimize-autoloader


# --- Final Production Stage ---
# This stage creates the lean final image.
FROM php:8.2-fpm-alpine
WORKDIR /opt/parceltrack

# Install only the necessary production system dependencies
RUN apk add --no-cache gettext nginx supervisor icu-dev curl-dev libzip-dev python3 py3-pip
RUN apk add --no-cache nodejs npm chromium
RUN npm install puppeteer
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium
RUN pip install apprise --break-system-packages
RUN docker-php-ext-install -j$(nproc) curl zip intl

# Copy configurations into their final locations
# These paths are relative to the build context (the project root).
COPY docker/php/supervisord.conf /etc/supervisord.conf
COPY docker/php/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php/crontab /var/spool/cron/crontabs/root
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Copy the entrypoint script
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

# Copy application source and production dependencies from the builder stage
COPY --from=builder /opt/parceltrack/src ./src
COPY --from=builder /opt/parceltrack/vendor ./vendor
COPY --from=builder /opt/parceltrack/web ./web
COPY --from=builder /opt/parceltrack/developer ./developer

# Set correct permissions for copied configuration files
RUN chmod +x /usr/local/bin/entrypoint.sh

# Create necessary directories for data, logs, and translations cache
# These directories will be mounted as volumes for persistence.
# Set appropriate permissions for the web server user (www-data)
RUN mkdir -p data translations \
    && chown -R www-data:www-data data translations \
    && chmod -R 775 data translations

# Expose port 80 for the Nginx web server
EXPOSE 80

# Set the entrypoint script to run on container start
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Start Supervisor, which will manage php-fpm, nginx, and cron
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
