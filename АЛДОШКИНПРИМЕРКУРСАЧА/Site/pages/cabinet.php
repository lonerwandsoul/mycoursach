<?php
if (!isset($user) || !$user) { echo "<div class='page'><p>Пожалуйста, авторизуйтесь.</p></div>"; return; }
?>
<section class="page-view active" id="page-cabinet">
    <div class="page">
        <h2>Личный кабинет кандидата</h2>
        <?php if (isset($_GET['updated'])): ?><div class="success-message show">Профиль успешно обновлен</div><?php endif; ?>
        
        <div class="profile-tabs">
            <button class="tab active">Профиль</button>
            <a href="index.php?page=applications" class="tab" style="text-decoration:none;">Мои отклики</a>
            <a href="index.php?page=resume" class="tab" style="text-decoration:none;">Резюме</a>
        </div>
        
        <div class="profile-info">
            <?php if ($userAvatar): ?>
                <img src="<?= $userAvatar ?>" class="profile-avatar" style="object-fit: cover;">
            <?php else: ?>
                <div class="profile-avatar"><?= $userInitials ?></div>
            <?php endif; ?>
            <div>
                <h3><?= htmlspecialchars($user['last_name'] . ' ' . $user['first_name'] . ' ' . $user['middle_name']) ?></h3>
                <p>Email: <?= htmlspecialchars($user['email']) ?></p>
                <p>Телефон: <?= htmlspecialchars($user['phone']) ?></p>
            </div>
        </div>

        <h3>Редактировать профиль</h3>
        <form action="actions.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-group">
                <label>Фотография профиля</label>
                <input type="file" name="avatar" class="form-control" accept="image/*">
            </div>
            <div class="form-row">
                <div class="form-group"><label>Фамилия</label><input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>" required></div>
                <div class="form-group"><label>Имя</label><input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>" required></div>
                <div class="form-group"><label>Отчество</label><input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($user['middle_name']) ?>"></div>
            </div>
            <button type="submit" class="btn">Сохранить изменения</button>
        </form>
    </div>
</section>