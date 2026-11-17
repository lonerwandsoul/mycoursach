<?php
require_once 'db.php';
require_once 'analytics_lib.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Главная — аналитика';
$activePage = 'dashboard';
$period = an_selected_period($pdo);
$monthly = an_monthly($pdo);
$kpi = an_kpi($pdo, $period);
$previous = an_previous($monthly, $period);
$deltaQty = an_delta($kpi['qty'], $previous['qty'] ?? null);
$deltaRevenue = an_delta($kpi['revenue'], $previous['revenue'] ?? null);
$deltaDefect = an_delta($kpi['defect_rate'], $previous['defect_rate'] ?? null);
$deltaLoad = an_delta($kpi['load'], $previous['load'] ?? null);

$labels = array_map(static fn ($row) => $row['label'], $monthly);
$chart = an_line_chart($labels, [
    ['name' => 'Производительность, изделий', 'color' => '#2f6fed', 'values' => array_column($monthly, 'qty'), 'format' => 'an_num'],
    ['name' => 'Брак, %', 'color' => '#f04438', 'values' => array_column($monthly, 'defect_rate'), 'format' => static fn ($v) => number_format((float) $v, 1, ',', ' '), 'axis' => 'right'],
], 980, 320, max(1.0, (float) max(array_column($monthly, 'qty'))), 'an_num');

$forecast = an_forecast($monthly, 'revenue');
$insights = an_insights($pdo, $monthly, $kpi + ['period' => $period], $previous);
$topProducts = an_top_products($pdo, $period, 5);
$channelStructure = an_channel_structure($pdo, $period);
$channelMax = $channelStructure ? max(array_map(static fn ($row) => (float) $row['revenue'], $channelStructure)) : 1;

$sections = [
    ['title' => 'Заказы', 'href' => 'orders.php', 'text' => 'Портфель, статусы, сроки', 'value' => an_num((int) scalar_orders($pdo, $period)), 'icon' => '▤'],
    ['title' => 'Производство', 'href' => 'production.php', 'text' => 'Выпуск, брак, загрузка', 'value' => an_num($kpi['qty']) . ' изд.', 'icon' => '▦'],
    ['title' => 'Качество', 'href' => 'reviews.php', 'text' => 'Причины брака и потери', 'value' => an_money((float) scalar_defect_cost($pdo, $period), true), 'icon' => '⚠'],
    ['title' => 'Финансы', 'href' => 'finance.php', 'text' => 'Выручка, себестоимость, прибыль', 'value' => an_money($kpi['revenue'], true), 'icon' => '₽'],
    ['title' => 'Продажи', 'href' => 'sales.php', 'text' => 'Заказы, средний чек, каналы', 'value' => an_num($kpi['orders']), 'icon' => '↗'],
    ['title' => 'Каналы', 'href' => 'partners.php', 'text' => 'План/факт, CAC, конверсия', 'value' => (string) count($channelStructure), 'icon' => '⇄'],
    ['title' => 'Кадры', 'href' => 'hr.php', 'text' => 'Численность и ФОТ', 'value' => an_num((int) $pdo->query("SELECT COUNT(*) FROM an_staff WHERE status='active'")->fetchColumn()) . ' чел.', 'icon' => '👥'],
    ['title' => 'Отчёты', 'href' => 'reports.php', 'text' => 'CSV, планы, журнал', 'value' => 'CSV', 'icon' => '⚙'],
    ['title' => 'Справочник', 'href' => 'products.php', 'text' => 'Категории и цены', 'value' => an_num((int) $pdo->query('SELECT COUNT(*) FROM an_products WHERE active = 1')->fetchColumn()) . ' поз.', 'icon' => '☰'],
    ['title' => 'Данные', 'href' => 'admin.php', 'text' => 'Источники и демо-режим', 'value' => '10 таблиц', 'icon' => '⛁'],
];

