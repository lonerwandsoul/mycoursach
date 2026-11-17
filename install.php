<?php
/**
 * Установка базы данных из файла database/favorit_db.sql.
 *
 * Открывается один раз после распаковки проекта:
 *   http://имя-папки/install.php
 * Скрипт создаёт базу favorit_db, таблицы и демонстрационные данные,
 * затем показывает отчёт: какие таблицы созданы и сколько в них записей.
 */

$configFile = __DIR__ . '/config.php';
$config = is_file($configFile) ? (array) require $configFile : [];
$config += [
    'host' => '127.0.0.1', 'port' => '3306', 'database' => 'favorit_db',
    'user' => 'root', 'pass' => '', 'sqlite_fallback' => true, 'sqlite_dir' => __DIR__ . '/data',
];

$sqlFile = __DIR__ . '/database/favorit_db.sql';
$log = [];
$error = '';
$done = false;
$pdo = null;
$driver = '';
$dataDir = null;
$counts = [];

/** Разбивает SQL-файл на отдельные запросы (учитывает кавычки и комментарии). */
function install_split_sql(string $sql): array
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

        if ($char === '-' && $next === '-') {          // комментарий до конца строки
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }
        if ($char === '/' && $next === '*') {          // блочный комментарий
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $length : $end + 1;
            continue;
        }
        if ($char === '#' ) {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }
        if ($char === ';') {
            $trimmed = trim($buffer);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }
    $trimmed = trim($buffer);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }
    return $statements;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_file($sqlFile)) {
        $error = 'Файл database/favorit_db.sql не найден.';
    } else {
        // --- Вариант 1: MySQL (OpenServer) ---
        try {
            $options = [PDO::ATTR_TIMEOUT => 4, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
            $pdo = new PDO('mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';charset=utf8mb4', $config['user'], $config['pass'], $options);
            $log[] = 'Соединение с MySQL установлено: ' . $config['host'] . ':' . $config['port'];
            $driver = 'mysql';

            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $config['database'])
                . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . str_replace('`', '', $config['database']) . '`');
            $log[] = 'База данных «' . $config['database'] . '» готова.';

            $statements = install_split_sql((string) file_get_contents($sqlFile));
            $executed = 0;
            foreach ($statements as $statement) {
                if (preg_match('/^\s*(CREATE DATABASE|USE)/i', $statement)) {
                    continue;                       // уже выполнено выше
                }
                if (preg_match('/^\s*SET\s+(NAMES|FOREIGN_KEY|SQL_MODE)/i', $statement)) {
                    try { $pdo->exec($statement); } catch (Throwable $ignored) {}
                    continue;
                }
                $pdo->exec($statement);
                $executed++;
            }
            $log[] = 'Выполнено запросов из database/favorit_db.sql: ' . $executed . '.';
            $done = true;
        } catch (Throwable $mysqlError) {
            $error = 'MySQL недоступен: ' . $mysqlError->getMessage();
            $log[] = 'Переключаюсь на резервную базу SQLite.';

            // --- Вариант 2: SQLite ---
            foreach ([$config['sqlite_dir'], __DIR__ . '/data', sys_get_temp_dir() . '/favorit'] as $dir) {
                if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                if (!is_dir($dir) || @file_put_contents($dir . '/.t', 'ok') === false) { continue; }
                @unlink($dir . '/.t');
                $dataDir = $dir;
                break;
            }
            if ($dataDir === null) {
                $error .= ' Резервный каталог SQLite недоступен.';
            } else {
                try {
                    require_once __DIR__ . '/analytics_schema.php';
                    $pdo = new PDO('sqlite:' . $dataDir . '/favorit.sqlite');
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    $pdo->exec('PRAGMA foreign_keys = ON');
                    an_init_schema($pdo);
                    $driver = 'sqlite';
                    $done = true;
                    $error = '';
                    $log[] = 'Резервная база SQLite создана: ' . $dataDir . '/favorit.sqlite';
                } catch (Throwable $sqliteError) {
                    $error .= ' SQLite: ' . $sqliteError->getMessage();
                }
            }
        }
    }
}

$tables = ['users', 'an_categories', 'an_products', 'an_channels', 'an_production',
    'an_sales', 'an_orders', 'an_defects', 'an_staff', 'an_targets', 'an_audit'];
