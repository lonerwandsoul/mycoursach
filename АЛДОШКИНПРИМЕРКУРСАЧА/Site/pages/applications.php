<?php
$sql = "SELECT a.*, v.title, v.department FROM applications a JOIN vacancies v ON v.id = a.vacancy_id WHERE a.user_id = $user[id] ORDER BY a.created_at DESC";
$res = mysqli_query($link, $sql);
?>
<section class="page-view active" id="page-applications">
    <div class="page">
        <h2>Мои отклики на вакансии</h2>
        <div class="applications-list">
            <?php if (mysqli_num_rows($res) === 0): ?><p>Вы пока не откликались на вакансии.</p><?php endif; ?>
            <?php while ($a = mysqli_fetch_assoc($res)): ?>
            <div class="application-item">
                <div class="application-header">
                    <h4><?= htmlspecialchars($a['title']) ?></h4>
                    <span class="application-status status-pending"><?= htmlspecialchars($a['status']) ?></span>
                </div>
                <p>Отдел: <?= htmlspecialchars($a['department']) ?></p>
                <p>Дата отклика: <?= date('d.m.Y', strtotime($a['created_at'])) ?></p>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>