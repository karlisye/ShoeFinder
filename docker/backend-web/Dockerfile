FROM nginx:1.28-alpine

COPY docker/backend-web/default.conf /etc/nginx/conf.d/default.conf
COPY backend/public/ /var/www/html/public/

RUN mkdir -p /var/www/html/storage/app/public
