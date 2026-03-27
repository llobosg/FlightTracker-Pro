# Usar imagen oficial de PHP con las extensiones necesarias
FROM php:8.2-cli

# Instalar extensiones útiles para tu app (MySQL, Curl, etc.)
RUN docker-php-ext-install pdo pdo_mysql curl mbstring

# Establecer el directorio de trabajo
WORKDIR /app

# Copiar todo el código al contenedor
COPY . .

# Dar permisos de ejecución al script de inicio (si lo usas) o直接使用 php
# Exponer el puerto que Railway asignará (variable de entorno PORT)
EXPOSE 8000

# Comando de inicio:
# Escucha en 0.0.0.0 para ser accesible desde fuera del contenedor
# Usa la variable $PORT de Railway
# -t public indica que la raíz del sitio web es la carpeta public/
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8000} -t public"]