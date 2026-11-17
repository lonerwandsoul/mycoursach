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
        $client = trim((string) ($_POST['client'] ?? ''));
        $product = trim((string) ($_POST['product'] ?? ''));
        $channel = trim((string) ($_POST['channel'] ?? ''));
        $amount = max(0, (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['amount'] ?? 0)));
        $status = in_array($_POST['status'] ?? '', ['new', 'in_progress', 'done', 'cancelled'], true) ? $_POST['status'] : 'new';
        $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['due_date'] ?? '')) ? $_POST['due_date'] : date('Y-m-d');
        if ($client === '' || $product === '' || $channel === '') {
            an_flash('Заполните клиента, продукт и канал.', 'error');
        } else {
            $statement = $pdo->prepare('INSERT INTO an_orders (period, client, product, channel, amount, status, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$period, $client, $product, $channel, $amount, $status, $due]);
            an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Создан заказ', $client . ' на ' . an_money($amount));
            an_flash('Заказ добавлен.');
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $period = preg_match('/^\d{4}-\d{2}$/', (string) ($_POST['period'] ?? '')) ? $_POST['period'] : date('Y-m');
        $client = trim((string) ($_POST['client'] ?? ''));
        $product = trim((string) ($_POST['product'] ?? ''));
        $channel = trim((string) ($_POST['channel'] ?? ''));
        $amount = max(0, (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['amount'] ?? 0)));
        $status = in_array($_POST['status'] ?? '', ['new', 'in_progress', 'done', 'cancelled'], true) ? $_POST['status'] : 'new';
        $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['due_date'] ?? '')) ? $_POST['due_date'] : date('Y-m-d');
        if ($id > 0 && $client !== '') {
            $pdo->prepare('UPDATE an_orders SET period = ?, client = ?, product = ?, channel = ?, amount = ?, status = ?, due_date = ? WHERE id = ?')
                ->execute([$period, $client, $product, $channel, $amount, $status, $due, $id]);
            an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Изменён заказ', 'id ' . $id . ', ' . $client);
            an_flash('Заказ обновлён.');
        }
    } elseif ($action === 'status') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['new', 'in_progress', 'done', 'cancelled'], true) ? $_POST['status'] : 'new';
        $pdo->prepare('UPDATE an_orders SET status = ? WHERE id = ?')->execute([$status, $id]);
        an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Изменён статус заказа', 'id ' . $id . ' → ' . $status);
        an_flash('Статус обновлён.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM an_orders WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Удалён заказ', 'id ' . (int) ($_POST['id'] ?? 0));
        an_flash('Заказ удалён.');
    }
    an_redirect('orders.php?period=' . urlencode((string) ($_POST['period'] ?? '')));
}

$period = an_selected_period($pdo);
$orders = $pdo->prepare('SELECT * FROM an_orders WHERE period = ? ORDER BY id DESC');
$orders->execute([$period]);
$orders = $orders->fetchAll();

$totalAmount = array_sum(array_map(static fn ($row) => (float) $row['amount'], $orders));
$done = count(array_filter($orders, static fn ($row) => $row['status'] === 'done'));
$cancelled = count(array_filter($orders, static fn ($row) => $row['status'] === 'cancelled'));
$overdue = count(array_filter($orders, static fn ($row) => $row['status'] !== 'done' && $row['status'] !== 'cancelled' && $row['due_date'] < date('Y-m-d')));
$avg = $orders ? $totalAmount / count($orders) : 0;
$statusLabels = ['new' => 'Новый', 'in_progress' => 'В работе', 'done' => 'Выполнен', 'cancelled' => 'Отменён'];
$statusCounts = [];
foreach ($statusLabels as $key => $label) {
    $statusCounts[$key] = count(array_filter($orders, static fn ($row) => $row['status'] === $key));
}

$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId > 0 ? one('SELECT * FROM an_orders WHERE id = ?', [$editId]) : null;