if ($done && $pdo instanceof PDO) {
    foreach ($tables as $table) {
        try {
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        } catch (Throwable $e) {
            $counts[$table] = -1;
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Установка базы данных — Фаворит</title>
    <link rel="stylesheet" href="analytics.css">
    <style>
        body { margin: 0; background: #0f1117; font-family: 'Segoe UI', system-ui, sans-serif; }
        .install { max-width: 900px; margin: 0 auto; padding: 40px 20px 60px; }
        .card { background: #fff; border-radius: 18px; padding: 30px 32px; box-shadow: 0 24px 60px rgba(0,0,0,.35); }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
        .brand img { width: 44px; height: 44px; border-radius: 10px; object-fit: cover; }
        .brand b { display: block; color: #1a1f2e; }
        .brand span { font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: #9aa1b3; }
        h1 { font-size: 24px; color: #1a1f2e; margin: 0 0 6px; }
        p.sub { color: #7b8194; margin: 0 0 22px; font-size: 14px; }
        ol, ul { padding-left: 20px; color: #3c4257; font-size: 14px; line-height: 1.7; }
        code { background: #eef2ff; color: #2f6fed; padding: 1px 6px; border-radius: 4px; }
        button { padding: 12px 22px; border: 0; border-radius: 10px; background: #2f6fed; color: #fff;
                 font-size: 14px; font-weight: 600; cursor: pointer; }
        button:hover { background: #2459c8; }
        .ok { background: #e8f8ef; border: 1px solid #b8e8cd; color: #0b6b3a; padding: 12px 14px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; }
        .err { background: #fdecea; border: 1px solid #f6c6c2; color: #a61b12; padding: 12px 14px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 8px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eef0f6; }
        th { color: #7b8194; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .links { margin-top: 22px; display: flex; gap: 10px; flex-wrap: wrap; }
        .links a { display: inline-block; padding: 11px 18px; border-radius: 10px; background: #1a1f2e; color: #fff;
                   text-decoration: none; font-size: 14px; }
        .links a.ghost { background: #eef2ff; color: #2f6fed; }
    </style>
</head>
<body>
<div class="install">
    <div class="card">
        <div class="brand">
            <img src="изображения/logo.jpg" alt="Фаворит">
            <div><span>СМП</span><b>Фаворит Диван</b></div>
        </div>
        <h1>Установка базы данных</h1>
        <p class="sub">Создание базы favorit_db, таблиц и демонстрационных данных</p>

        <?php if ($done): ?>
            <div class="ok">База данных готова. Движок: <b><?= $driver === 'mysql' ? 'MySQL' : 'SQLite' ?></b>.</div>
        <?php elseif ($error !== ''): ?>
            <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($log): ?>
            <ul>
                <?php foreach ($log as $line): ?>
                    <li><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($done): ?>
            <table>
                <thead><tr><th>Таблица</th><th>Записей</th><th>Назначение</th></tr></thead>
                <tbody>
                <?php
                $purpose = [
                    'users' => 'Пользователи и роли (администратор, сотрудник)',
                    'an_categories' => 'Категории продукции',
                    'an_products' => 'Справочник продукции и цены',
                    'an_channels' => 'Каналы продаж, план и затраты',
                    'an_production' => 'Выпуск, брак, часы, себестоимость по цехам',
                    'an_sales' => 'Продажи по каналам за месяц',
                    'an_orders' => 'Портфель заказов и статусы',
                    'an_defects' => 'Брак по причинам и потери',
                    'an_staff' => 'Штат сотрудников и оклады',
                    'an_targets' => 'Плановые и допустимые показатели',
                    'an_audit' => 'Журнал действий пользователей',
                ];
                foreach ($counts as $table => $count): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td><?= $count >= 0 ? $count : '—' ?></td>
                        <td style="color:#7b8194"><?= htmlspecialchars($purpose[$table] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="links">
                <a href="analytics.php">Открыть аналитику</a>
                <a class="ghost" href="login.php">Войти (manager / manager123)</a>
            </div>
        <?php else: ?>
            <p style="font-size:14px;color:#3c4257">
                Скрипт создаст базу <code><?= htmlspecialchars($config['database'], ENT_QUOTES, 'UTF-8') ?></code>
                на сервере <code><?= htmlspecialchars($config['host'] . ':' . $config['port'], ENT_QUOTES, 'UTF-8') ?></code>,
                выполнит <code>database/favorit_db.sql</code> и покажет список таблиц.
                Если MySQL не запущен, будет создана резервная база SQLite — сайт всё равно откроется.
            </p>
            <ol>
                <li>Запустите OpenServer (MySQL должен быть запущен).</li>
                <li>Нажмите кнопку ниже.</li>
                <li>После отчёта перейдите в «Открыть аналитику».</li>
            </ol>
            <form method="post">
                <button type="submit">Установить базу данных</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
