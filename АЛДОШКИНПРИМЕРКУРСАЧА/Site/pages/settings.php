<?php
if (!isset($user) || !$user) { echo "<div class='page'><p>Пожалуйста, авторизуйтесь.</p></div>"; return; }
?>
<section class="page-view active" id="page-settings">
    <div class="page">
        <h2>Настройки профиля</h2>
        <form>
            <div class="settings-section">
                <h3>Основные настройки</h3>
                <div class="form-group"><label>Email для уведомлений</label><input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled></div>
                <div class="form-group"><label>Телефон</label><input type="tel" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" disabled></div>
            </div>
            <div class="settings-section">
                <h3>Безопасность</h3>
                <p style="color:var(--medium-gray);">Смена пароля доступна в следующей версии системы.</p>
            </div>
        </form>
    </div>
</section>