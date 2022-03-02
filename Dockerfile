FROM php:7.2-cli-alpine

WORKDIR /app
COPY . .
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install
CMD [ "./console", "subscriber:urls" ]