FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy project files into the container
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Create uploads folder and set appropriate permissions
RUN mkdir -p uploads && \
    chown -R www-data:www-data /var/www/html/ && \
    chmod -R 755 /var/www/html/

# Expose port 80
EXPOSE 80
