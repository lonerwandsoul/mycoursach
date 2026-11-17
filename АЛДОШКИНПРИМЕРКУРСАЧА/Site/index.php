<?php
require 'db.php';
$user = current_user($link);
$page = $_GET['page'] ?? 'home';

$public_pages = ['home', 'vacancies', 'vacancy', 'contacts', 'help', 'login', 'register'];
if (!$user && !in_array($page, $public_pages)) {
    header('Location: index.php?page=login');
    exit;
}

$userName = (isset($user) && $user) ? htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) : 'Имя Фамилия';
$userInitials = (isset($user) && $user) ? mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1) : 'ИФ';
$userAvatar = (isset($user) && !empty($user['avatar'])) ? "uploads/" . $user['avatar'] : null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Электронный рекрутинг - ФКП "Комбинат Каменский"</title>
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/layout.css">
<link rel="stylesheet" href="css/components.css">
<link rel="stylesheet" href="css/pages.css">
<link rel="stylesheet" href="css/responsive.css">
</head>
<body>

<!-- ЭКРАН АВТОРИЗАЦИИ -->
<div id="authWrapper" class="auth-wrapper <?= ($page === 'login' || $page === 'register') ? '' : 'hidden' ?>">
    
    <!-- ВХОД -->
    <div id="loginBox" class="auth-container <?= ($page === 'register') ? 'hidden' : '' ?>">
        <div class="auth-logo">
            <h1>ФКП "Комбинат Каменский"</h1>
        </div>
        <h2>Вход в систему</h2>
        <?php if (isset($_GET['error']) && $page === 'login'): ?>
            <div class="error-message show"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>
        <form action="actions.php" method="post">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" placeholder="ivanov@example.com" required>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" class="form-control" placeholder="Введите пароль" required>
            </div>
            <button type="submit" class="btn btn-block">Войти</button>
        </form>
        <div class="auth-links">
            <p>Нет аккаунта? <a href="index.php?page=register">Зарегистрироваться</a></p>
        </div>
        <a class="back-link" href="index.php?page=home">← Продолжить без входа</a>
    </div>

    <!-- РЕГИСТРАЦИЯ -->
    <div id="registerBox" class="auth-container <?= ($page === 'register') ? '' : 'hidden' ?>">
        <div class="auth-logo">
            <h1>ФКП "Комбинат Каменский"</h1>
        </div>
        <h2>Регистрация</h2>
        <?php if (isset($_GET['error']) && $page === 'register'): ?>
            <div class="error-message show"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>
        <form action="actions.php" method="post">
            <input type="hidden" name="action" value="register">
            <div class="form-row">
                <div class="form-group">
                    <label>Фамилия *</label>
                    <input type="text" name="last_name" class="form-control" placeholder="Иванов" required>
                </div>
                <div class="form-group">
                    <label>Имя *</label>
                    <input type="text" name="first_name" class="form-control" placeholder="Иван" required>
                </div>
            </div>
            <div class="form-group">
                <label>Отчество</label>
                <input type="text" name="middle_name" class="form-control" placeholder="Иванович">
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" class="form-control" placeholder="ivanov@example.com" required>
            </div>
            <div class="form-group">
                <label>Телефон *</label>
                <input type="tel" name="phone" class="form-control" placeholder="+7 (999) 123-45-67" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Пароль *</label>
                    <input type="password" name="password" class="form-control" placeholder="Мин. 6 символов" required>
                </div>
                <div class="form-group">
                    <label>Повторите пароль *</label>
                    <input type="password" name="password2" class="form-control" placeholder="Повторите пароль" required>
                </div>
            </div>
            <button type="submit" class="btn btn-block">Зарегистрироваться</button>
        </form>
        <div class="auth-links">
            <p>Уже есть аккаунт? <a href="index.php?page=login">Войти</a></p>
        </div>
        <a class="back-link" href="index.php?page=home">← Продолжить без входа</a>
    </div>
</div>

<!-- ОСНОВНОЙ САЙТ -->
<div id="mainApp" class="" style="display:flex; flex-direction:column; min-height:100vh;">