$products = $pdo->query('SELECT title FROM an_products ORDER BY title')->fetchAll(PDO::FETCH_COLUMN);
$channels = $pdo->query('SELECT name FROM an_channels ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$bars = an_bar_chart(array_values($statusLabels), array_values($statusCounts), '#2f6fed', 'an_num');

$pageTitle = 'Заказы';
$activePage = 'orders';
require_once 'header.php';
?>
<link rel="stylesheet" href="analytics.css">
<div class="an-wrap">
    <div class="an-head">
        <div><h1>Заказы</h1><p class="an-sub">Портфель заказов: сумма, статусы, сроки</p></div>
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
        <div class="an-kpi"><span class="an-kpi-title">Заказов</span><b class="an-kpi-value"><?= an_num(count($orders)) ?></b><span class="an-kpi-note">выполнено <?= an_num($done) ?> · отменено <?= an_num($cancelled) ?></span></div>
        <div class="an-kpi"><span class="an-kpi-title">Сумма заказов</span><b class="an-kpi-value"><?= an_money($totalAmount, true) ?></b><span class="an-kpi-note">за период</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Средний чек</span><b class="an-kpi-value"><?= an_money($avg) ?></b><span class="an-kpi-note">на заказ</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Просрочено</span><b class="an-kpi-value <?= $overdue > 0 ? 'danger' : '' ?>"><?= an_num($overdue) ?></b><span class="an-kpi-note">срок истёк, не закрыты</span></div>
    </div>

    <div class="an-card"><div class="an-card-head"><h2>Заказы по статусам · <?= an_e(an_month_label($period)) ?></h2></div><?= $bars ?></div>

    <div class="an-card">
        <div class="an-card-head"><h2>Список заказов</h2></div>
        <table class="an-table">
            <thead><tr><th>Клиент</th><th>Продукт</th><th>Канал</th><th>Сумма</th><th>Срок</th><th>Статус</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= an_e($order['client']) ?></td>
                    <td><?= an_e($order['product']) ?></td>
                    <td><?= an_e($order['channel']) ?></td>
                    <td><?= an_money($order['amount']) ?></td>
                    <td class="<?= $order['status'] !== 'done' && $order['due_date'] < date('Y-m-d') ? 'overdue' : '' ?>"><?= an_e(date('d.m.Y', strtotime($order['due_date']))) ?></td>
                    <td>
                        <?php if (an_can_edit()): ?>
                            <form method="post" class="inline-select">
                                <?= an_csrf_field() ?>
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                                <input type="hidden" name="period" value="<?= an_e($period) ?>">
                                <select name="status" onchange="this.form.submit()">
                                    <?php foreach ($statusLabels as $key => $label): ?>
                                        <option value="<?= an_e($key) ?>" <?= $order['status'] === $key ? 'selected' : '' ?>><?= an_e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php else: ?>
                            <?= an_e($statusLabels[$order['status']] ?? $order['status']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (an_can_edit()): ?>
                            <a class="an-link" href="orders.php?period=<?= an_e($period) ?>&edit=<?= (int) $order['id'] ?>#add">Изменить</a>
                            <form method="post" class="inline" onsubmit="return confirm('Удалить заказ?');">
                                <?= an_csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                                <input type="hidden" name="period" value="<?= an_e($period) ?>">
                                <button class="an-link danger" type="submit">Удалить</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (an_can_edit()): ?>
        <div class="an-card" id="add">
            <div class="an-card-head"><h2><?= $editing ? 'Редактировать заказ #' . (int) $editing['id'] : 'Новый заказ' ?></h2></div>
            <form method="post" class="an-form">
                <?= an_csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editing ? 'update' : 'add' ?>">
                <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
                <label>Месяц<input type="month" name="period" value="<?= an_e($editing['period'] ?? $period) ?>" required></label>
                <label>Клиент<input type="text" name="client" required value="<?= an_e($editing['client'] ?? '') ?>"></label>
                <label>Продукт
                    <select name="product" required>
                        <?php foreach ($products as $product): ?><option <?= ($editing['product'] ?? '') === $product ? 'selected' : '' ?>><?= an_e($product) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Канал
                    <select name="channel" required>
                        <?php foreach ($channels as $channel): ?><option <?= ($editing['channel'] ?? '') === $channel ? 'selected' : '' ?>><?= an_e($channel) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Сумма, ₽<input type="number" step="0.01" name="amount" min="0" required value="<?= an_e($editing['amount'] ?? '') ?>"></label>
                <label>Срок<input type="date" name="due_date" value="<?= an_e($editing['due_date'] ?? date('Y-m-d')) ?>"></label>
                <label>Статус
                    <select name="status">
                        <?php foreach ($statusLabels as $key => $label): ?><option value="<?= an_e($key) ?>" <?= ($editing['status'] ?? '') === $key ? 'selected' : '' ?>><?= an_e($label) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <button class="an-btn" type="submit"><?= $editing ? 'Сохранить изменения' : 'Добавить заказ' ?></button>
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
