<?php
/**
 * Подключение к БД. Основной вариант — MySQL (OpenServer), резервный — SQLite,
 * чтобы аналитический модуль запускался и без MySQL (демонстрация, защита курсовой).
 */
require_once __DIR__ . '/analytics_schema.php';

$host = getenv('DB_HOST') ?: '127.127.126.32';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'favorit_db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_TIMEOUT => 3]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // SQLite-резерв: ищем каталог, куда реально может писать PHP (Desktop/OneDrive часто закрыт).
    $candidates = [
        (string) getenv('FAVORIT_DATA_DIR'),
        __DIR__ . '/data',
        __DIR__ . '/../favorit-data',
        sys_get_temp_dir() . '/favorit',
        (string) getenv('LOCALAPPDATA') . '/favorit',
    ];
    $file = null;
    foreach ($candidates as $dir) {
        if ($dir === '') {
            continue;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir)) {
            continue;
        }
        $probe = $dir . '/.write-test';
        if (@file_put_contents($probe, 'ok') !== false) {
            @unlink($probe);
            $file = $dir . '/favorit.sqlite';
            break;
        }
    }
    if ($file === null) {
        die('Не удалось открыть SQLite-базу: нет каталога с правами на запись.');
    }
    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
}

an_init_schema($pdo);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
