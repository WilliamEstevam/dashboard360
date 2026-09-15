document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.DashboardDashboardData === 'undefined') return;
    const data = window.DashboardDashboardData;

    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? '#1e293b' : '#f1f5f9';

    const ctxLine = document.getElementById('mockLineChart');
    if (ctxLine) {
        const gradient = ctxLine.getContext('2d').createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(79, 70, 229, 0.2)');
        gradient.addColorStop(1, 'rgba(79, 70, 229, 0)');

        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: data.chartLabels,
                datasets: [
                    {
                        label: 'Retenção Atingida',
                        data: data.chartRetencao,
                        borderColor: '#4f46e5',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: isDark ? '#0f172a' : '#ffffff',
                        pointBorderColor: '#4f46e5'
                    },
                    {
                        label: 'Meta',
                        data: data.chartMeta,
                        borderColor: '#cbd5e1',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { min: 40, max: 100, grid: { color: gridColor }, ticks: { color: textColor } },
                    x: { grid: { display: false }, ticks: { color: textColor } }
                }
            }
        });
    }

    const ctxDoughnut = document.getElementById('mockDoughnutChart');
    if (ctxDoughnut) {
        new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: data.chartStatusKeys,
                datasets: [{
                    data: data.chartStatusValues,
                    backgroundColor: ['#4f46e5', '#10b981', '#06b6d4', '#f43f5e'],
                    borderWidth: 0, hoverOffset: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '75%',
                plugins: { legend: { display: false } }
            }
        });
    }
});