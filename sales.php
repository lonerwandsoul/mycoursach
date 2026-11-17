<?php
require_once 'db.php';
require_once 'analytics_lib.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && an_can_edit()) {
    an_check_csrf();
    if (($_POST['action'] ?? '') === 'add') {
        $period = preg_match('/^\d{4}-\d{2}$/', (string) ($_POST['period'] ?? '')) ? $_POST['period'] : date('Y-m');
        $channel = trim((string) ($_POST['channel'] ?? ''));
        $orders = max(0, (int) ($_POST['orders'] ?? 0));
        $revenue = max(0, (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['revenue'] ?? 0)));
        if ($channel === '' || $orders === 0) {
            an_flash('Укажите канал и количество заказов.', 'error');
        } else {
            $statement = $pdo->prepare('INSERT INTO an_sales (period, channel, orders, revenue) VALUES (?, ?, ?, ?)');
            $statement->execute([$period, $channel, $orders, $revenue]);
            an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Добавлена продажа', $channel . ', ' . an_money($revenue));
            an_flash('Продажа добавлена.');
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $statement = $pdo->prepare('DELETE FROM an_sales WHERE id = ?');
        $statement->execute([(int) ($_POST['id'] ?? 0)]);
        an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Удалена запись продаж', 'id ' . (int) ($_POST['id'] ?? 0));
        an_flash('Запись удалена.');
    }
    an_redirect('sales.php?period=' . urlencode((string) ($_POST['period'] ?? '')));
}

$period = an_selected_period($pdo);
$monthly = an_monthly($pdo);
$kpi = an_kpi($pdo, $period);
$rows = $pdo->prepare('SELECT * FROM an_sales WHERE period = ? ORDER BY revenue DESC');
$rows->execute([$period]);
$rows = $rows->fetchAll();

$byChannel = $pdo->prepare('SELECT channel, SUM(revenue) AS revenue, SUM(orders) AS orders FROM an_sales WHERE period = ? GROUP BY channel ORDER BY revenue DESC');
$byChannel->execute([$period]);
$byChannel = $byChannel->fetchAll();

$labels = array_map(static fn ($row) => $row['label'], $monthly);
$ordersChart = an_line_chart($labels, [
    ['name' => 'Заказы, шт.', 'color' => '#2f6fed', 'values' => array_column($monthly, 'orders'), 'format' => 'an_num'],
    ['name' => 'Средний чек, тыс. ₽', 'color' => '#12b76a', 'values' => array_map(static fn ($row) => $row['orders'] > 0 ? $row['revenue'] / $row['orders'] / 1000 : 0, $monthly), 'format' => static fn ($v) => number_format((float) $v, 1, ',', ' ')],
], 980, 300, max(1.0, (float) max(array_column($monthly, 'orders')) * 1.15), 'an_num');
$channelBars = an_bar_chart(array_column($byChannel, 'channel'), array_column($byChannel, 'revenue'), '#2f6fed', static fn ($v) => an_money($v, true));

$pageTitle = 'Продажи и маркетинг';
$activePage = 'sales';
require_once 'header.php';
?>
<link rel="stylesheet" href="analytics.css">
<div class="an-wrap">
    <div class="an-head">
        <div><h1>Продажи и маркетинг</h1><p class="an-sub">Заказы, средний чек и каналы продаж</p></div>
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
        <div class="an-kpi"><span class="an-kpi-title">Заказы</span><b class="an-kpi-value"><?= an_num($kpi['orders']) ?></b><span class="an-kpi-note">за период</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Средний чек</span><b class="an-kpi-value"><?= an_money($kpi['avg_check']) ?></b><span class="an-kpi-note">на один заказ</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Выручка</span><b class="an-kpi-value green"><?= an_money($kpi['revenue'], true) ?></b><span class="an-kpi-note">по всем каналам</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Лидирующий канал</span><b class="an-kpi-value small"><?= an_e($byChannel[0]['channel'] ?? '—') ?></b><span class="an-kpi-note"><?= an_money($byChannel[0]['revenue'] ?? 0, true) ?></span></div>
    </div>

    <div class="an-card"><div class="an-card-head"><h2>Заказы и средний чек</h2></div><?= $ordersChart ?></div>
    <div class="an-card"><div class="an-card-head"><h2>Выручка по каналам · <?= an_e(an_month_label($period)) ?></h2></div><?= $channelBars ?></div>

    <div class="an-card">
        <div class="an-card-head"><h2>Записи продаж</h2></div>
        <table class="an-table">
            <thead><tr><th>Месяц</th><th>Канал</th><th>Заказы</th><th>Выручка</th><th>Средний чек</th><?php if (an_can_edit()): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= an_e(an_month_label($row['period'])) ?></td>
                    <td><?= an_e($row['channel']) ?></td>
                    <td><?= an_num($row['orders']) ?></td>
                    <td><?= an_money($row['revenue']) ?></td>
                    <td><?= an_money((int) $row['orders'] > 0 ? (float) $row['revenue'] / (int) $row['orders'] : 0) ?></td>
                    <?php if (an_can_edit()): ?>
                        <td>
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
            <div class="an-card-head"><h2>Добавить продажу</h2></div>
            <form method="post" class="an-form">
                <?= an_csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <label>Месяц<input type="month" name="period" value="<?= an_e($period) ?>" required></label>
                <label>Канал<input type="text" name="channel" placeholder="Сайт" required></label>
                <label>Заказы<input type="number" name="orders" min="1" required></label>
                <label>Выручка, ₽<input type="number" step="0.01" name="revenue" min="0" required></label>
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
