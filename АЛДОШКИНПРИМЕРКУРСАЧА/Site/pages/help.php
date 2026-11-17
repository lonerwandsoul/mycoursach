<section class="page-view active" id="page-help">
    <div class="page">
        <h2>Помощь и поддержка</h2> <!-- Убрал смайлики отсюда -->

        <?php if (isset($_GET['sent'])): ?>
            <div class="success-message show">Ваше обращение успешно отправлено! Мы свяжемся с вами в ближайшее время.</div>
        <?php endif; ?>

        <div id="faq" class="faq-section">
            <h3>Часто задаваемые вопросы</h3>
            
            <!-- ВСТАВЛЯЕШЬ НОВЫЙ КОД СЮДА -->
            <div class="faq-item card-hover" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px;">
                <div class="faq-question" style="padding: 1rem; cursor: pointer; display: flex; justify-content: space-between; font-weight: bold;">
                    Как зарегистрироваться в системе? <span>+</span>
                </div>
                <div class="faq-answer" style="display: none; padding: 1rem; border-top: 1px solid #eee;">
                    <p>Для регистрации перейдите в раздел <a href="index.php?page=register">Регистрация</a> (или нажмите на кнопку "Войти" в шапке сайта), заполните обязательные поля и нажмите кнопку "Зарегистрироваться".</p>
                </div>
            </div>

            <div class="faq-item card-hover" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px;">
                <div class="faq-question" style="padding: 1rem; cursor: pointer; display: flex; justify-content: space-between; font-weight: bold;">
                    Как откликнуться на вакансию? <span>+</span>
                </div>
                <div class="faq-answer" style="display: none; padding: 1rem; border-top: 1px solid #eee;">
                    <p>Перейдите в раздел <a href="index.php?page=vacancies">Поиск вакансий</a>, выберите подходящую должность, нажмите "Подробнее", а затем "Откликнуться".</p>
                </div>
            </div>
            <!-- КОНЕЦ НОВОГО КОДА -->

        </div>

        <div class="contact-form card-hover" style="margin-top: 3rem;">
            <h3>Форма обращения в техническую поддержку</h3>
            <p style="margin-bottom: 1.5rem; color: #666;">Опишите вашу проблему, и наши специалисты помогут вам.</p>
            
            <?php if ($user): ?>
            <form method="post" action="actions.php">
                <input type="hidden" name="action" value="support_request">
                <div class="form-group"><label>Ваше имя</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>" required></div>
                <div class="form-group"><label>Email для ответа</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required></div>
                <div class="form-group"><label>Описание проблемы</label><textarea name="message" class="form-control" rows="5" required placeholder="Подробно опишите, что случилось..."></textarea></div>
                <button type="submit" class="btn">Отправить обращение</button>
            </form>
            <?php else: ?>
                <p>Для отправки обращения необходимо <a href="index.php?page=login">авторизоваться</a>.</p>
            <?php endif; ?>
        </div>
    </div>
</section>