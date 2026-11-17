<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($pageTitle)) $pageTitle = 'Мебельная Фабрика "Фаворит"';
if (!isset($activePage)) $activePage = '';
if (!isset($productsCount)) {
    try { $productsCount = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn(); }
    catch (Exception $e) { $productsCount = 0; }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="custom.css">
    <style>
    /* ============ УБИВАЕМ ВСЕ SVG ============ */
    svg { display: none !important; }
    .info-icon, .service-icon, .metric-icon, .search-icon, .search-icon-btn {
        display: none !important;
    }

    /* ============ ЛОГОТИП ============ */
    .logo-icon, .logo img {
        width: 45px !important;
        height: 45px !important;
        max-width: 45px !important;
        max-height: 45px !important;
        object-fit: contain !important;
        display: block !important;
    }

    /* ============ СОЦ-ИКОНКИ ============ */
    .social-icons {
        display: flex !important;
        gap: 10px !important;
        flex-wrap: wrap !important;
        margin-top: 15px !important;
    }
    .social-icon {
        width: 40px !important;
        height: 40px !important;
        max-width: 40px !important;
        max-height: 40px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: #4A4A4A !important;
        border-radius: 8px !important;
        overflow: hidden !important;
        text-decoration: none !important;
    }
    .social-icon img {
        width: 20px !important;
        height: 20px !important;
        max-width: 20px !important;
        max-height: 20px !important;
        object-fit: contain !important;
        display: block !important;
    }

    /* ============ ВСЕ КАРТИНКИ — ОГРАНИЧЕНЫ ============ */
    img {
        max-width: 100% !important;
        height: auto !important;
    }

    /* Карусель на главной */
    .carousel-slide img {
        width: 100% !important;
        height: 100% !important;
        max-height: 500px !important;
        object-fit: cover !important;
    }

    /* Товары и статьи */
    .product-image img {
        width: 100% !important;
        height: 250px !important;
        max-height: 250px !important;
        object-fit: cover !important;
    }

    /* Картинка статьи */
    .info-card > img {
        width: 100% !important;
        height: auto !important;
        max-height: 400px !important;
        object-fit: cover !important;
        border-radius: 12px !important;
        margin-bottom: 25px !important;
    }

    /* Картинка товара в форме заказа */
    #order-product-img, .order-product-img {
        max-width: 300px !important;
        max-height: 300px !important;
        object-fit: cover !important;
        border-radius: 12px !important;
    }

    .nav-submenu { list-style: none; margin: 4px 0 0 18px; padding: 0 0 0 12px; border-left: 1px solid #4a4a4a; }
    .nav-submenu a { display: block; padding: 6px 8px; color: #c9c9c9 !important; font-size: 12.5px; border-radius: 6px; text-decoration: none; }
    .nav-submenu a:hover { background: #3a3a3a; color: #fff !important; }
    .nav-submenu a.sub-active { color: #fff !important; background: #4a4a4a; font-weight: 600; }

    .sidebar .nav-item > a { padding-left: 22px !important; }
    .sidebar .nav-submenu { margin-left: 24px !important; }

    .brand-text { display: grid; gap: 2px; line-height: 1.1; }
    .brand-text span { font-size: 12px; letter-spacing: .12em; color: #c9c9c9; }
    .brand-text b { font-size: 16px; color: #fff; }
</style>
</head>
<body>
    <button class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</button>

    <nav class="sidebar" id="sidebar">
        <div class="logo">
            <img src="изображения/logo.jpg" alt="logo" class="logo-icon">
            <div class="brand-text"><span>СМП</span><b>Фаворит Диван</b></div>
        </div>
        <ul class="nav-menu">
            <li class="nav-item <?= $activePage==='dashboard'?'active':'' ?>"><a href="analytics.php"><span>Дашборд</span></a></li>
            <li class="nav-item <?= $activePage==='orders'?'active':'' ?>"><a href="orders.php"><span>Заказы</span></a></li>
            <li class="nav-item <?= $activePage==='production'?'active':'' ?>"><a href="production.php"><span>Производство</span></a></li>
            <li class="nav-item <?= $activePage==='quality'?'active':'' ?>"><a href="reviews.php"><span>Качество</span></a></li>
            <li class="nav-item <?= $activePage==='finance'?'active':'' ?>"><a href="finance.php"><span>Финансы</span></a></li>
            <li class="nav-item <?= $activePage==='sales'?'active':'' ?>"><a href="sales.php"><span>Продажи</span></a></li>
            <li class="nav-item <?= $activePage==='channels'?'active':'' ?>"><a href="partners.php"><span>Каналы</span></a></li>
            <li class="nav-item <?= $activePage==='hr'?'active':'' ?>"><a href="hr.php"><span>Кадры</span></a></li>
            <li class="nav-item <?= $activePage==='reports'?'active':'' ?>"><a href="reports.php"><span>Отчёты</span></a></li>
            <li class="nav-item <?= $activePage==='catalog'?'active':'' ?>"><a href="products.php"><span>Справочник</span></a></li>
            <li class="nav-item <?= $activePage==='admin'?'active':'' ?>"><a href="admin.php"><span>Данные</span></a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item"><a href="logout.php"><span>Выход</span></a></li>
            <?php else: ?>
                <li class="nav-item <?= $activePage==='login'?'active':'' ?>"><a href="login.php"><span>Вход</span></a></li>
            <?php endif; ?>
        </ul>
        <div class="sidebar-user">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span class="role"><?= ($_SESSION['role'] ?? 'manager') === 'admin' ? 'администратор' : 'сотрудник' ?></span>
                <span class="name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Сотрудник') ?></span>
                <a href="logout.php">Выйти</a>
            <?php else: ?>
                <span class="name">Гостевой режим</span>
                <a href="login.php">Войти как сотрудник</a>
            <?php endif; ?>
        </div>
    </nav>

    <main class="main-content">