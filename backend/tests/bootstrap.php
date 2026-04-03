<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    // overrideExistingVars=true — чтобы .env.test перекрывал переменные процесса Docker-контейнера
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env', 'dev', ['test'], true);
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
