# Cadangan jika tidak memakai Vercel (mis. Nivor / Railway / Render).
FROM php:8.3-cli

# Driver PostgreSQL untuk PDO (dibutuhkan boot.php).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} router.php"]