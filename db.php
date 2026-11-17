<?php
/**
 * Подключение к базе данных.
 *
 * Порядок работы:
 *   1. Параметры берутся из config.php (или переменных окружения).
 *   2. Если базы нет — она создаётся сама (CREATE DATABASE).
 *   3. Если MySQL недоступен, а резерв разрешён — используется SQLite,
 *      чтобы сайт открывался даже без запущенного OpenServer/MySQL.
 *   4. Таблицы и демонстрационные данные создаются автоматически
 *      при первом открытии любой страницы.
 *
 * Режим проверяется в разделе «Данные» (admin.php).
 */

require_once __DIR__ . '/analytics_schema.php';

if (!function_exists('db_config')) {
    /** Настройки подключения из config.php. */
    function db_config(): array
    {
        static $config = null;
        if ($config === null) {
            $file = __DIR__ . '/config.php';
            $config = is_file($file) ? (array) require $file : [];
            $config += [
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => 'favorit_db',
                'user' => 'root',
                'pass' => '',
                'sqlite_fallback' => true,
                'sqlite_dir' => __DIR__ . '/data',
            ];
        }
        return $config;
    }
}

if (!function_exists('db_dsn')) {
    /** DSN для MySQL без имени базы (нужен, чтобы создать базу при первом запуске). */
    function db_dsn(bool $withDatabase = true): string
    {
        $config = db_config();
        $dsn = 'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';charset=utf8mb4';
        if ($withDatabase) {
            $dsn = 'mysql:host=' . $config['host'] . ';port=' . $config['port']
                . ';dbname=' . $config['database'] . ';charset=utf8mb4';
        }
        return $dsn;
    }
}

if (!function_exists('db_configure')) {
    /** Настройки PDO: исключения и ассоциативные массивы. */
    function db_configure(PDO $pdo): PDO
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }
}

if (!function_exists('db_sqlite_file')) {
    /** Файл SQLite: ищем каталог, куда PHP действительно может писать. */
    function db_sqlite_file(): ?string
    {
        $config = db_config();
        $candidates = array_filter([
            (string) $config['sqlite_dir'],
            __DIR__ . '/data',
            __DIR__ . '/../favorit-data',
            sys_get_temp_dir() . '/favorit',
            (string) getenv('LOCALAPPDATA') . '/favorit',
        ], static fn ($dir) => $dir !== '' && $dir !== '/favorit');

        foreach ($candidates as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (!is_dir($dir)) {
                continue;
            }
            $probe = $dir . '/.write-test';
            if (@file_put_contents($probe, 'ok') !== false) {
                @unlink($probe);
                return $dir . '/favorit.sqlite';
            }
        }
        return null;
    }
}

