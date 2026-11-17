<?php
require_once 'db.php';
require_once 'analytics_lib.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tables = [
    'an_orders' => ['Заказы', 'orders.php', 'клиент, продукт, канал, сумма, статус, срок'],
    'an_production' => ['Производство', 'production.php', 'цех, выпуск, брак, часы, себестоимость'],
    'an_defects' => ['Качество (брак)', 'reviews.php', 'цех, причина, количество, потери'],
    'an_sales' => ['Продажи', 'sales.php', 'канал, заказы, выручка'],
    'an_channels' => ['Каналы продаж', 'partners.php', 'план, затраты, лиды'],
    'an_staff' => ['Сотрудники', 'hr.php', 'ФИО, отдел, должность, оклад'],
    'an_products' => ['Справочник продукции', 'products.php', 'категория, цена, единица'],
    'an_categories' => ['Категории', 'products.php', 'название'],
    'an_targets' => ['Плановые показатели', 'reports.php', 'загрузка, брак, план выручки'],
    'an_audit' => ['Журнал действий', 'reports.php', 'кто, что и когда сделал'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && an_can_edit()) {
    an_check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'reset' && an_is_admin()) {
        an_reset_demo($pdo);
        an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Демо-данные пересозданы', '');
        an_flash('Демонстрационные данные пересозданы за май–октябрь 2025.');
    } elseif ($action === 'role' && an_is_admin()) {
        $id = (int) ($_POST['id'] ?? 0);
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'manager';
        if ($id !== (int) ($_SESSION['user_id'] ?? 0)) {
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
            an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Изменена роль пользователя', 'id ' . $id . ' → ' . $role);
            an_flash('Роль пользователя обновлена.');
        } else {
            an_flash('Нельзя менять роль самому себе.', 'error');
        }
    } elseif ($action === 'backup' && an_is_admin()) {
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="favorit-backup-' . date('Y-m-d-His') . '.sql"');
        echo "-- Favorit analytics backup " . date('Y-m-d H:i:s') . "\n\n";
        foreach (array_keys($tables) as $table) {
            echo 'DELETE FROM ' . $table . ";\n";
            foreach ($pdo->query('SELECT * FROM ' . $table) as $row) {
                $values = array_map(static function ($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return $pdo->quote((string) $value);
                }, array_values($row));
                echo 'INSERT INTO ' . $table . ' (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', $values) . ");\n";
            }
            echo "\n";
        }
        an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Скачан бэкап базы', '');
        exit;
    }
    an_redirect('admin.php');
}

$counts = [];
foreach (array_keys($tables) as $table) {
    $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}
$users = $pdo->query('SELECT id, login, full_name, role, created_at FROM users ORDER BY id')->fetchAll();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$auditLast = $pdo->query('SELECT actor, action, created_at FROM an_audit ORDER BY id DESC LIMIT 5')->fetchAll();

// Состояние подключения к базе (показывается в блоке «База данных»).
$dbStatus = [
    'engine' => $dbInfo['engine'] === 'mysql' ? 'MySQL' : 'SQLite (резерв)',
    'where' => $dbInfo['engine'] === 'mysql'
        ? $dbInfo['database'] . ' @ ' . $dbInfo['host']
        : (string) ($dbInfo['file'] ?? 'файл'),
    'note' => $dbInfo['engine'] === 'mysql'
        ? ('импорт дампа: ' . ((int) ($dbInfo['imported'] ?? 0) > 0 ? 'выполнен' : 'не требуется'))
        : 'резервный режим: MySQL недоступен',
];

