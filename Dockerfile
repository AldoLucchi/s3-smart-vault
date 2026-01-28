FROM --platform=linux/amd64 bref/php-83-fpm:2

COPY . /var/task

RUN chmod -R 775 /var/task/storage /var/task/bootstrap/cache

CMD ["public/index.php"]