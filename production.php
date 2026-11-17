<?php
require_once 'db.php';
require_once 'analytics_lib.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && an_can_edit()) {
    an_check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $period = preg_match('/^\d{4}-\d{2}$/', (string) ($_POST['period'] ?? '')) ? $_POST['period'] : date('Y-m');
        $workshop = trim((string) ($_POST['workshop'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $qty = max(0, (int) ($_POST['qty'] ?? 0));
        $defects = max(0, min($qty, (int) ($_POST['defects'] ?? 0)));
        $hours = max(0, (float) str_replace(',', '.', (string) ($_POST['hours'] ?? 0)));
        $cost = max(0, (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['cost'] ?? 0)));
        if ($workshop === '' || $category === '' || $qty === 0) {
            an_flash('Заполните цех, категорию и количество изделий.', 'error');
        } else {
            $statement = $pdo->prepare('INSERT INTO an_production (period, workshop, category, qty, defects, hours, cost) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$period, $workshop, $category, $qty, $defects, $hours, $cost]);
            an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Добавлена запись производства', $workshop . ', ' . $qty . ' шт.');
            an_flash('Запись добавлена. Показатели пересчитаны.');
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $period = preg_match('/^\d{4}-\d{2}$/', (string) ($_POST['period'] ?? '')) ? $_POST['period'] : date('Y-m');
        $workshop = trim((string) ($_POST['workshop'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $qty = max(0, (int) ($_POST['qty'] ?? 0));
        $defects = max(0, min($qty, (int) ($_POST['defects'] ?? 0)));
        $hours = max(0, (float) str_replace(',', '.', (string) ($_POST['hours'] ?? 0)));
        $cost = max(0, (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['cost'] ?? 0)));
        if ($id > 0 && $workshop !== '') {
            $pdo->prepare('UPDATE an_production SET period = ?, workshop = ?, category = ?, qty = ?, defects = ?, hours = ?, cost = ? WHERE id = ?')
                ->execute([$period, $workshop, $category, $qty, $defects, $hours, $cost, $id]);
            an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Изменена запись производства', 'id ' . $id);
            an_flash('Запись обновлена.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $statement = $pdo->prepare('DELETE FROM an_production WHERE id = ?');
        $statement->execute([$id]);
        an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Удалена запись производства', 'id ' . $id);
        an_flash('Запись удалена.');
    }
    an_redirect('production.php?period=' . urlencode((string) ($_POST['period'] ?? '')));
}

$period = an_selected_period($pdo);
$monthly = an_monthly($pdo);
$kpi = an_kpi($pdo, $period);
$rows = $pdo->prepare('SELECT * FROM an_production WHERE period = ? ORDER BY workshop');
$rows->execute([$period]);
$rows = $rows->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId > 0 ? one('SELECT * FROM an_production WHERE id = ?', [$editId]) : null;

$byWorkshop = $pdo->prepare('SELECT workshop, SUM(qty) AS qty, SUM(defects) AS defects, SUM(hours) AS hours FROM an_production WHERE period = ? GROUP BY workshop ORDER BY qty DESC');
$byWorkshop->execute([$period]);
$byWorkshop = $byWorkshop->fetchAll();

$pageTitle = 'Производственная аналитика';
$activePage = 'production';
$chartLabels = array_map(static fn ($row) => $row['label'], $monthly);
$chart = an_line_chart($chartLabels, [
    ['name' => 'Выпуск, изделий', 'color' => '#2f6fed', 'values' => array_column($monthly, 'qty'), 'format' => 'an_num'],
    ['name' => 'Брак, изделий', 'color' => '#f04438', 'values' => array_column($monthly, 'defects'), 'format' => 'an_num'],
], 980, 300);
$bars = an_bar_chart(array_column($byWorkshop, 'workshop'), array_column($byWorkshop, 'qty'), '#2f6fed', 'an_num');

require_once 'header.php';
?>
<link rel="stylesheet" href="analytics.css">
<div class="an-wrap">
    <div class="an-head">
        <div><h1>Производственная аналитика</h1><p class="an-sub">Выпуск, брак и загрузка цехов</p></div>
        <form method="get" class="an-period-form">
            <label for="period">Период</label>
            <select name="period" id="period" onchange="this.form.submit()">
                <?php foreach (an_periods($pdo) as $option): ?>
                    <option value="<?= an_e($option) ?>" <?= $option === $period ? 'selected' : '' ?>><?= an_e(an_month_label($option) . ' ' . substr($option, 0, 4)) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php foreach (an_flashes() as $flash): ?>
        <div class="an-flash <?= an_e($flash['type']) ?>"><?= an_e($flash['text']) ?></div>
    <?php endforeach; ?>

    <div class="an-kpi-grid">
        <div class="an-kpi"><span class="an-kpi-title">Выпуск за период</span><b class="an-kpi-value"><?= an_num($kpi['qty']) ?></b><span class="an-kpi-note">изделий</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Брак</span><b class="an-kpi-value danger"><?= number_format((float) $kpi['defect_rate'], 1, ',', ' ') ?> %</b><span class="an-kpi-note"><?= an_num($kpi['defects']) ?> шт. · норма <?= number_format(an_target($pdo, 'defect_max', 3), 1, ',', ' ') ?> %</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Отработано часов</span><b class="an-kpi-value"><?= an_num($kpi['hours'], 0) ?></b><span class="an-kpi-note">по всем цехам</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Загрузка мощностей</span><b class="an-kpi-value"><?= number_format((float) $kpi['load'], 0, ',', ' ') ?> %</b><span class="an-kpi-note">план <?= an_num(an_target($pdo, 'capacity', 5400), 0) ?> ч</span></div>
    </div>

    <div class="an-card"><div class="an-card-head"><h2>Динамика выпуска и брака</h2></div><?= $chart ?></div>

    <div class="an-card">
        <div class="an-card-head"><h2>Выпуск по цехам · <?= an_e(an_month_label($period)) ?></h2></div>
        <?= $bars ?>
        <table class="an-table">
            <thead><tr><th>Цех</th><th>Выпуск</th><th>Брак</th><th>Доля брака</th><th>Часы</th></tr></thead>
            <tbody>
            <?php foreach ($byWorkshop as $row): ?>
                <tr>
                    <td><?= an_e($row['workshop']) ?></td>
                    <td><?= an_num($row['qty']) ?></td>
                    <td><?= an_num($row['defects']) ?></td>
                    <td><?= number_format((int) $row['qty'] > 0 ? (int) $row['defects'] / (int) $row['qty'] * 100 : 0, 1, ',', ' ') ?> %</td>
                    <td><?= an_num($row['hours'], 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="an-card">
        <div class="an-card-head"><h2>Записи производства</h2></div>
        <table class="an-table">
            <thead><tr><th>Месяц</th><th>Цех</th><th>Категория</th><th>Выпуск</th><th>Брак</th><th>Часы</th><th>Себестоимость</th><?php if (an_can_edit()): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= an_e(an_month_label($row['period'])) ?></td>
                    <td><?= an_e($row['workshop']) ?></td>
                    <td><?= an_e($row['category']) ?></td>
                    <td><?= an_num($row['qty']) ?></td>
                    <td><?= an_num($row['defects']) ?></td>
                    <td><?= an_num($row['hours'], 0) ?></td>
                    <td><?= an_money($row['cost']) ?></td>
                    <?php if (an_can_edit()): ?>
                        <td>
                            <?php if (an_can_edit()): ?>
                                <a class="an-link" href="production.php?period=<?= an_e($period) ?>&edit=<?= (int) $row['id'] ?>#add">Изменить</a>
                            <?php endif; ?>
                            <form method="post" onsubmit="return confirm('Удалить запись?');">
                                <?= an_csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="period" value="<?= an_e($period) ?>">
                                <button class="an-link danger" type="submit">Удалить</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (an_can_edit()): ?>
        <div class="an-card" id="add">
            <div class="an-card-head"><h2><?= $editing ? 'Редактировать запись #' . (int) $editing['id'] : 'Добавить запись производства' ?></h2></div>
            <form method="post" class="an-form">
                <?= an_csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editing ? 'update' : 'add' ?>">
                <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
                <label>Месяц<input type="month" name="period" value="<?= an_e($editing['period'] ?? $period) ?>" required></label>
                <label>Цех<input type="text" name="workshop" placeholder="Цех мягкой мебели" required value="<?= an_e($editing['workshop'] ?? '') ?>"></label>
                <label>Категория<input type="text" name="category" placeholder="Диваны" required value="<?= an_e($editing['category'] ?? '') ?>"></label>
                <label>Выпуск, шт.<input type="number" name="qty" min="1" required value="<?= an_e($editing['qty'] ?? '') ?>"></label>
                <label>Брак, шт.<input type="number" name="defects" min="0" value="<?= an_e($editing['defects'] ?? 0) ?>"></label>
                <label>Часы<input type="number" step="0.1" name="hours" min="0" value="<?= an_e($editing['hours'] ?? 0) ?>"></label>
                <label>Себестоимость, ₽<input type="number" step="0.01" name="cost" min="0" value="<?= an_e($editing['cost'] ?? 0) ?>"></label>
                <button class="an-btn" type="submit"><?= $editing ? 'Сохранить изменения' : 'Добавить запись' ?></button>
            </form>
        </div>
    <?php else: ?>
        <p class="an-note">Добавлять и удалять записи может только авторизованный сотрудник. <a href="login.php">Войти</a></p>
    <?php endif; ?>
</div>

<?php if (!an_can_edit()): ?>
    <div class="an-card an-login-hint">
        <b>Формы ввода скрыты</b>
        <p>Чтобы добавлять заказы, записи производства, брак и продажи, войдите как сотрудник:
            <code>manager</code> / <code>manager123</code>.</p>
        <a class="an-btn" href="login.php">Войти и открыть формы</a>
    </div>
<?php endif; ?>

<?php require_once 'analytics_footer.php'; ?>
