FROM php:8.3-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite (needed for .htaccess routing)
RUN a2enmod rewrite headers

# Copy project files into Apache's web root
COPY . /var/www/html/

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Fix .htaccess RewriteBase — Railway serves from root, not /horizon_academy/
RUN sed -i 's|RewriteBase /horizon_academy/|RewriteBase /|g' /var/www/html/.htaccess

# Set permissions
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

EXPOSE 80
