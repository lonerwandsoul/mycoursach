// ============ МОБИЛЬНОЕ МЕНЮ ============
function toggleMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    let overlay = document.getElementById('mobileOverlay');
    sidebar.classList.toggle('mobile-open');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'mobileOverlay';
        overlay.className = 'mobile-overlay';
        overlay.onclick = toggleMobileMenu;
        document.body.appendChild(overlay);
    }
    overlay.classList.toggle('active');
    document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
}
function closeMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    if (sidebar && sidebar.classList.contains('mobile-open')) {
        sidebar.classList.remove('mobile-open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
}
window.addEventListener('resize', () => { if (window.innerWidth > 768) closeMobileMenu(); });
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.nav-item a').forEach(l => l.addEventListener('click', closeMobileMenu));
});

// ============ КАРУСЕЛЬ ============
let currentSlideIndex = 0;
function moveCarousel(dir) {
    const slides = document.querySelectorAll('.carousel-slide');
    const dots = document.querySelectorAll('.dot');
    if (!slides.length) return;
    slides[currentSlideIndex].classList.remove('active');
    dots[currentSlideIndex].classList.remove('active');
    currentSlideIndex += dir;
    if (currentSlideIndex >= slides.length) currentSlideIndex = 0;
    if (currentSlideIndex < 0) currentSlideIndex = slides.length - 1;
    slides[currentSlideIndex].classList.add('active');
    dots[currentSlideIndex].classList.add('active');
}
function currentSlide(i) {
    const slides = document.querySelectorAll('.carousel-slide');
    const dots = document.querySelectorAll('.dot');
    if (!slides.length) return;
    slides[currentSlideIndex].classList.remove('active');
    dots[currentSlideIndex].classList.remove('active');
    currentSlideIndex = i;
    slides[currentSlideIndex].classList.add('active');
    dots[currentSlideIndex].classList.add('active');
}
if (document.querySelector('.carousel')) setInterval(() => moveCarousel(1), 5000);

// ============ ДАННЫЕ ДЛЯ ГРАФИКОВ ============
const salesData = {
    week:  { labels: ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'], data: [120,135,145,140,180,200,95] },
    month: { labels: ['1 нед','2 нед','3 нед','4 нед'], data: [620,720,800,650] },
    year:  { labels: ['Янв','Фев','Мар','Апр','Май','Июн','Июл','Авг','Сен','Окт','Ноя','Дек'],
             data: [1200,1350,1450,1380,1600,1720,1850,1900,1780,1950,2100,2400] }
};

const categoryData = {
    labels: ['Модульные','Угловые','Прямые','Кресла','Столы'],
    data: [35, 28, 22, 10, 5]
};

const productivityData = {
    labels: ['Янв','Фев','Мар','Апр','Май','Июн','Июл','Авг','Сен','Окт','Ноя'],
    planned: [180,185,190,195,200,205,210,215,220,225,230],
    actual:  [175,190,188,200,198,210,215,220,218,230,235]
};

// ============ ЛИНЕЙНЫЙ ГРАФИК ============
function drawLineChart(canvasId, labels, datasets) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const width = canvas.width = canvas.offsetWidth;
    const height = canvas.height = canvas.offsetHeight;
    ctx.clearRect(0, 0, width, height);
    const padding = 60;
    const chartWidth = width - 2 * padding;
    const chartHeight = height - 2 * padding;
    let maxValue = 0;
    datasets.forEach(ds => { maxValue = Math.max(maxValue, ...ds.data); });
    maxValue = Math.ceil(maxValue * 1.1);

    ctx.strokeStyle = '#E5E5E5';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 5; i++) {
        const y = padding + (chartHeight / 5) * i;
        ctx.beginPath(); ctx.moveTo(padding, y); ctx.lineTo(width - padding, y); ctx.stroke();
        ctx.fillStyle = '#7A7A7A'; ctx.font = '12px Arial'; ctx.textAlign = 'right';
        ctx.fillText(Math.round(maxValue - (maxValue / 5) * i), padding - 10, y + 4);
    }
    datasets.forEach(ds => {
        ctx.strokeStyle = ds.color; ctx.lineWidth = 3; ctx.beginPath();
        ds.data.forEach((v, i) => {
            const x = padding + (chartWidth / (labels.length - 1)) * i;
            const y = padding + chartHeight - (v / maxValue) * chartHeight;
            if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
        });
        ctx.stroke();
        ctx.fillStyle = ds.color;
        ds.data.forEach((v, i) => {
            const x = padding + (chartWidth / (labels.length - 1)) * i;
            const y = padding + chartHeight - (v / maxValue) * chartHeight;
            ctx.beginPath(); ctx.arc(x, y, 5, 0, Math.PI * 2); ctx.fill();
        });
    });
    ctx.fillStyle = '#7A7A7A'; ctx.font = '12px Arial'; ctx.textAlign = 'center';
    labels.forEach((l, i) => {
        const x = padding + (chartWidth / (labels.length - 1)) * i;
        ctx.fillText(l, x, height - padding + 20);
    });
}

