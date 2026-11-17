<?php
require_once 'db.php';
require_once 'analytics_lib.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$monthly = an_monthly($pdo);

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="favorit-analytics-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Месяц', 'Выпуск, шт.', 'Брак, шт.', 'Брак, %', 'Часы', 'Загрузка, %', 'Выручка, ₽', 'Себестоимость, ₽', 'Прибыль, ₽', 'Заказы'], ';');
    foreach ($monthly as $row) {
        fputcsv($out, [
            $row['period'], $row['qty'], $row['defects'], number_format($row['defect_rate'], 2, ',', ''),
            number_format($row['hours'], 1, ',', ''), number_format($row['load'], 1, ',', ''),
            number_format($row['revenue'], 2, ',', ''), number_format($row['cost'], 2, ',', ''),
            number_format($row['profit'], 2, ',', ''), $row['orders'],
        ], ';');
    }
    fclose($out);
    an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'гость'), 'Экспорт отчёта', 'CSV за ' . count($monthly) . ' мес.');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && an_can_edit()) {
    an_check_csrf();
    if (($_POST['action'] ?? '') === 'targets') {
        $values = [
            'capacity' => max(1, (float) ($_POST['capacity'] ?? 5400)),
            'defect_max' => max(0, (float) ($_POST['defect_max'] ?? 3)),
            'revenue_plan' => max(0, (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['revenue_plan'] ?? 0))),
        ];
        $update = $pdo->prepare('UPDATE an_targets SET target_value = ? WHERE metric = ?');
        foreach ($values as $metric => $value) {
            $update->execute([$value, $metric]);
        }
        an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Изменены плановые показатели', json_encode($values, JSON_UNESCAPED_UNICODE));
        an_flash('Плановые показатели обновлены.');
    }
    an_redirect('reports.php');
}

$audit = $pdo->query('SELECT * FROM an_audit ORDER BY id DESC LIMIT 50')->fetchAll();
$targets = $pdo->query('SELECT * FROM an_targets ORDER BY metric')->fetchAll();
$pageTitle = 'Управление и отчётность';
$activePage = 'reports';
require_once 'header.php';
?>
<link rel="stylesheet" href="analytics.css">
<div class="an-wrap">
    <div class="an-head">
        <div><h1>Управление и отчётность</h1><p class="an-sub">Сводные отчёты, плановые показатели и журнал действий</p></div>
        <a class="an-btn" href="?export=csv">Скачать CSV</a>
    </div>

    <?php foreach (an_flashes() as $flash): ?>
        <div class="an-flash <?= an_e($flash['type']) ?>"><?= an_e($flash['text']) ?></div>
    <?php endforeach; ?>

    <div class="an-card">
        <div class="an-card-head"><h2>Сводный отчёт по месяцам</h2></div>
        <table class="an-table">
            <thead><tr><th>Месяц</th><th>Выпуск</th><th>Брак</th><th>Часы</th><th>Загрузка</th><th>Выручка</th><th>Прибыль</th><th>Заказы</th></tr></thead>
            <tbody>
            <?php foreach ($monthly as $row): ?>
                <tr>
                    <td><?= an_e($row['label']) ?> <?= an_e(substr($row['period'], 0, 4)) ?></td>
                    <td><?= an_num($row['qty']) ?></td>
                    <td><?= number_format($row['defect_rate'], 1, ',', ' ') ?> %</td>
                    <td><?= an_num($row['hours'], 0) ?></td>
                    <td><?= number_format($row['load'], 0, ',', ' ') ?> %</td>
                    <td><?= an_money($row['revenue']) ?></td>
                    <td><?= an_money($row['profit']) ?></td>
                    <td><?= an_num($row['orders']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="an-card">
        <div class="an-card-head"><h2>Плановые показатели</h2></div>
        <?php if (an_can_edit()): ?>
            <form method="post" class="an-form">
                <?= an_csrf_field() ?>
                <input type="hidden" name="action" value="targets">
                <label>Плановая загрузка, ч/мес<input type="number" step="1" name="capacity" value="<?= (int) an_target($pdo, 'capacity', 5400) ?>"></label>
                <label>Допустимый брак, %<input type="number" step="0.1" name="defect_max" value="<?= an_e(an_target($pdo, 'defect_max', 3)) ?>"></label>
                <label>План выручки, ₽<input type="number" step="1000" name="revenue_plan" value="<?= (int) an_target($pdo, 'revenue_plan', 8500000) ?>"></label>
                <button class="an-btn" type="submit">Сохранить</button>
            </form>
        <?php else: ?>
            <table class="an-table">
                <thead><tr><th>Показатель</th><th>Значение</th></tr></thead>
                <tbody>
                <?php foreach ($targets as $row): ?>
                    <tr><td><?= an_e($row['title']) ?></td><td><?= an_num($row['target_value'], 1) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="an-card">
        <div class="an-card-head"><h2>Журнал действий</h2></div>
        <?php if ($audit): ?>
            <table class="an-table">
                <thead><tr><th>Дата</th><th>Сотрудник</th><th>Действие</th><th>Детали</th></tr></thead>
                <tbody>
                <?php foreach ($audit as $row): ?>
                    <tr>
                        <td><?= an_e(date('d.m.Y H:i', strtotime($row['created_at']))) ?></td>
                        <td><?= an_e($row['actor']) ?></td>
                        <td><?= an_e($row['action']) ?></td>
                        <td class="muted"><?= an_e($row['details']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="an-note">Действий пока не было.</p>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'analytics_footer.php'; ?>
