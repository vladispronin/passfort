# При проблеме с недоступностью сервера после деплоя проверить правильность конфигов на сервере:
# Контейнер nginx должен запускаться именно с этими конфигами!

## /opt/project/passfort/docker/nginx/nginx.conf
### это корневой конфиг nginx 
```
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;
    sendfile on;
    keepalive_timeout 65;

    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;

    include /etc/nginx/conf.d/*.conf;
}
```

## /opt/project/passfort/docker/nginx/conf.d/app.conf
### это доп конфиг с настройками для https. в проекте он находится в файле [`docker/nginx/conf.d/prod/app.conf`](/docker/nginx/conf.d/prod/app.conf)"
### файл [`docker/nginx/conf.d/app.conf`](/docker/nginx/conf.d/app.conf) чисто для локальной разработки
```
server {
    listen 80;
    server_name _;
    return 301 https://$host$request_uri;
}

server {
listen 443 ssl;
server_name _;

    ssl_certificate /etc/nginx/ssl/selfsigned.crt;
    ssl_certificate_key /etc/nginx/ssl/selfsigned.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;" always;

    # Frontend SPA (собранная статика)
    root /var/www/frontend/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # API proxy to PHP
    location /api/ {
        root /var/www/backend/public;
        try_files $uri /index.php$is_args$args;

        location ~ \.php$ {
            fastcgi_pass php:9000;
            fastcgi_split_path_info ^(.+\.php)(/.*)$;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME /var/www/backend/public/index.php;
            fastcgi_param DOCUMENT_ROOT /var/www/backend/public;
            fastcgi_read_timeout 60;
        }
    }

    location ~ ^/index\.php(/|$) {
        root /var/www/backend/public;
        fastcgi_pass php:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/backend/public/index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/backend/public;
        internal;
    }
}
```
