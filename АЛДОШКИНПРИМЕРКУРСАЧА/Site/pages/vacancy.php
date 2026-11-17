<?php
$id = (int)($_GET['id'] ?? 0);
$res = mysqli_query($link, "SELECT * FROM vacancies WHERE id = $id");
$v = mysqli_fetch_assoc($res);
if (!$v) { echo '<div class="page"><p>Вакансия не найдена.</p></div>'; return; }
?>
<section class="page-view active" id="page-vacancy-detail">
    <div class="page">
        <div class="vacancy-detail-header">
            <h1 class="vacancy-detail-title"><?= htmlspecialchars($v['title']) ?></h1>
            <span class="security-level">Секретность: <?= htmlspecialchars($v['security_level']) ?></span>
        </div>
        <div class="vacancy-meta-detail">
            <div class="meta-item"><span class="meta-label">Отдел</span><span class="meta-value"><?= htmlspecialchars($v['department']) ?></span></div>
            <div class="meta-item"><span class="meta-label">Опыт работы</span><span class="meta-value">от <?= $v['experience_from'] ?> лет</span></div>
            <div class="meta-item"><span class="meta-label">Зарплата</span><span class="meta-value">от <?= number_format($v['salary_from'], 0, '', ' ') ?> руб.</span></div>
        </div>
        <div class="vacancy-section">
            <h3>Описание вакансии</h3>
            <p><?= nl2br(htmlspecialchars($v['description'])) ?></p>
        </div>
        <?php if ($user): ?>
        <div class="vacancy-actions-detail">
            <form method="post" action="actions.php">
                <input type="hidden" name="action" value="apply">
                <input type="hidden" name="vacancy_id" value="<?= $v['id'] ?>">
                <button type="submit" class="btn">Откликнуться на вакансию</button>
            </form>
            <a class="btn btn-outline" href="index.php?page=vacancies">← Назад к поиску</a>
        </div>
        <?php endif; ?>
    </div>
</section>