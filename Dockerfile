FROM php:8.2-cli

# 1. Instalar dependencias del sistema necesarias para compilar extensiones
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# 2. Instalar extensiones de PHP
# Nota: curl ahora funcionará porque instalamos libcurl4-openssl-dev arriba
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql curl mbstring gd

# 3. Configurar directorio de trabajo
WORKDIR /app

# 4. Copiar archivos del proyecto
COPY . .

# 5. Exponer el puerto (Railway inyecta la variable PORT, usamos 8000 por defecto)
EXPOSE 8000

# 6. Comando de inicio
# Escucha en 0.0.0.0 para ser accesible desde fuera del contenedor
# -t public define la carpeta raíz del servidor web
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8000} -t public"]