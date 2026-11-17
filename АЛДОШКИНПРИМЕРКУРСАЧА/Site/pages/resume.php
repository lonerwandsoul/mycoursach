<?php
$res = mysqli_query($link, "SELECT * FROM resumes WHERE user_id = $user[id]");
$r = mysqli_fetch_assoc($res);
?>
<section class="page-view active" id="page-resume">
    <div class="page">
        <h2>Создание резюме</h2>
        <?php if (isset($_GET['saved'])): ?>
            <div class="success-message show">Резюме сохранено</div>
        <?php endif; ?>
        <form method="post" action="actions.php">
            <input type="hidden" name="action" value="save_resume">
            <div class="form-group"><label>Желаемая должность</label><input type="text" name="position" class="form-control" required value="<?= htmlspecialchars($r['position'] ?? '') ?>"></div>
            <div class="form-group"><label>Опыт работы</label><textarea name="experience" class="form-control"><?= htmlspecialchars($r['experience'] ?? '') ?></textarea></div>
            <div class="form-group"><label>Образование</label><textarea name="education" class="form-control"><?= htmlspecialchars($r['education'] ?? '') ?></textarea></div>
            <div class="form-group"><label>Ключевые навыки</label><textarea name="skills" class="form-control"><?= htmlspecialchars($r['skills'] ?? '') ?></textarea></div>
            <button type="submit" class="btn">Сохранить резюме</button>
        </form>
    </div>
</section>