<!-- ШАПКА -->
<header class="main-header" style="position: sticky; top: 0; z-index: 1000;">
        <div class="container">
        <div class="header-content">
            <div class="header-left">
                <button class="mobile-menu-btn" onclick="toggleSidebar(event)">☰</button>
                <div class="logo">
                    <a href="index.php?page=home"><img src="assets/рисунок.svg" alt="Логотип" width="40" height="40" onerror="this.style.display='none'"></a>
                    <a href="index.php?page=home" class="logo-text">ФКП "Комбинат Каменский"</a>
                </div>
            </div>
            <div class="header-right">
                <!-- УВЕДОМЛЕНИЯ -->
                <div class="notifications" id="notificationsBtn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="white" stroke-width="2"/><path d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21" stroke="white" stroke-width="2"/></svg>
                    <div class="notifications-dropdown" id="notifDropdown">
                        <div class="notification-item" data-link="applications"><div class="notification-title">Новое сообщение</div><div class="notification-text">Вас пригласили на собеседование</div></div>
                    </div>
                </div>
                                <!-- ПРОФИЛЬ ИЛИ КНОПКА ВОЙТИ -->
                <?php if ($user): ?>
                    <!-- Если пользователь авторизован, показываем профиль -->
                    <div class="profile" id="profileBtn">
                        <?php if ($userAvatar): ?>
                            <img src="<?= $userAvatar ?>" class="profile-avatar-small" style="object-fit: cover;">
                        <?php else: ?>
                            <div class="profile-avatar-small"><?= $userInitials ?></div>
                        <?php endif; ?>
                        <span><?= $userName ?></span>
                        <div class="profile-dropdown" id="profileDropdown">
                            <ul class="profile-menu">
                                <li><a href="index.php?page=cabinet">Мой профиль</a></li>
                                <li><a href="index.php?page=settings">Настройки</a></li>
                                <li><a href="logout.php">Выйти</a></li>
                            </ul>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Если пользователь НЕ авторизован, показываем кнопку Войти -->
                    <a href="index.php?page=login" class="btn" style="background: white; color: var(--primary-blue); padding: 8px 20px; border-radius: 4px; font-weight: bold; text-decoration: none; margin-left: 10px;">Войти</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<!-- ОСНОВНОЙ КОНТЕНТ -->
<div class="main-content" style="position: relative;">
    <!-- Невидимая зона для выезда меню на ПК -->
    <div class="sidebar-trigger"></div>

    <!-- ВЫЕЗДНОЕ МЕНЮ -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-close" onclick="toggleSidebar(event)">×</div>
        <!-- ... блоки меню оставлены как были ... -->
        <div class="sidebar-section">
            <h3>Главный раздел</h3>
            <ul class="sidebar-nav">
                <li><a href="index.php?page=home" class="<?= $page === 'home' ? 'active' : '' ?>">Главная</a></li>
                <li><a href="index.php?page=vacancies" class="<?= $page === 'vacancies' ? 'active' : '' ?>">Поиск вакансий</a></li>
                <li><a href="index.php?page=cabinet" class="<?= $page === 'cabinet' ? 'active' : '' ?>">Личный кабинет</a></li>
            </ul>
        </div>
        <div class="sidebar-section">
            <h3>Кандидату</h3>
            <ul class="sidebar-nav">
                <li><a href="index.php?page=resume" class="<?= $page === 'resume' ? 'active' : '' ?>">Создать резюме</a></li>
                <li><a href="index.php?page=applications" class="<?= $page === 'applications' ? 'active' : '' ?>">Мои отклики</a></li>
                <li><a href="index.php?page=favorites" class="<?= $page === 'favorites' ? 'active' : '' ?>">Избранные вакансии</a></li>
            </ul>
        </div>
        <div class="sidebar-section">
            <h3>Дополнительно</h3>
            <ul class="sidebar-nav">
                <li><a href="index.php?page=contacts" class="<?= $page === 'contacts' ? 'active' : '' ?>">Контакты</a></li>
                <li><a href="index.php?page=help" class="<?= $page === 'help' ? 'active' : '' ?>">Помощь</a></li>
            </ul>
        </div>
    </aside>
    <div class="sidebar-overlay" onclick="toggleSidebar(event)"></div>

        <div class="page-content">
        <?php
        if ($page !== 'login' && $page !== 'register') {
            $file = "pages/{$page}.php";
            if (file_exists($file)) { include $file; } else { echo "<div class='page'><h2>Страница не найдена</h2></div>"; }
        }
        ?>
    </div>
</div>

<!-- ПОДВАЛ -->
<footer>
        <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>О предприятии</h3>
                <ul>
                    <li><a href="index.php?page=about">История</a></li>
                    <li><a href="index.php?page=about#products">Продукция</a></li>
                    <li><a href="index.php?page=about#certs">Сертификаты</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Карьера</h3>
                <ul>
                    <li><a href="index.php?page=vacancies">Вакансии</a></li>
                    <li><a href="index.php?page=internship">Стажировки</a></li>
                    <li><a href="index.php?page=conditions">Условия работы</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Контакты</h3>
                <ul>
                    <li><a href="index.php?page=contacts">Отдел кадров</a></li>
                    <li><a href="index.php?page=contacts">Руководство</a></li>
                    <li><a href="index.php?page=contacts">Адрес предприятия</a></li>
                </ul>
            </div>
        </div>
        <div class="copyright">
            &copy; 2026 ФКП "Комбинат Каменский". Все права защищены.
        </div>
    </div>
</footer>
</div>

<script src="js/ui.js"></script>
<script src="js/main.js"></script>
<script>
function toggleSidebar(e) {
    if(e) e.stopPropagation();
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    if(sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    } else {
        sidebar.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden'; // блокируем скролл фона
    }
}
</script>
</body>
</html>