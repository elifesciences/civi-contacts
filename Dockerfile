FROM php:7.2-cli-alpine

ENV COMPOSER composer-standalone.json
WORKDIR /app
COPY . .
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install
CMD [ "./console", "subscriber:urls" ]
