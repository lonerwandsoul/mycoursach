<?php
require_once 'db.php';
require_once 'analytics_lib.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: analytics.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string) ($_POST['login'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    $statement = $pdo->prepare('SELECT * FROM users WHERE login = ?');
    $statement->execute([$login]);
    $user = $statement->fetch();
    if ($user && password_verify($pass, (string) $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = (string) ($user['full_name'] ?: $user['login']);
        $_SESSION['role'] = (string) ($user['role'] ?? 'manager');
        an_audit($pdo, $_SESSION['user_name'], 'Вход в систему', '');
        header('Location: analytics.php');
        exit;
    }
    $error = 'Неверный логин или пароль.';
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — Фаворит Диван</title>
    <link rel="stylesheet" href="analytics.css">
    <style>
        body { margin: 0; background: #0f1117; font-family: 'Segoe UI', system-ui, sans-serif; }
        .login-shell { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .login-card { width: min(420px, 100%); background: #fff; border-radius: 18px; padding: 32px; box-shadow: 0 24px 60px rgba(0,0,0,.35); }
        .login-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
        .login-brand img { width: 42px; height: 42px; border-radius: 10px; object-fit: cover; }
        .login-brand b { font-size: 16px; color: #1a1f2e; display: block; }
        .login-brand span { font-size: 11px; color: #9aa1b3; letter-spacing: .12em; text-transform: uppercase; }
        .login-card h1 { font-size: 24px; margin: 18px 0 4px; color: #1a1f2e; }
        .login-card p.sub { color: #7b8194; font-size: 13px; margin: 0 0 18px; }
        .login-card label { display: grid; gap: 6px; font-size: 12px; color: #7b8194; margin-bottom: 12px; }
        .login-card input { padding: 11px 13px; border: 1px solid #dfe3ee; border-radius: 10px; font-size: 14px; }
        .login-card input:focus { outline: 2px solid #c7d7ff; border-color: #2f6fed; }
        .login-card button { width: 100%; padding: 12px; border: 0; border-radius: 10px; background: #2f6fed; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; }
        .login-card button:hover { background: #2459c8; }
        .login-error { background: #fdecea; color: #a61b12; border: 1px solid #f6c6c2; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 14px; }
        .login-hint { margin-top: 14px; font-size: 12px; color: #9aa1b3; text-align: center; }
        .login-hint code { background: #eef2ff; padding: 1px 6px; border-radius: 4px; color: #2f6fed; }
        .back { display: block; text-align: center; margin-top: 16px; color: #7b8194; font-size: 12px; text-decoration: none; }
        .back:hover { color: #2f6fed; }
    </style>
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <div class="login-brand">
            <img src="изображения/logo.jpg" alt="Фаворит">
            <div><span>СМП</span><b>Фаворит Диван</b></div>
        </div>
        <h1>Вход в аналитику</h1>
        <p class="sub">Доступ к формам ввода и управлению данными</p>
        <?php if ($error !== ''): ?><div class="login-error"><?= an_e($error) ?></div><?php endif; ?>
        <form method="post">
            <label>Логин<input type="text" name="login" required autofocus value="<?= an_e($_POST['login'] ?? '') ?>"></label>
            <label>Пароль<input type="password" name="password" required></label>
            <button type="submit">Войти</button>
        </form>
        <p class="login-hint">Демо-доступ: <code>manager</code> / <code>manager123</code></p>
        <a class="back" href="analytics.php">← Вернуться к дашборду</a>
    </div>
</div>
</body>
</html>
