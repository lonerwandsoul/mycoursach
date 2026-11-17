<?php
$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM vacancies";
if ($search !== '') {
    $safe = mysqli_real_escape_string($link, $search);
    $sql .= " WHERE title LIKE '%$safe%' OR description LIKE '%$safe%'";
}
$res = mysqli_query($link, $sql);
?>
<section class="page-view active" id="page-vacancies">
    <div class="page">
        <h2>Актуальные вакансии</h2>
        <div style="margin-bottom: 20px; border-radius: 8px; overflow: hidden; height: 400px;">
            <img src="assets/photo2.webp" style="width: 100%; height: 100%; object-fit: cover;" alt="Работа на предприятии">
        </div>
        
        <form class="search-form" method="get" action="index.php" style="margin-bottom: 2rem; display: flex; gap: 10px;">
            <input type="hidden" name="page" value="vacancies">
            <input type="text" name="q" placeholder="Введите название должности или ключевое слово..." value="<?= htmlspecialchars($search) ?>" style="flex: 1; padding: 12px; border: 2px solid #ddd; border-radius: 6px;">
            <button type="submit" class="btn" style="padding: 12px 30px;">Найти</button>
        </form>
        
        <div class="vacancies-list">
            <?php if(mysqli_num_rows($res) === 0): ?><p>По вашему запросу ничего не найдено.</p><?php endif; ?>
            <?php while ($v = mysqli_fetch_assoc($res)): ?>
            <div class="vacancy-item" style="padding: 20px; border-left: 4px solid var(--primary-blue);">
                <div class="vacancy-header" style="margin-bottom: 15px;">
                    <h3 class="vacancy-title" style="font-size: 1.4rem;"><?= htmlspecialchars($v['title']) ?></h3>
                    <span class="security-level"><?= htmlspecialchars($v['security_level']) ?></span>
                </div>
                <div class="vacancy-meta" style="font-size: 1rem; margin-bottom: 15px; color: #555;">
                    <strong>Отдел:</strong> <?= htmlspecialchars($v['department']) ?> &nbsp;|&nbsp; 
                    <strong>Опыт:</strong> от <?= $v['experience_from'] ?> лет &nbsp;|&nbsp; 
                    <strong>Зарплата:</strong> <span style="color: var(--success); font-weight: bold;">от <?= number_format($v['salary_from'], 0, '', ' ') ?> руб.</span>
                </div>
                <p style="margin-bottom: 20px; line-height: 1.6;"><?= htmlspecialchars($v['description']) ?></p>
                <div class="vacancy-actions">
                    <a class="btn" href="index.php?page=vacancy&id=<?= $v['id'] ?>">Подробнее</a>
                    <?php if ($user): ?>
                    <form method="post" action="actions.php" style="display:inline;">
                        <input type="hidden" name="action" value="add_favorite">
                        <input type="hidden" name="vacancy_id" value="<?= $v['id'] ?>">
                        <button type="submit" class="btn btn-outline" style="margin-left: 10px;">В избранное</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>