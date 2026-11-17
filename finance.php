<?php
require_once 'db.php';
require_once 'analytics_lib.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$period = an_selected_period($pdo);
$monthly = an_monthly($pdo);
$kpi = an_kpi($pdo, $period);
$labels = array_map(static fn ($row) => $row['label'], $monthly);

$chart = an_line_chart($labels, [
    ['name' => 'Выручка, млн ₽', 'color' => '#12b76a', 'values' => array_map(static fn ($row) => $row['revenue'] / 1000000, $monthly), 'format' => static fn ($v) => number_format((float) $v, 1, ',', ' ')],
    ['name' => 'Себестоимость, млн ₽', 'color' => '#f79009', 'values' => array_map(static fn ($row) => $row['cost'] / 1000000, $monthly), 'format' => static fn ($v) => number_format((float) $v, 1, ',', ' ')],
    ['name' => 'Прибыль, млн ₽', 'color' => '#2f6fed', 'values' => array_map(static fn ($row) => $row['profit'] / 1000000, $monthly), 'format' => static fn ($v) => number_format((float) $v, 1, ',', ' ')],
], 980, 320);

$byChannel = $pdo->prepare('SELECT channel, SUM(revenue) AS revenue, SUM(orders) AS orders FROM an_sales WHERE period = ? GROUP BY channel ORDER BY revenue DESC');
$byChannel->execute([$period]);
$byChannel = $byChannel->fetchAll();

$pageTitle = 'Финансовая аналитика';
$activePage = 'finance';
require_once 'header.php';
?>
<link rel="stylesheet" href="analytics.css">
<div class="an-wrap">
    <div class="an-head">
        <div><h1>Финансовая аналитика</h1><p class="an-sub">Выручка, себестоимость и прибыль</p></div>
        <form method="get" class="an-period-form">
            <label for="period">Период</label>
            <select name="period" id="period" onchange="this.form.submit()">
                <?php foreach (an_periods($pdo) as $option): ?>
                    <option value="<?= an_e($option) ?>" <?= $option === $period ? 'selected' : '' ?>><?= an_e(an_month_label($option) . ' ' . substr($option, 0, 4)) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="an-kpi-grid">
        <div class="an-kpi"><span class="an-kpi-title">Выручка</span><b class="an-kpi-value"><?= an_money($kpi['revenue']) ?></b><span class="an-kpi-note"><?= an_num($kpi['orders']) ?> заказов</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Себестоимость</span><b class="an-kpi-value"><?= an_money($kpi['cost']) ?></b><span class="an-kpi-note">материалы и производство</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Прибыль</span><b class="an-kpi-value green"><?= an_money($kpi['profit']) ?></b><span class="an-kpi-note">до налогов</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Рентабельность</span><b class="an-kpi-value"><?= number_format((float) $kpi['margin'], 1, ',', ' ') ?> %</b><span class="an-kpi-note">прибыль / выручка</span></div>
    </div>

    <div class="an-card"><div class="an-card-head"><h2>Выручка, себестоимость и прибыль</h2></div><?= $chart ?></div>

    <div class="an-card">
        <div class="an-card-head"><h2>Выручка по каналам · <?= an_e(an_month_label($period)) ?></h2></div>
        <table class="an-table">
            <thead><tr><th>Канал</th><th>Заказы</th><th>Выручка</th><th>Средний чек</th><th>Доля</th></tr></thead>
            <tbody>
            <?php foreach ($byChannel as $row): ?>
                <tr>
                    <td><?= an_e($row['channel']) ?></td>
                    <td><?= an_num($row['orders']) ?></td>
                    <td><?= an_money($row['revenue']) ?></td>
                    <td><?= an_money((int) $row['orders'] > 0 ? (float) $row['revenue'] / (int) $row['orders'] : 0) ?></td>
                    <td><?= $kpi['revenue'] > 0 ? number_format((float) $row['revenue'] / $kpi['revenue'] * 100, 1, ',', ' ') : '0' ?> %</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="an-card">
        <div class="an-card-head"><h2>Показатели по месяцам</h2></div>
        <table class="an-table">
            <thead><tr><th>Месяц</th><th>Выручка</th><th>Себестоимость</th><th>Прибыль</th><th>Рентабельность</th></tr></thead>
            <tbody>
            <?php foreach ($monthly as $row): ?>
                <tr>
                    <td><?= an_e($row['label']) ?></td>
                    <td><?= an_money($row['revenue']) ?></td>
                    <td><?= an_money($row['cost']) ?></td>
                    <td><?= an_money($row['profit']) ?></td>
                    <td><?= $row['revenue'] > 0 ? number_format($row['profit'] / $row['revenue'] * 100, 1, ',', ' ') : '0' ?> %</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'analytics_footer.php'; ?>
