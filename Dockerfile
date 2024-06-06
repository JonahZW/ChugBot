FROM php:8.0-apache

RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli
RUN apt-get update && apt-get upgrade -y

RUN a2enmod ssl

RUN echo "Installed PHP"

COPY /chugbot /var/www/html/

# Copy the SSL certificates
COPY /Docker/certs /etc/apache2/ssl

# Copy the custom config file
COPY /Docker/my-httpd.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80 443

RUN echo "Installed files"

VOLUME ["/etc/mysql", "/var/lib/mysql"]