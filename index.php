<?php
// Сайт полностью посвящён аналитике эффективности: главная — дашборд.
header('Location: analytics.php');
exit;
require_once 'db.php';
$pageTitle = 'Мебельная Фабрика — О производстве';
$activePage = 'index';

$reviewsStmt = $pdo->query("SELECT * FROM reviews WHERE status='Опубликован' ORDER BY created_at DESC LIMIT 3");
$latestReviews = $reviewsStmt->fetchAll();

require_once 'header.php';
?>

<header class="page-header">
    <h1>О нашем производстве</h1>
    <p class="subtitle">Современное мебельное производство полного цикла</p>
</header>

<section class="carousel-section">
    <div class="carousel-container">
        <div class="carousel">
            <div class="carousel-slide active">
                <img src="изображения/завод1.jpg" alt="Производственный цех">
                <div class="carousel-caption">Производственный цех</div>
            </div>
            <div class="carousel-slide">
                <img src="изображения/завод2.jpg" alt="Цех обработки древесины">
                <div class="carousel-caption">Цех с листами и конвейерами</div>
            </div>
            <div class="carousel-slide">
                <img src="изображения/завод3.jpeg" alt="Сборочный цех">
                <div class="carousel-caption">CNC станок</div>
            </div>
        </div>
        <button class="carousel-btn prev" onclick="moveCarousel(-1)">&#10094;</button>
        <button class="carousel-btn next" onclick="moveCarousel(1)">&#10095;</button>
        <div class="carousel-dots">
            <span class="dot active" onclick="currentSlide(0)"></span>
            <span class="dot" onclick="currentSlide(1)"></span>
            <span class="dot" onclick="currentSlide(2)"></span>
        </div>
    </div>
</section>

<section class="info-section">
    <div class="info-grid">
        <div class="info-card">
            <div class="info-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
            </div>
            <h3>Производственные мощности</h3>
            <p>Наша фабрика оснащена современным высокотехнологичным оборудованием ведущих мировых производителей. Производственная площадь составляет более 15 000 м².</p>
            <ul class="feature-list">
                <li>Автоматизированные линии раскроя</li>
                <li>Станки ЧПУ для точной обработки</li>
                <li>Камеры сушки и окраски</li>
                <li>Современные сборочные цеха</li>
            </ul>
        </div>

        <div class="info-card">
            <div class="info-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
            </div>
            <h3>Качество материалов</h3>
            <p>Мы используем только сертифицированные материалы от проверенных поставщиков. Каждая партия проходит строгий контроль качества.</p>
            <ul class="feature-list">
                <li>Натуральная древесина премиум класса</li>
                <li>Экологически чистые материалы</li>
                <li>Современные обивочные ткани</li>
                <li>Фурнитура европейского качества</li>
            </ul>
        </div>

        <div class="info-card">
            <div class="info-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
            <h3>Профессиональная команда</h3>
            <p>Наши специалисты имеют многолетний опыт работы в мебельной индустрии. Каждый сотрудник регулярно проходит повышение квалификации.</p>
            <ul class="feature-list">
                <li>Опытные мастера-краснодеревщики</li>
                <li>Квалифицированные технологи</li>
                <li>Профессиональные дизайнеры</li>
                <li>Служба контроля качества</li>
            </ul>
        </div>

        <div class="info-card">
            <div class="info-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
            <h3>Индивидуальный подход</h3>
            <p>Мы изготавливаем мебель как по типовым каталогам, так и по индивидуальным проектам заказчика. Возможность реализации любых дизайнерских решений.</p>
            <ul class="feature-list">
                <li>Разработка уникальных проектов</li>
                <li>3D визуализация перед производством</li>
                <li>Гибкая система скидок</li>
                <li>Доставка и монтаж</li>
            </ul>
        </div>
    </div>
</section>

<section class="stats-section">
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-number">15+</div><div class="stat-label">Лет на рынке</div></div>
        <div class="stat-card"><div class="stat-number">5000+</div><div class="stat-label">Довольных клиентов</div></div>
        <div class="stat-card"><div class="stat-number">200+</div><div class="stat-label">Изделий в месяц</div></div>
        <div class="stat-card"><div class="stat-number">50+</div><div class="stat-label">Сотрудников</div></div>
    </div>
</section>

<?php if (!empty($latestReviews)): ?>
<section class="info-section">
    <h2 style="text-align:center; margin-bottom:25px; color:#2C2C2C;">Последние отзывы</h2>
    <div class="info-grid">
        <?php foreach ($latestReviews as $r): ?>
            <div class="info-card">
                <div class="review-stars"><?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?></div>
                <p style="margin:12px 0;"><?= htmlspecialchars(mb_substr($r['review_text'], 0, 160)) ?>...</p>
                <p style="font-weight:bold;">— <?= htmlspecialchars($r['author_name']) ?></p>
                <p style="font-size:13px;color:#7A7A7A;"><?= date('d.m.Y', strtotime($r['created_at'])) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <p style="text-align:center; margin-top:20px;">
        <a href="reviews.php" class="btn-primary">Все отзывы</a>
    </p>
</section>
<?php endif; ?>

<?php require_once 'footer.php'; ?>