$pageTitle = 'Данные и демо-режим';
$activePage = 'admin';
require_once 'header.php';
?>
<link rel="stylesheet" href="analytics.css">
<div class="an-wrap">
    <div class="an-head">
        <div>
            <div class="an-crumb">Система · данные</div>
            <h1>Данные и демо-режим</h1>
            <p class="an-sub">Источники аналитики, пользователи, резервная копия</p>
        </div>
        <?php if (an_is_admin()): ?>
            <form method="post">
                <?= an_csrf_field() ?>
                <input type="hidden" name="action" value="backup">
                <button class="an-btn ghost" type="submit">Скачать бэкап SQL</button>
            </form>
        <?php endif; ?>
    </div>

    <?php foreach (an_flashes() as $flash): ?>
        <div class="an-flash <?= an_e($flash['type']) ?>"><?= an_e($flash['text']) ?></div>
    <?php endforeach; ?>

    <div class="an-kpi-grid">
        <div class="an-kpi"><span class="an-kpi-title">Таблиц в контуре</span><b class="an-kpi-value"><?= count($tables) ?></b><span class="an-kpi-note">аналитические</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Записей всего</span><b class="an-kpi-value"><?= an_num(array_sum($counts)) ?></b><span class="an-kpi-note">во всех таблицах</span></div>
        <div class="an-kpi"><span class="an-kpi-title">База данных</span><b class="an-kpi-value small"><?= an_e($dbStatus['engine']) ?></b><span class="an-kpi-note"><?= an_e($dbStatus['where']) ?></span></div>
        <div class="an-kpi"><span class="an-kpi-title">Пользователей</span><b class="an-kpi-value"><?= an_num(count($users)) ?></b><span class="an-kpi-note">сотрудники и админ</span></div>
    </div>

    <div class="an-card">
        <div class="an-card-head"><h2>Какие данные откуда берутся</h2></div>
        <table class="an-table">
            <thead><tr><th>Раздел</th><th>Что вводится</th><th>Таблица</th><th>Записей</th></tr></thead>
            <tbody>
            <?php foreach ($tables as $table => [$title, $page, $fields]): ?>
                <tr>
                    <td><a class="an-link" href="<?= an_e($page) ?>"><?= an_e($title) ?></a></td>
                    <td class="muted"><?= an_e($fields) ?></td>
                    <td><code><?= an_e($table) ?></code></td>
                    <td><?= an_num($counts[$table]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="an-split">
        <div class="an-card">
            <div class="an-card-head"><h2>Пользователи</h2></div>
            <table class="an-table">
                <thead><tr><th>Логин</th><th>Имя</th><th>Роль</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= an_e($user['login']) ?></td>
                        <td><?= an_e($user['full_name']) ?></td>
                        <td><?= ($user['role'] ?? 'manager') === 'admin' ? 'администратор' : 'сотрудник' ?></td>
                        <td>
                            <?php if (an_is_admin()): ?>
                                <form method="post" class="inline">
                                    <?= an_csrf_field() ?>
                                    <input type="hidden" name="action" value="role">
                                    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                    <input type="hidden" name="role" value="<?= ($user['role'] ?? 'manager') === 'admin' ? 'manager' : 'admin' ?>">
                                    <button class="an-link" type="submit"><?= ($user['role'] ?? 'manager') === 'admin' ? 'Сделать сотрудником' : 'Сделать админом' ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="an-card">
            <div class="an-card-head"><h2>База данных</h2></div>
            <p class="an-note">
                Движок: <b><?= an_e($dbStatus['engine']) ?></b><br>
                <?= $dbInfo['engine'] === 'mysql' ? 'База' : 'Файл' ?>:
                <b><?= an_e($dbStatus['where']) ?></b><br>
                <?= an_e($dbStatus['note']) ?>
            </p>
            <p class="an-note">
                Полный дамп базы — файл <code>database/favorit_db.sql</code>.
                Импорт в phpMyAdmin или установка страницей <a class="an-link" href="install.php">install.php</a>.
            </p>
        </div>

        <div class="an-card">
            <div class="an-card-head"><h2>Демо-данные</h2></div>
            <p class="an-note">Шесть месяцев (май–октябрь 2025): 12 480 изделий, 8,54 млн ₽, брак 2,4 %, загрузка 87 %.</p>
            <?php if (an_is_admin()): ?>
                <form method="post" onsubmit="return confirm('Пересоздать демо-данные? Текущие записи будут удалены.');">
                    <?= an_csrf_field() ?>
                    <input type="hidden" name="action" value="reset">
                    <button class="an-btn" type="submit">Пересоздать демо-данные</button>
                </form>
            <?php else: ?>
                <p class="an-note">Пересоздание доступно администратору.</p>
            <?php endif; ?>
            <div class="an-forecast">
                <span>Последние действия</span>
                <?php foreach ($auditLast as $row): ?>
                    <b style="font-size:12px;font-weight:400"><?= an_e(date('d.m H:i', strtotime($row['created_at']))) ?> — <?= an_e($row['actor']) ?>: <?= an_e($row['action']) ?></b>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once 'analytics_footer.php'; ?>
