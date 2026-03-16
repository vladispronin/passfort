CREATE DATABASE IF NOT EXISTS passfort CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS passfort_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON passfort.* TO 'passfort_user'@'%';
GRANT ALL PRIVILEGES ON passfort_test.* TO 'passfort_user'@'%';
FLUSH PRIVILEGES;