// ============ КРУГОВАЯ ДИАГРАММА ============
function drawPieChart(canvasId, labels, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const width = canvas.width = canvas.offsetWidth;
    const height = canvas.height = canvas.offsetHeight;
    ctx.clearRect(0, 0, width, height);
    const centerX = width / 2;
    const centerY = height / 2 - 20;
    const radius = Math.min(centerX, centerY) - 40;
    const colors = ['#2C2C2C', '#4A4A4A', '#6C6C6C', '#8A8A8A', '#A8A8A8'];
    const total = data.reduce((s, v) => s + v, 0);
    let currentAngle = -Math.PI / 2;

    data.forEach((value, index) => {
        const sliceAngle = (value / total) * Math.PI * 2;
        ctx.fillStyle = colors[index % colors.length];
        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
        ctx.closePath(); ctx.fill();
        const middleAngle = currentAngle + sliceAngle / 2;
        const lx = centerX + Math.cos(middleAngle) * (radius * 0.7);
        const ly = centerY + Math.sin(middleAngle) * (radius * 0.7);
        ctx.fillStyle = '#FFFFFF'; ctx.font = 'bold 14px Arial'; ctx.textAlign = 'center';
        ctx.fillText(value + '%', lx, ly);
        currentAngle += sliceAngle;
    });

    const legendY = height - 40;
    let legendX = 40;
    labels.forEach((label, index) => {
        ctx.fillStyle = colors[index % colors.length];
        ctx.fillRect(legendX, legendY, 15, 15);
        ctx.fillStyle = '#2C2C2C'; ctx.font = '12px Arial'; ctx.textAlign = 'left';
        ctx.fillText(label, legendX + 20, legendY + 12);
        legendX += ctx.measureText(label).width + 40;
    });
}

// ============ БАРНЫЙ ГРАФИК ============
function drawBarChart(canvasId, labels, datasets) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const width = canvas.width = canvas.offsetWidth;
    const height = canvas.height = canvas.offsetHeight;
    ctx.clearRect(0, 0, width, height);
    const padding = 60;
    const chartWidth = width - 2 * padding;
    const chartHeight = height - 2 * padding;
    let maxValue = 0;
    datasets.forEach(ds => { maxValue = Math.max(maxValue, ...ds.data); });
    maxValue = Math.ceil(maxValue * 1.1);

    ctx.strokeStyle = '#E5E5E5';
    for (let i = 0; i <= 5; i++) {
        const y = padding + (chartHeight / 5) * i;
        ctx.beginPath(); ctx.moveTo(padding, y); ctx.lineTo(width - padding, y); ctx.stroke();
        ctx.fillStyle = '#7A7A7A'; ctx.font = '12px Arial'; ctx.textAlign = 'right';
        ctx.fillText(Math.round(maxValue - (maxValue / 5) * i), padding - 10, y + 4);
    }
    const barWidth = chartWidth / labels.length / datasets.length * 0.8;
    const groupWidth = chartWidth / labels.length;
    labels.forEach((label, labelIndex) => {
        datasets.forEach((ds, dsIndex) => {
            const value = ds.data[labelIndex];
            const barHeight = (value / maxValue) * chartHeight;
            const x = padding + groupWidth * labelIndex + barWidth * dsIndex + (groupWidth - barWidth * datasets.length) / 2;
            const y = padding + chartHeight - barHeight;
            ctx.fillStyle = ds.color;
            ctx.fillRect(x, y, barWidth, barHeight);
        });
        ctx.fillStyle = '#7A7A7A'; ctx.font = '12px Arial'; ctx.textAlign = 'center';
        const labelX = padding + groupWidth * labelIndex + groupWidth / 2;
        ctx.fillText(label, labelX, height - padding + 20);
    });
    let legendX = padding;
    datasets.forEach(ds => {
        ctx.fillStyle = ds.color;
        ctx.fillRect(legendX, 20, 15, 15);
        ctx.fillStyle = '#2C2C2C'; ctx.font = '14px Arial'; ctx.textAlign = 'left';
        ctx.fillText(ds.label, legendX + 20, 32);
        legendX += ctx.measureText(ds.label).width + 50;
    });
}

// ============ ИНИЦИАЛИЗАЦИЯ ГРАФИКОВ ============
function initCharts() {
    if (document.getElementById('salesChart')) updateSalesChart();
    if (document.getElementById('categoryChart')) drawPieChart('categoryChart', categoryData.labels, categoryData.data);
    if (document.getElementById('productivityChart')) {
        drawBarChart('productivityChart', productivityData.labels, [
            { label: 'Плановые показатели', data: productivityData.planned, color: '#6C6C6C' },
            { label: 'Фактические показатели', data: productivityData.actual, color: '#2C2C2C' }
        ]);
    }
}

function updateSalesChart() {
    const sel = document.getElementById('sales-period');
    if (!sel) return;
    const d = salesData[sel.value];
    drawLineChart('salesChart', d.labels, [{ label: 'Продажи', data: d.data, color: '#2C2C2C' }]);
}

// ============ ФИЛЬТР ПЕРИОДА (кнопки) ============
function changePeriod(period, evt) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    if (evt && evt.target) evt.target.classList.add('active');
    updateMetrics(period);
}

function updateMetrics(period) {
    const metrics = {
        week:  { production: '580K ₽', orders: 38,  average: '15 300 ₽', efficiency: '92%' },
        month: { production: '2.4M ₽', orders: 156, average: '15 400 ₽', efficiency: '94%' },
        year:  { production: '28.5M ₽', orders: 1850, average: '15 400 ₽', efficiency: '93%' }
    };
    const d = metrics[period] || metrics.month;
    const p = document.getElementById('production-value');
    const o = document.getElementById('orders-value');
    const a = document.getElementById('average-value');
    const e = document.getElementById('efficiency-value');
    if (p) p.textContent = d.production;
    if (o) o.textContent = d.orders;
    if (a) a.textContent = d.average;
    if (e) e.textContent = d.efficiency;
}

// ============ ЗАПУСК ============
window.addEventListener('load', () => {
    initCharts();
});

window.addEventListener('resize', () => {
    initCharts();
});