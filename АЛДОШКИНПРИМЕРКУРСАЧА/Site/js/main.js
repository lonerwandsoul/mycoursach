document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const trigger = document.querySelector('.sidebar-trigger');
    const profileBtn = document.getElementById('profileBtn');
    const notifBtn = document.getElementById('notificationsBtn');

    if (sidebar && trigger) {
        // Открываем меню при наведении
        trigger.addEventListener('mouseenter', () => {
            // Проверяем, не открыто ли меню профиля или уведомлений
            const isProfileOpen = document.getElementById('profileDropdown').classList.contains('show');
            const isNotifOpen = document.getElementById('notifDropdown').classList.contains('show');
            
            if (!isProfileOpen && !isNotifOpen) {
                sidebar.classList.add('force-open');
            }
        });

        sidebar.addEventListener('mouseenter', () => {
            sidebar.classList.add('force-open');
        });

        sidebar.addEventListener('mouseleave', () => {
            sidebar.classList.remove('force-open');
        });

        // Фиксация после перехода по ссылке
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('page')) {
            sidebar.classList.add('force-open');
        }
    }

    // Блокируем выезд меню, если кликаем по профилю или уведомлениям
    if (profileBtn) {
        profileBtn.addEventListener('mouseenter', () => {
            if(sidebar) sidebar.classList.remove('force-open');
        });
    }
    if (notifBtn) {
        notifBtn.addEventListener('mouseenter', () => {
            if(sidebar) sidebar.classList.remove('force-open');
        });
    }
});
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const trigger = document.querySelector('.sidebar-trigger');
    
    if (sidebar && trigger) {
        trigger.addEventListener('mouseenter', () => {
            sidebar.classList.add('force-open');
        });
        sidebar.addEventListener('mouseenter', () => {
            sidebar.classList.add('force-open');
        });
        sidebar.addEventListener('mouseleave', () => {
            sidebar.classList.remove('force-open');
        });
    }
});