require_once 'header.php';
?>
<link rel="stylesheet" href="analytics.css">
<div class="an-wrap">
    <div class="an-head">
        <div>
            <div class="an-crumb">Аналитика эффективности · <?= an_e(an_month_label($period) . ' ' . substr($period, 0, 4)) ?></div>
            <h1>Главная</h1>
            <p class="an-sub">Ключевые показатели производства, финансов и продаж</p>
        </div>
        <div class="an-head-actions">
            <form method="get" class="an-period-form">
                <label for="period">Период</label>
                <select name="period" id="period" onchange="this.form.submit()">
                    <?php foreach (an_periods($pdo) as $option): ?>
                        <option value="<?= an_e($option) ?>" <?= $option === $period ? 'selected' : '' ?>>
                            <?= an_e(an_month_label($option) . ' ' . substr($option, 0, 4)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a class="an-btn ghost" href="reports.php?export=csv">CSV</a>
            <button class="an-btn ghost" type="button" onclick="window.print()">Печать</button>
        </div>
    </div>

    <?php foreach (an_flashes() as $flash): ?>
        <div class="an-flash <?= an_e($flash['type']) ?>"><?= an_e($flash['text']) ?></div>
    <?php endforeach; ?>

    <div class="an-kpi-grid">
        <div class="an-kpi">
            <div class="an-kpi-top">
                <span class="an-kpi-title">Производительность</span>
                <?= an_sparkline(array_column($monthly, 'qty'), '#2f6fed') ?>
            </div>
            <b class="an-kpi-value"><?= an_num($kpi['qty']) ?></b>
            <span class="an-kpi-note">изделий за месяц <em class="<?= $deltaQty['class'] ?>"><?= an_e($deltaQty['text']) ?></em></span>
        </div>
        <div class="an-kpi">
            <div class="an-kpi-top">
                <span class="an-kpi-title">Выручка</span>
                <?= an_sparkline(array_column($monthly, 'revenue'), '#12b76a') ?>
            </div>
            <b class="an-kpi-value"><?= an_money($kpi['revenue']) ?></b>
            <span class="an-kpi-note">прибыль <?= an_money($kpi['profit'], true) ?> <em class="<?= $deltaRevenue['class'] ?>"><?= an_e($deltaRevenue['text']) ?></em></span>
        </div>
        <div class="an-kpi">
            <div class="an-kpi-top">
                <span class="an-kpi-title">Коэффициент брака</span>
                <?= an_sparkline(array_column($monthly, 'defect_rate'), '#f04438') ?>
            </div>
            <b class="an-kpi-value danger"><?= number_format((float) $kpi['defect_rate'], 1, ',', ' ') ?> %</b>
            <span class="an-kpi-note"><?= an_num($kpi['defects']) ?> изделий <em class="<?= $deltaDefect['class'] ?>"><?= an_e($deltaDefect['text']) ?></em></span>
        </div>
        <div class="an-kpi">
            <div class="an-kpi-top">
                <span class="an-kpi-title">Загрузка мощностей</span>
                <?= an_sparkline(array_column($monthly, 'load'), '#f79009') ?>
            </div>
            <b class="an-kpi-value"><?= number_format((float) $kpi['load'], 0, ',', ' ') ?> %</b>
            <span class="an-kpi-note"><?= an_num($kpi['hours'], 0) ?> ч из <?= an_num(an_target($pdo, 'capacity', 5400), 0) ?> <em class="<?= $deltaLoad['class'] ?>"><?= an_e($deltaLoad['text']) ?></em></span>
        </div>
    </div>

    <div class="an-split">
        <div class="an-card">
            <div class="an-card-head">
                <h2>Сводный график показателей</h2>
                <span class="an-muted">производительность · брак</span>
            </div>
            <?= $chart ?>
        </div>

        <div class="an-card an-insights">
            <div class="an-card-head"><h2>Что видно по данным</h2></div>
            <ul class="an-insight-list">
                <?php foreach ($insights as $insight): ?>
                    <li class="an-insight <?= an_e($insight['type']) ?>">
                        <span class="an-dot"></span>
                        <span><?= an_e($insight['text']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($forecast !== null): ?>
                <div class="an-forecast">
                    <span>Прогноз выручки на следующий месяц</span>
                    <b><?= an_money($forecast) ?></b>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="an-split">
        <div class="an-card">
            <div class="an-card-head"><h2>Топ-5 продуктов по выручке</h2></div>
            <?php if ($topProducts): ?>
                <table class="an-table">
                    <thead><tr><th>Продукт</th><th>Заказов</th><th>Выручка</th><th>Доля</th></tr></thead>
                    <tbody>
                    <?php $topRevenue = array_sum(array_map(static fn ($row) => (float) $row['revenue'], $topProducts)); ?>
                    <?php foreach ($topProducts as $row): ?>
                        <tr>
                            <td><?= an_e($row['product']) ?></td>
                            <td><?= an_num($row['orders']) ?></td>
                            <td><?= an_money($row['revenue']) ?></td>
                            <td><?= $topRevenue > 0 ? number_format((float) $row['revenue'] / $topRevenue * 100, 0, ',', ' ') : 0 ?> %</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="an-note">Заказов за период нет.</p>
            <?php endif; ?>
        </div>

        <div class="an-card">
            <div class="an-card-head"><h2>Структура выручки по каналам</h2></div>
            <?php foreach ($channelStructure as $row): ?>
                <div class="an-bar-row">
                    <span><?= an_e($row['channel']) ?></span>
                    <div class="an-bar-track">
                        <div class="an-bar-fill" style="width: <?= $channelMax > 0 ? round((float) $row['revenue'] / $channelMax * 100, 1) : 0 ?>%"></div>
                    </div>
                    <b><?= an_money((float) $row['revenue'], true) ?></b>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <p class="an-updated">Данные на <?= an_e(date('d.m.Y H:i')) ?> · источник: база данных</p>

    <div class="an-card">
        <div class="an-card-head"><h2>Разделы системы</h2></div>
        <div class="an-sections">
            <?php foreach ($sections as $section): ?>
                <a class="an-section" href="<?= an_e($section['href']) ?>">
                    <span class="an-section-icon"><?= $section['icon'] ?></span>
                    <span class="an-section-body">
                        <b><?= an_e($section['title']) ?></b>
                        <span><?= an_e($section['text']) ?></span>
                    </span>
                    <em><?= an_e($section['value']) ?></em>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require_once 'analytics_footer.php'; ?>
