<?php
/**
 * Настройки подключения к базе данных.
 *
 * OpenServer: MySQL слушает 127.0.0.1:3306 (или 127.0.0.1:3307, если в
 * «Настройки → MySQL» указан другой порт). Пользователь по умолчанию — root,
 * пароль пустой.
 *
 * Файл можно не трогать: те же значения подхватываются из переменных
 * окружения DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS.
 */

return [
    // MySQL / MariaDB (OpenServer)
    'host'    => getenv('DB_HOST') ?: '127.0.0.1',
    'port'    => getenv('DB_PORT') ?: '3306',
    'database'=> getenv('DB_NAME') ?: 'favorit_db',
    'user'    => getenv('DB_USER') ?: 'root',
    'pass'    => getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '',

    // Резервный файл SQLite, если MySQL недоступен (демонстрация без сервера БД).
    // Каталог подбирается автоматически: data/, затем временный каталог системы.
    'sqlite_fallback' => true,
    'sqlite_dir'      => getenv('FAVORIT_DATA_DIR') ?: __DIR__ . '/data',
];
