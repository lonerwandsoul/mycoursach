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
        $name = trim((string) ($_POST['name'] ?? ''));
        $type = trim((string) ($_POST['type'] ?? 'прямые'));
        $plan = max(0, (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['plan_revenue'] ?? 0)));
        $cost = max(0, (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['cost'] ?? 0)));
        $leads = max(0, (int) ($_POST['leads'] ?? 0));
        if ($name === '') {
            an_flash('Укажите название канала.', 'error');
        } else {
            $pdo->prepare('INSERT INTO an_channels (name, type, plan_revenue, cost, leads) VALUES (?, ?, ?, ?, ?)')
                ->execute([$name, $type, $plan, $cost, $leads]);
            an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Добавлен канал продаж', $name);
            an_flash('Канал добавлен.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM an_channels WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        an_flash('Канал удалён.');
    }
    an_redirect('partners.php');
}

$period = an_selected_period($pdo);
$channels = $pdo->query('SELECT * FROM an_channels ORDER BY id')->fetchAll();

$actual = [];
foreach ($pdo->query('SELECT channel, SUM(revenue) AS revenue, SUM(orders) AS orders FROM an_sales GROUP BY channel') as $row) {
    $actual[$row['channel']] = $row;
}
$orderCounts = [];
foreach ($pdo->query('SELECT channel, COUNT(*) AS cnt FROM an_orders GROUP BY channel') as $row) {
    $orderCounts[$row['channel']] = (int) $row['cnt'];
}

$rows = [];
$planTotal = 0.0;
$actualTotal = 0.0;
foreach ($channels as $channel) {
    $revenue = (float) ($actual[$channel['name']]['revenue'] ?? 0);
    $plan = (float) $channel['plan_revenue'];
    $leads = (int) $channel['leads'];
    $rows[] = [
        'id' => (int) $channel['id'],
        'name' => $channel['name'],
        'type' => $channel['type'],
        'plan' => $plan,
        'actual' => $revenue,
        'completion' => $plan > 0 ? $revenue / $plan * 100 : 0,
        'cost' => (float) $channel['cost'],
        'leads' => $leads,
        'cac' => $leads > 0 ? (float) $channel['cost'] / $leads : 0,
        'conversion' => $leads > 0 ? ($orderCounts[$channel['name']] ?? 0) / $leads * 100 : 0,
    ];
    $planTotal += $plan;
    $actualTotal += $revenue;
}
$completionTotal = $planTotal > 0 ? $actualTotal / $planTotal * 100 : 0;
$chart = an_bar_chart(array_column($rows, 'name'), array_column($rows, 'actual'), '#2f6fed', static fn ($v) => an_money($v, true));

$pageTitle = 'Каналы продаж';
$activePage = 'channels';
require_once 'header.php';
?>
<link rel="stylesheet" href="analytics.css">
<div class="an-wrap">
    <div class="an-head">
        <div><h1>Каналы продаж</h1><p class="an-sub">План и факт выручки, стоимость привлечения и конверсия</p></div>
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
        <div class="an-kpi"><span class="an-kpi-title">Каналов</span><b class="an-kpi-value"><?= an_num(count($rows)) ?></b><span class="an-kpi-note">активных направлений</span></div>
        <div class="an-kpi"><span class="an-kpi-title">План выручки</span><b class="an-kpi-value"><?= an_money($planTotal, true) ?></b><span class="an-kpi-note">за месяц</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Факт выручки</span><b class="an-kpi-value green"><?= an_money($actualTotal, true) ?></b><span class="an-kpi-note">по всем каналам</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Выполнение плана</span><b class="an-kpi-value"><?= number_format($completionTotal, 0, ',', ' ') ?> %</b><span class="an-kpi-note">факт / план</span></div>
    </div>

    <div class="an-card"><div class="an-card-head"><h2>Фактическая выручка по каналам</h2></div><?= $chart ?></div>

    <div class="an-card">
        <div class="an-card-head"><h2>Эффективность каналов</h2></div>
        <table class="an-table">
            <thead><tr><th>Канал</th><th>Тип</th><th>План</th><th>Факт</th><th>Выполнение</th><th>Лиды</th><th>Заказы</th><th>Конверсия</th><th>CAC</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= an_e($row['name']) ?></td>
                    <td><?= an_e($row['type']) ?></td>
                    <td><?= an_money($row['plan'], true) ?></td>
                    <td><?= an_money($row['actual'], true) ?></td>
                    <td class="<?= $row['completion'] >= 100 ? 'good' : ($row['completion'] < 80 ? 'bad' : '') ?>"><?= number_format($row['completion'], 0, ',', ' ') ?> %</td>
                    <td><?= an_num($row['leads']) ?></td>
                    <td><?= an_num($orderCounts[$row['name']] ?? 0) ?></td>
                    <td><?= number_format($row['conversion'], 1, ',', ' ') ?> %</td>
                    <td><?= an_money($row['cac']) ?></td>
                    <td>
                        <?php if (an_can_edit()): ?>
                            <form method="post" onsubmit="return confirm('Удалить канал?');">
                                <?= an_csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
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
            <div class="an-card-head"><h2>Добавить канал</h2></div>
            <form method="post" class="an-form">
                <?= an_csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <label>Название<input type="text" name="name" required></label>
                <label>Тип<input type="text" name="type" value="прямые"></label>
                <label>План, ₽<input type="number" step="1000" name="plan_revenue" min="0" required></label>
                <label>Затраты, ₽<input type="number" step="100" name="cost" min="0" value="0"></label>
                <label>Лиды<input type="number" name="leads" min="0" value="0"></label>
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
