<?php
/** Схема и демо-данные аналитического контура «Фаворит». Работает и в MySQL, и в SQLite. */

function an_is_sqlite(?PDO $pdo = null): bool
{
    global $pdo;
    $pdo = $pdo ?: $pdo;
    return $pdo instanceof PDO && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
}

function an_init_schema(PDO $pdo): void
{
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    $tables = [
        "CREATE TABLE IF NOT EXISTS an_production (
            id $id,
            period VARCHAR(7) NOT NULL,
            workshop VARCHAR(80) NOT NULL,
            category VARCHAR(60) NOT NULL,
            qty INT NOT NULL DEFAULT 0,
            defects INT NOT NULL DEFAULT 0,
            hours DECIMAL(10,1) NOT NULL DEFAULT 0,
            cost DECIMAL(14,2) NOT NULL DEFAULT 0
        )$suffix",
        "CREATE TABLE IF NOT EXISTS an_sales (
            id $id,
            period VARCHAR(7) NOT NULL,
            channel VARCHAR(60) NOT NULL,
            orders INT NOT NULL DEFAULT 0,
            revenue DECIMAL(14,2) NOT NULL DEFAULT 0
        )$suffix",
        "CREATE TABLE IF NOT EXISTS an_staff (
            id $id,
            full_name VARCHAR(120) NOT NULL,
            department VARCHAR(80) NOT NULL,
            position VARCHAR(80) NOT NULL,
            salary DECIMAL(12,2) NOT NULL DEFAULT 0,
            hired_at VARCHAR(10) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active'
        )$suffix",
        "CREATE TABLE IF NOT EXISTS an_targets (
            metric VARCHAR(40) NOT NULL PRIMARY KEY,
            target_value DECIMAL(14,2) NOT NULL,
            title VARCHAR(120) NOT NULL
        )$suffix",
        "CREATE TABLE IF NOT EXISTS an_audit (
            id $id,
            actor VARCHAR(120) NOT NULL,
            action VARCHAR(120) NOT NULL,
            details VARCHAR(255) NOT NULL DEFAULT '',
            created_at VARCHAR(19) NOT NULL
        )$suffix",
    ];
    foreach ($tables as $statement) {
        $pdo->exec($statement);
    }

    // Минимальная таблица пользователей для входа в демо-режиме (SQLite).
    $pdo->exec($sqlite
        ? "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            login TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            full_name TEXT NOT NULL DEFAULT '',
            phone TEXT NOT NULL DEFAULT '',
            email TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT ''
        )"
        : "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            login VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL DEFAULT '',
            phone VARCHAR(30) NOT NULL DEFAULT '',
            email VARCHAR(100) NOT NULL DEFAULT '',
            created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Роль пользователя (manager / admin), добавляется к существующей таблице.
    $columns = $sqlite
        ? $pdo->query("PRAGMA table_info(users)")->fetchAll()
        : $pdo->query('SHOW COLUMNS FROM users')->fetchAll();
    $hasRole = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? $column['Field'] ?? '') === 'role') {
            $hasRole = true;
        }
    }
    if (!$hasRole) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'manager'");
    }

    $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($users === 0) {
        $insertUser = $pdo->prepare('INSERT INTO users (login, password, full_name, phone, email, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $insertUser->execute(['manager', password_hash('manager123', PASSWORD_DEFAULT), 'Менеджер Фаворит', '', 'manager@favorit.local', date('Y-m-d H:i:s')]);
        $pdo->exec("UPDATE users SET role = 'admin' WHERE login = 'manager'");
    }

    // Справочники и операционные данные, питающие аналитику.
    $extra = [
        "CREATE TABLE IF NOT EXISTS an_categories (id $id, name VARCHAR(80) NOT NULL)$suffix",
        "CREATE TABLE IF NOT EXISTS an_products (
            id $id, category_id INT NOT NULL, title VARCHAR(120) NOT NULL,
            unit VARCHAR(20) NOT NULL DEFAULT 'шт.', price DECIMAL(12,2) NOT NULL DEFAULT 0,
            active INT NOT NULL DEFAULT 1)$suffix",
        "CREATE TABLE IF NOT EXISTS an_channels (
            id $id, name VARCHAR(80) NOT NULL, type VARCHAR(40) NOT NULL DEFAULT 'прямые',
            plan_revenue DECIMAL(14,2) NOT NULL DEFAULT 0, cost DECIMAL(14,2) NOT NULL DEFAULT 0,
            leads INT NOT NULL DEFAULT 0)$suffix",
        "CREATE TABLE IF NOT EXISTS an_orders (
            id $id, period VARCHAR(7) NOT NULL, client VARCHAR(120) NOT NULL,
            product VARCHAR(120) NOT NULL, channel VARCHAR(80) NOT NULL,
            amount DECIMAL(14,2) NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'new',
            due_date VARCHAR(10) NOT NULL)$suffix",
        "CREATE TABLE IF NOT EXISTS an_defects (
            id $id, period VARCHAR(7) NOT NULL, workshop VARCHAR(80) NOT NULL,
            reason VARCHAR(80) NOT NULL, qty INT NOT NULL DEFAULT 0,
            cost DECIMAL(14,2) NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'open')$suffix",
    ];
    foreach ($extra as $statement) {
        $pdo->exec($statement);
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM an_production')->fetchColumn();
    if ($count === 0) {
        an_seed($pdo);
    }

    $targetCount = (int) $pdo->query('SELECT COUNT(*) FROM an_targets')->fetchColumn();
    if ($targetCount === 0) {
        $targets = [
            ['capacity', 5400, 'Плановая загрузка мощностей, ч/мес'],
            ['defect_max', 3.0, 'Допустимый брак, %'],
            ['revenue_plan', 8500000, 'План выручки, ₽/мес'],
        ];
        $insert = $pdo->prepare('INSERT INTO an_targets (metric, target_value, title) VALUES (?, ?, ?)');
        foreach ($targets as $row) {
            $insert->execute($row);
        }
    }
}

