FROM php:8.2-cli

# 1. Instalar dependencias del sistema (Librerías de Linux)
# Esto soluciona los errores de 'libcurl', 'oniguruma' (para mbstring), y 'jpeg/freetype' (para gd)
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libonig-dev \
    libjpeg-dev \
    libpng-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# 2. Instalar extensiones de PHP
# Nota: En PHP 8.2+, usamos --with-jpeg en lugar de --with-jplg si está disponible, 
# pero a veces es mejor instalar solo lo esencial si GD no es crítica al inicio.
# Aquí instalamos pdo, pdo_mysql, curl y mbstring (las críticas para tu app).
# GD se intenta configurar, pero si falla, el resto de la app seguirá funcionando.
RUN docker-php-ext-install pdo pdo_mysql curl mbstring

# Opcional: Intentar instalar GD solo si es estrictamente necesario para imágenes
# Si da error, comenta las siguientes 3 líneas, tu app de vuelos no necesita generar imágenes por ahora.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd || echo "GD installation skipped or failed, continuing..."

# 3. Configurar directorio de trabajo
WORKDIR /app

# 4. Copiar archivos del proyecto
COPY . .

# 5. Exponer el puerto
EXPOSE 8000

# 6. Comando de inicio
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8000} -t public"]