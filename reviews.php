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
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $qty = max(0, (int) ($_POST['qty'] ?? 0));
        $cost = max(0, (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['cost'] ?? 0)));
        if ($workshop === '' || $reason === '' || $qty === 0) {
            an_flash('Заполните цех, причину и количество.', 'error');
        } else {
            $pdo->prepare('INSERT INTO an_defects (period, workshop, reason, qty, cost, status) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$period, $workshop, $reason, $qty, $cost, 'open']);
            an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Зафиксирован брак', $reason . ', ' . $qty . ' шт.');
            an_flash('Запись о браке добавлена.');
        }
    } elseif ($action === 'close') {
        $pdo->prepare("UPDATE an_defects SET status = 'closed' WHERE id = ?")->execute([(int) ($_POST['id'] ?? 0)]);
        an_flash('Запись закрыта.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM an_defects WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        an_flash('Запись удалена.');
    }
    an_redirect('reviews.php?period=' . urlencode((string) ($_POST['period'] ?? '')));
}

$period = an_selected_period($pdo);
$rows = $pdo->prepare('SELECT * FROM an_defects WHERE period = ? ORDER BY cost DESC');
$rows->execute([$period]);
$rows = $rows->fetchAll();

$totalQty = array_sum(array_map(static fn ($row) => (int) $row['qty'], $rows));
$totalCost = array_sum(array_map(static fn ($row) => (float) $row['cost'], $rows));
$open = count(array_filter($rows, static fn ($row) => $row['status'] === 'open'));
$byReason = [];
foreach ($rows as $row) {
    $byReason[$row['reason']] = ($byReason[$row['reason']] ?? 0) + (float) $row['cost'];
}
arsort($byReason);
$topReason = $byReason ? array_key_first($byReason) : '—';

$stmt = $pdo->prepare('SELECT COALESCE(SUM(revenue),0) FROM an_sales WHERE period = ?');
$stmt->execute([$period]);
$revenue = (float) $stmt->fetchColumn();
$lossShare = $revenue > 0 ? $totalCost / $revenue * 100 : 0;

$chart = an_bar_chart(array_keys($byReason), array_values($byReason), '#f04438', static fn ($v) => an_money($v, true));

$pageTitle = 'Качество продукции';
$activePage = 'quality';
require_once 'header.php';
?>
<link rel="stylesheet" href="analytics.css">
<div class="an-wrap">
    <div class="an-head">
        <div><h1>Качество продукции</h1><p class="an-sub">Брак, причины и потери — обратная связь для производства</p></div>
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
        <div class="an-kpi"><span class="an-kpi-title">Брак за период</span><b class="an-kpi-value danger"><?= an_num($totalQty) ?></b><span class="an-kpi-note">изделий</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Потери от брака</span><b class="an-kpi-value"><?= an_money($totalCost) ?></b><span class="an-kpi-note"><?= number_format($lossShare, 1, ',', ' ') ?> % выручки</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Открытых случаев</span><b class="an-kpi-value"><?= an_num($open) ?></b><span class="an-kpi-note">требуют решения</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Главная причина</span><b class="an-kpi-value small"><?= an_e($topReason) ?></b><span class="an-kpi-note"><?= an_money($byReason[$topReason] ?? 0, true) ?> потерь</span></div>
    </div>

    <div class="an-card"><div class="an-card-head"><h2>Потери по причинам брака</h2></div><?= $chart ?></div>

    <div class="an-card">
        <div class="an-card-head"><h2>Журнал брака</h2></div>
        <table class="an-table">
            <thead><tr><th>Цех</th><th>Причина</th><th>Кол-во</th><th>Потери</th><th>Статус</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr class="<?= $row['status'] === 'open' ? '' : 'muted' ?>">
                    <td><?= an_e($row['workshop']) ?></td>
                    <td><?= an_e($row['reason']) ?></td>
                    <td><?= an_num($row['qty']) ?></td>
                    <td><?= an_money($row['cost']) ?></td>
                    <td><?= $row['status'] === 'open' ? 'открыт' : 'закрыт' ?></td>
                    <td>
                        <?php if (an_can_edit()): ?>
                            <?php if ($row['status'] === 'open'): ?>
                                <form method="post" class="inline">
                                    <?= an_csrf_field() ?>
                                    <input type="hidden" name="action" value="close">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="period" value="<?= an_e($period) ?>">
                                    <button class="an-link" type="submit">Закрыть</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" class="inline" onsubmit="return confirm('Удалить запись?');">
                                <?= an_csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
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
            <div class="an-card-head"><h2>Зафиксировать брак</h2></div>
            <form method="post" class="an-form">
                <?= an_csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <label>Месяц<input type="month" name="period" value="<?= an_e($period) ?>" required></label>
                <label>Цех<input type="text" name="workshop" placeholder="Цех мягкой мебели" required></label>
                <label>Причина<input type="text" name="reason" placeholder="Оборудование" required></label>
                <label>Кол-во<input type="number" name="qty" min="1" required></label>
                <label>Потери, ₽<input type="number" step="0.01" name="cost" min="0" required></label>
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
