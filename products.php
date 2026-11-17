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
        $title = trim((string) ($_POST['title'] ?? ''));
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $unit = trim((string) ($_POST['unit'] ?? 'шт.')) ?: 'шт.';
        $price = max(0, (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['price'] ?? 0)));
        if ($title === '' || $categoryId === 0) {
            an_flash('Укажите наименование и категорию.', 'error');
        } else {
            $pdo->prepare('INSERT INTO an_products (category_id, title, unit, price, active) VALUES (?, ?, ?, ?, 1)')
                ->execute([$categoryId, $title, $unit, $price]);
            an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Добавлена продукция', $title);
            an_flash('Позиция справочника добавлена.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE an_products SET active = 0 WHERE id = ?')->execute([$id]);
        an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Архивирована продукция', 'id ' . $id);
        an_flash('Позиция переведена в архив.');
    } elseif ($action === 'category') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name !== '') {
            $pdo->prepare('INSERT INTO an_categories (name) VALUES (?)')->execute([$name]);
            an_audit($pdo, (string) ($_SESSION['user_name'] ?? 'пользователь'), 'Добавлена категория', $name);
            an_flash('Категория добавлена.');
        }
    }
    an_redirect('products.php');
}

$categories = $pdo->query('SELECT * FROM an_categories ORDER BY id')->fetchAll();
$products = $pdo->query('SELECT p.*, c.name AS category FROM an_products p JOIN an_categories c ON c.id = p.category_id ORDER BY c.id, p.title')->fetchAll();
$activeProducts = array_values(array_filter($products, static fn ($row) => (int) $row['active'] === 1));
$avgPrice = $activeProducts ? array_sum(array_map(static fn ($row) => (float) $row['price'], $activeProducts)) / count($activeProducts) : 0;

$byCategory = [];
foreach ($activeProducts as $row) {
    $byCategory[$row['category']][] = $row;
}
$chart = an_bar_chart(array_keys($byCategory), array_map('count', $byCategory), '#2f6fed', 'an_num');

$pageTitle = 'Справочник продукции';
$activePage = 'catalog';
require_once 'header.php';
?>
<link rel="stylesheet" href="analytics.css">
<div class="an-wrap">
    <div class="an-head">
        <div><h1>Справочник продукции</h1><p class="an-sub">Категории, цены и единицы измерения — основа производственных и финансовых расчётов</p></div>
    </div>

    <?php foreach (an_flashes() as $flash): ?>
        <div class="an-flash <?= an_e($flash['type']) ?>"><?= an_e($flash['text']) ?></div>
    <?php endforeach; ?>

    <div class="an-kpi-grid">
        <div class="an-kpi"><span class="an-kpi-title">Позиций в справочнике</span><b class="an-kpi-value"><?= an_num(count($activeProducts)) ?></b><span class="an-kpi-note">активных</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Категорий</span><b class="an-kpi-value"><?= an_num(count($categories)) ?></b><span class="an-kpi-note">продуктовых групп</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Средняя цена</span><b class="an-kpi-value"><?= an_money($avgPrice) ?></b><span class="an-kpi-note">по активным позициям</span></div>
        <div class="an-kpi"><span class="an-kpi-title">Архив</span><b class="an-kpi-value"><?= an_num(count($products) - count($activeProducts)) ?></b><span class="an-kpi-note">снято с производства</span></div>
    </div>

    <div class="an-card"><div class="an-card-head"><h2>Позиции по категориям</h2></div><?= $chart ?></div>

    <div class="an-card">
        <div class="an-card-head"><h2>Продукция</h2></div>
        <table class="an-table">
            <thead><tr><th>Наименование</th><th>Категория</th><th>Ед.</th><th>Цена</th><th>Статус</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($products as $row): ?>
                <tr class="<?= (int) $row['active'] === 1 ? '' : 'muted' ?>">
                    <td><?= an_e($row['title']) ?></td>
                    <td><?= an_e($row['category']) ?></td>
                    <td><?= an_e($row['unit']) ?></td>
                    <td><?= an_money($row['price']) ?></td>
                    <td><?= (int) $row['active'] === 1 ? 'в производстве' : 'архив' ?></td>
                    <td>
                        <?php if (an_can_edit() && (int) $row['active'] === 1): ?>
                            <form method="post" onsubmit="return confirm('Перевести в архив?');">
                                <?= an_csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="an-link danger" type="submit">В архив</button>
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
            <div class="an-card-head"><h2>Добавить продукцию</h2></div>
            <form method="post" class="an-form">
                <?= an_csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <label>Наименование<input type="text" name="title" required></label>
                <label>Категория
                    <select name="category_id" required>
                        <?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= an_e($category['name']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Единица<input type="text" name="unit" value="шт."></label>
                <label>Цена, ₽<input type="number" step="0.01" name="price" min="0" required></label>
                <button class="an-btn" type="submit">Добавить</button>
            </form>
        </div>
        <div class="an-card">
            <div class="an-card-head"><h2>Новая категория</h2></div>
            <form method="post" class="an-form">
                <?= an_csrf_field() ?>
                <input type="hidden" name="action" value="category">
                <label>Название<input type="text" name="name" required></label>
                <button class="an-btn" type="submit">Добавить категорию</button>
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
