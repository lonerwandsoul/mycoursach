<?php
if (!is_logged_in()) { echo "<div class='page'><p>Войдите, чтобы просмотреть избранное.</p></div>"; return; }
$uid = $user['id'];
$sql = "SELECT v.* FROM favorites f JOIN vacancies v ON v.id = f.vacancy_id WHERE f.user_id = $uid";
$res = mysqli_query($link, $sql);
?>
<section class="page-view active" id="page-favorites">
    <div class="page">
        <h2>Избранные вакансии</h2>
        <div class="vacancies-list">
            <?php if(mysqli_num_rows($res) === 0): ?>
                <p>Вы пока не добавили ни одной вакансии в избранное.</p>
            <?php endif; ?>
            <?php while ($v = mysqli_fetch_assoc($res)): ?>
            <div class="vacancy-item">
                <div class="vacancy-header">
                    <h3 class="vacancy-title"><?= htmlspecialchars($v['title']) ?></h3>
                    <span class="security-level"><?= htmlspecialchars($v['security_level']) ?></span>
                </div>
                <div class="vacancy-meta">Отдел: <?= htmlspecialchars($v['department']) ?> | Зарплата: от <?= number_format($v['salary_from'], 0, '', ' ') ?> руб.</div>
                <div class="vacancy-actions" style="margin-top: 15px;">
                    <a class="btn" href="index.php?page=vacancy&id=<?= $v['id'] ?>">Подробнее</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>