if (!function_exists('db_connect_mysql')) {
    /** Подключение к MySQL. Если базы нет — создаём её и повторяем попытку. */
    function db_connect_mysql(): PDO
    {
        $config = db_config();
        $options = [
            PDO::ATTR_TIMEOUT => 3,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        try {
            return new PDO(db_dsn(true), $config['user'], $config['pass'], $options);
        } catch (PDOException $e) {
            $missingDatabase = (int) $e->getCode() === 1049
                || stripos($e->getMessage(), 'Unknown database') !== false;
            if (!$missingDatabase) {
                throw $e;
            }
        }

        // Базы ещё нет: создаём её от имени сервера и подключаемся заново.
        $server = new PDO(db_dsn(false), $config['user'], $config['pass'], $options);
        $server->exec(
            'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $config['database'])
            . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        $server = null;

        return new PDO(db_dsn(true), $config['user'], $config['pass'], $options);
    }
}

if (!function_exists('db_import_dump')) {
    /**
     * Импорт database/favorit_db.sql в MySQL, если база пустая
     * (в ней ещё нет таблицы an_production). Так проект работает
     * после простого копирования в OpenServer, без phpMyAdmin.
     */
    function db_import_dump(PDO $pdo): int
    {
        $file = __DIR__ . '/database/favorit_db.sql';
        if (!is_file($file)) {
            return 0;
        }
        $statements = db_split_sql((string) file_get_contents($file));
        $executed = 0;
        foreach ($statements as $statement) {
            if (preg_match('/^\s*(CREATE DATABASE|USE)\s/i', $statement)) {
                continue;                                    // база уже открыта
            }
            if (preg_match('/^\s*SET\s/i', $statement)) {
                try { $pdo->exec($statement); } catch (Throwable $ignored) {}
                continue;
            }
            $pdo->exec($statement);
            $executed++;
        }
        return $executed;
    }
}

if (!function_exists('db_split_sql')) {
    /** Делит SQL-файл на отдельные запросы (кавычки и комментарии учитываются). */
    function db_split_sql(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = null;
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $quote !== '`') {
                    $buffer .= $next;
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if (($char === '-' && $next === '-') || $char === '#') {
                while ($i < $length && $sql[$i] !== "\n") { $i++; }
                continue;
            }
            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') { $statements[] = $trimmed; }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $trimmed = trim($buffer);
        if ($trimmed !== '') { $statements[] = $trimmed; }
        return $statements;
    }
}

$pdo = null;
$dbError = '';
$dbEngine = 'mysql';
$dbFile = null;
$dbImported = 0;

try {
    $pdo = db_configure(db_connect_mysql());
    $dbEngine = 'mysql';

    // Пустая база (только что создана): импортируем структуру и демо-данные
    // из database/favorit_db.sql, чтобы цифры в отчётах были готовы сразу.
    $empty = false;
    try {
        $pdo->query('SELECT 1 FROM an_production LIMIT 1');
    } catch (Throwable $notYet) {
        $empty = true;
    }
    if ($empty) {
        $dbImported = db_import_dump($pdo);
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    $dbEngine = 'sqlite';
    $pdo = null;

    if (!empty(db_config()['sqlite_fallback'])) {
        $dbFile = db_sqlite_file();
        if ($dbFile !== null) {
            try {
                $pdo = db_configure(new PDO('sqlite:' . $dbFile));
                $pdo->exec('PRAGMA foreign_keys = ON');
            } catch (Throwable $sqliteError) {
                $pdo = null;
                $dbError .= ' | SQLite: ' . $sqliteError->getMessage();
            }
        }
    }
}

if ($pdo === null) {
    http_response_code(500);
    $host = htmlspecialchars(db_config()['host'] . ':' . db_config()['port'], ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>Нет соединения с БД</title>'
        . '<body style="font-family:Segoe UI,sans-serif;max-width:760px;margin:60px auto;line-height:1.6;color:#1a1f2e">'
        . '<h1 style="font-size:22px">Не удалось подключиться к базе данных</h1>'
        . '<p>MySQL по адресу <b>' . $host . '</b> недоступен, резервный файл SQLite создать не удалось.</p>'
        . '<ol><li>Запустите OpenServer (MySQL должен быть зелёным).</li>'
        . '<li>Проверьте параметры в файле <code>config.php</code>.</li>'
        . '<li>Импортируйте <code>database/favorit_db.sql</code> через phpMyAdmin '
        . 'или откройте <code>install.php</code> — он создаст базу сам.</li></ol>'
        . '<p style="color:#a61b12;font-size:13px">Техническая информация: '
        . htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
    exit;
}

// Таблицы и демо-данные создаются автоматически (идемпотентно).
an_init_schema($pdo);

// Путь к файлу SQLite показываем относительно каталога проекта — так понятнее.
$dbFilePublic = $dbFile;
if ($dbFile !== null && str_starts_with($dbFile, __DIR__ . DIRECTORY_SEPARATOR)) {
    $dbFilePublic = substr($dbFile, strlen(__DIR__) + 1);
}

$dbInfo = [
    'engine' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
    'imported' => $dbImported,
    'database' => db_config()['database'],
    'host' => db_config()['host'] . ':' . db_config()['port'],
    'file' => $dbFilePublic,
    'error' => $dbError,
];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
