document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const profileBtn = document.getElementById('profileBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    const notifBtn = document.getElementById('notificationsBtn');
    const notifDropdown = document.getElementById('notifDropdown');

    // 1. Клик по профилю
    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
            if(notifDropdown) notifDropdown.classList.remove('show');
            if(sidebar) sidebar.classList.remove('force-open'); // Прячем главное меню
        });
    }

    // 2. Клик по уведомлениям
    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('show');
            if(profileDropdown) profileDropdown.classList.remove('show');
            if(sidebar) sidebar.classList.remove('force-open'); // Прячем главное меню
        });
    }

    // 3. Закрытие при клике в любом другом месте
    document.addEventListener('click', function(e) {
        if (profileDropdown && !profileBtn.contains(e.target)) profileDropdown.classList.remove('show');
        if (notifDropdown && !notifBtn.contains(e.target)) notifDropdown.classList.remove('show');
    });

    // 4. Аккордеон FAQ (Помощь)
    document.querySelectorAll('.faq-question').forEach(q => {
        q.addEventListener('click', function() {
            const item = this.parentElement;
            const answer = this.nextElementSibling;
            const icon = this.querySelector('span');
            
            if (item.classList.contains('active')) {
                item.classList.remove('active');
                answer.style.display = 'none';
                if (icon) icon.textContent = '+';
            } else {
                item.classList.add('active');
                answer.style.display = 'block';
                if (icon) icon.textContent = '−';
            }
        });
    });
});