function an_seed(PDO $pdo): void
{
    $months = ['2025-05', '2025-06', '2025-07', '2025-08', '2025-09', '2025-10'];
    // [цех, категория, доля объёма, часы, себестоимость за изделие]
    $workshops = [
        ['Цех мягкой мебели', 'Диваны', 0.52, 2350, 640],
        ['Цех корпусной мебели', 'Шкафы и столы', 0.31, 1450, 600],
        ['Раскройный участок', 'Комплектующие', 0.17, 900, 580],
    ];
    $totals = [10500, 11100, 12200, 12500, 12600, 12480];
    $defectRates = [0.026, 0.025, 0.024, 0.023, 0.024, 0.024];
    $revenues = [7900000, 8050000, 8210000, 8330000, 8460000, 8540000];
    $orders = [318, 322, 341, 352, 361, 368];

    $insertProduction = $pdo->prepare('INSERT INTO an_production (period, workshop, category, qty, defects, hours, cost) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $insertSales = $pdo->prepare('INSERT INTO an_sales (period, channel, orders, revenue) VALUES (?, ?, ?, ?)');

    foreach ($months as $index => $month) {
        $total = $totals[$index];
        $assigned = 0;
        foreach ($workshops as $position => [$workshop, $category, $share, $hours, $unitCost]) {
            $isLast = $position === count($workshops) - 1;
            $qty = $isLast ? $total - $assigned : (int) round($total * $share);
            $assigned += $qty;
            $defects = (int) round($qty * $defectRates[$index]);
            $insertProduction->execute([$month, $workshop, $category, $qty, $defects, $hours, $qty * $unitCost]);
        }
        $channelShares = ['Сайт' => 0.46, 'Маркетплейсы' => 0.27, 'Оптовые партнёры' => 0.19, 'Розница' => 0.08];
        foreach ($channelShares as $channel => $share) {
            $insertSales->execute([$month, $channel, (int) round($orders[$index] * $share), round($revenues[$index] * $share, 2)]);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM an_categories')->fetchColumn() === 0) {
        $categories = ['Диваны', 'Шкафы и столы', 'Комплектующие'];
        $productData = [
            [1, 'Диван «Модерн»', 'шт.', 68000], [1, 'Диван «Осло»', 'шт.', 74000],
            [1, 'Кресло «Лира»', 'шт.', 32000], [2, 'Шкаф «Классик»', 'шт.', 41000],
            [2, 'Стол письменный', 'шт.', 28000], [3, 'Комплект каркаса', 'компл.', 7800],
            [3, 'Обивка', 'м²', 1450],
        ];
        foreach ($categories as $name) {
            $pdo->prepare('INSERT INTO an_categories (name) VALUES (?)')->execute([$name]);
        }
        $insertProduct = $pdo->prepare('INSERT INTO an_products (category_id, title, unit, price, active) VALUES (?, ?, ?, ?, 1)');
        foreach ($productData as $row) {
            $insertProduct->execute($row);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM an_channels')->fetchColumn() === 0) {
        $channels = [
            ['Сайт', 'онлайн', 4000000, 120000, 820],
            ['Маркетплейсы', 'маркетплейс', 2300000, 210000, 1450],
            ['Оптовые партнёры', 'B2B', 1700000, 90000, 210],
            ['Розница', 'офлайн', 700000, 45000, 120],
        ];
        $insertChannel = $pdo->prepare('INSERT INTO an_channels (name, type, plan_revenue, cost, leads) VALUES (?, ?, ?, ?, ?)');
        foreach ($channels as $row) {
            $insertChannel->execute($row);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM an_orders')->fetchColumn() === 0) {
        $statuses = ['new', 'in_progress', 'done', 'done', 'done', 'cancelled'];
        $clients = ['ООО «Дом-Стиль»', 'ИП Сергеев', 'ООО «Мебель Плюс»', 'ИП Ковалёва', 'ООО «Офис-Диск»', 'ООО «Уют»'];
        $orderProducts = ['Диван «Модерн»', 'Диван «Осло»', 'Шкаф «Классик»', 'Стол письменный', 'Кресло «Лира»'];
        $orderChannels = ['Сайт', 'Маркетплейсы', 'Оптовые партнёры', 'Розница'];
        $insertOrder = $pdo->prepare('INSERT INTO an_orders (period, client, product, channel, amount, status, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($months as $index => $month) {
            for ($i = 0; $i < 8; $i++) {
                $status = $statuses[($index + $i) % count($statuses)];
                $due = date('Y-m-d', strtotime($month . '-05 +' . (5 + ($i * 3)) . ' days'));
                $insertOrder->execute([
                    $month,
                    $clients[($index + $i) % count($clients)],
                    $orderProducts[($i + $index) % count($orderProducts)],
                    $orderChannels[($i + $index) % count($orderChannels)],
                    35000 + (($index * 7 + $i * 13) % 20) * 2500,
                    $status,
                    $due,
                ]);
            }
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM an_defects')->fetchColumn() === 0) {
        $reasons = ['Оборудование', 'Материал', 'Человеческий фактор', 'Логистика'];
        $workshopsForDefects = ['Цех мягкой мебели', 'Цех корпусной мебели', 'Раскройный участок'];
        $insertDefect = $pdo->prepare('INSERT INTO an_defects (period, workshop, reason, qty, cost, status) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($months as $index => $month) {
            foreach ($reasons as $rIndex => $reason) {
                $qty = 18 + (($index * 5 + $rIndex * 9) % 30);
                $insertDefect->execute([
                    $month,
                    $workshopsForDefects[($rIndex + $index) % 3],
                    $reason,
                    $qty,
                    $qty * (4200 + $rIndex * 800),
                    $rIndex === 0 && $index % 2 === 0 ? 'open' : 'closed',
                ]);
            }
        }
    }

    $staff = [
        ['Иванов И. И.', 'Производство', 'Начальник цеха', 92000, '2019-03-12'],
        ['Петрова А. С.', 'Производство', 'Мастер участка', 71000, '2021-07-01'],
        ['Сидоров П. А.', 'Производство', 'Оператор ЧПУ', 64000, '2022-02-14'],
        ['Кузнецов Д. В.', 'Производство', 'Сборщик мебели', 58000, '2023-05-22'],
        ['Смирнова Е. А.', 'Финансы', 'Главный бухгалтер', 98000, '2018-11-05'],
        ['Волкова М. И.', 'Финансы', 'Экономист', 72000, '2022-09-19'],
        ['Морозов А. Ю.', 'Продажи', 'Руководитель отдела', 85000, '2020-01-20'],
        ['Фёдорова Н. В.', 'Продажи', 'Менеджер по продажам', 56000, '2023-04-03'],
        ['Соколов Р. Н.', 'Маркетинг', 'Маркетолог', 69000, '2022-06-15'],
        ['Лебедева О. К.', 'Кадры', 'HR-специалист', 61000, '2021-10-11'],
    ];
    $insertStaff = $pdo->prepare('INSERT INTO an_staff (full_name, department, position, salary, hired_at, status) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($staff as $person) {
        $insertStaff->execute(array_merge($person, ['active']));
    }
}

function an_audit(PDO $pdo, string $actor, string $action, string $details = ''): void
{
    $statement = $pdo->prepare('INSERT INTO an_audit (actor, action, details, created_at) VALUES (?, ?, ?, ?)');
    $statement->execute([$actor, $action, $details, date('Y-m-d H:i:s')]);
}


/** Полностью пересоздаёт демонстрационные данные (кнопка на странице «Данные»). */
function an_reset_demo(PDO $pdo): void
{
    foreach (['an_production', 'an_sales', 'an_staff', 'an_orders', 'an_defects',
              'an_channels', 'an_products', 'an_categories', 'an_targets', 'an_audit'] as $table) {
        $pdo->exec('DELETE FROM ' . $table);
    }
    an_seed($pdo);
    $insert = $pdo->prepare('INSERT INTO an_targets (metric, target_value, title) VALUES (?, ?, ?)');
    foreach ([
        ['capacity', 5400, 'Плановая загрузка мощностей, ч/мес'],
        ['defect_max', 3.0, 'Допустимый брак, %'],
        ['revenue_plan', 8500000, 'План выручки, ₽/мес'],
    ] as $row) {
        $insert->execute($row);
    }
}
