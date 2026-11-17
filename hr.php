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
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $position = trim((string) ($_POST['position'] ?? ''));
        $salary = max(0, (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['salary'] ?? 0)));
        $hired = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['hired_at'] ?? '')) ? $_POST['hired_at'] : date('Y-m-d');
        if ($name === '' || $department === '' || $position === '') {
            an_flash('Заполните ФИО, отдел и должность.', 'error');
        } else {
            $statement = $pdo->prepare('INSERT INTO an_staff (full_name, department, position, salary, hired_at, status) VALUES (?, ?, ?, ?, ?, ?)');
            $statement->execute([$name, $department, $position, $salary, $hired, 'active']);
            an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Принят сотрудник', $name . ', ' . $position);
            an_flash('Сотрудник добавлен.');
        }
    } elseif ($action === 'dismiss') {
        $id = (int) ($_POST['id'] ?? 0);
        $statement = $pdo->prepare("UPDATE an_staff SET status = 'dismissed' WHERE id = ?");
        $statement->execute([$id]);
        an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Уволен сотрудник', 'id ' . $id);
        an_flash('Сотрудник переведён в архив.');
    }
    an_redirect('hr.php');
}

$staff = $pdo->query('SELECT * FROM an_staff ORDER BY status, department, full_name')->fetchAll();
$active = array_values(array_filter($staff, static fn ($row) => $row['status'] === 'active'));
$payroll = array_sum(array_map(static fn ($row) => (float) $row['salary'], $active));
$avgSalary = $active ? $payroll / count($active) : 0;
$byDepartment = [];
foreach ($active as $row) {
    $byDepartment[$row['department']][] = $row;
}
ksort($byDepartment);
$departmentLabels = array_keys($byDepartment);
$departmentFunds = array_map(static fn ($rows) => array_sum(array_map(static fn ($row) => (float) $row['salary'], $rows)), $byDepartment);
$hiredThisYear = count(array_filter($staff, static fn ($row) => substr((string) $row['hired_at'], 0, 4) === date('Y')));

$pageTitle = 'Кадровая аналитика';
$activePage = 'hr';
$chart = an_bar_chart($departmentLabels, $departmentFunds, '#7a5af8', static fn ($v) => an_money($v, true));

require_once 'header.php';
?>
<link rel="stylesheet" href="analytics.css">
<div class="an-wrap">
    <div class="an-head">
        <div><h1>Кадровая аналитика</h1><p class="an-sub">Численность, фонд оплаты труда и структура</p></div>
        <a class="an-btn ghost" href="hr.php">Обновить</a>
    </div>

    <?php foreach (an_flashes() as $flash): ?>
        <div class="an-flash <?= an_e($flash['type']) ?>"><?= an_e($flash['text']) ?></div>
    <?php endforeach; ?>

    <div class="an-kpi-grid">
        <div class="an-kpi"><span class="an-kpi-title">Сотрудников</span><b class="an-kpi-value"><?= an_num(count($active)) ?></b><span class="an-kpi-note">активных · <?= an_num(count($staff) - count($active)) ?> в архиве</span></div>
        <div class="an-kpi"><span class="an-kpi-title">ФОТ в месяц</span><b class="an-kpi-value"><?= an_money($payroll) ?></b><span class="an-kpi-note">оклады без налогов</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Средняя зарплата</span><b class="an-kpi-value"><?= an_money($avgSalary) ?></b><span class="an-kpi-note">на сотрудника</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Принято в <?= date('Y') ?></span><b class="an-kpi-value"><?= an_num($hiredThisYear) ?></b><span class="an-kpi-note">человек</span></div>
    </div>

    <div class="an-card"><div class="an-card-head"><h2>ФОТ по отделам</h2></div><?= $chart ?></div>

    <div class="an-card">
        <div class="an-card-head"><h2>Штат</h2></div>
        <table class="an-table">
            <thead><tr><th>ФИО</th><th>Отдел</th><th>Должность</th><th>Оклад</th><th>Принят</th><th>Статус</th><?php if (an_can_edit()): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($staff as $row): ?>
                <tr class="<?= $row['status'] === 'active' ? '' : 'muted' ?>">
                    <td><?= an_e($row['full_name']) ?></td>
                    <td><?= an_e($row['department']) ?></td>
                    <td><?= an_e($row['position']) ?></td>
                    <td><?= an_money($row['salary']) ?></td>
                    <td><?= an_e(date('d.m.Y', strtotime($row['hired_at']))) ?></td>
                    <td><?= $row['status'] === 'active' ? 'работает' : 'архив' ?></td>
                    <?php if (an_can_edit()): ?>
                        <td>
                            <?php if ($row['status'] === 'active'): ?>
                                <form method="post" onsubmit="return confirm('Перевести сотрудника в архив?');">
                                    <?= an_csrf_field() ?>
                                    <input type="hidden" name="action" value="dismiss">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="an-link danger" type="submit">Уволить</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (an_can_edit()): ?>
        <div class="an-card" id="add">
            <div class="an-card-head"><h2>Принять сотрудника</h2></div>
            <form method="post" class="an-form">
                <?= an_csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <label>ФИО<input type="text" name="full_name" required></label>
                <label>Отдел<input type="text" name="department" placeholder="Производство" required></label>
                <label>Должность<input type="text" name="position" placeholder="Сборщик" required></label>
                <label>Оклад, ₽<input type="number" step="0.01" name="salary" min="0" required></label>
                <label>Дата приёма<input type="date" name="hired_at" value="<?= date('Y-m-d') ?>"></label>
                <button class="an-btn" type="submit">Добавить</button>
            </form>
        </div>
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
