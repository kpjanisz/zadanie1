-- Runs once, on first initialisation of the data volume.
-- The MySQL entrypoint only grants MYSQL_USER access to MYSQL_DATABASE, so the
-- PHPUnit database (<db>_test, created by dama/doctrine-test-bundle's dbname_suffix)
-- has to be provisioned explicitly.

CREATE DATABASE IF NOT EXISTS `app_test`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `app_test`.* TO 'app'@'%';

FLUSH PRIVILEGES;
