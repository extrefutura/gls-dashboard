FROM php:8.2-apache
COPY index.html /var/www/html/index.html
COPY n8n-proxy.php /var/www/html/n8n-proxy.php
EXPOSE 80
