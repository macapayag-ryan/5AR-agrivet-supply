document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('salesChart');
    if (!canvas || !window.Chart) {
        return;
    }
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: JSON.parse(canvas.dataset.labels),
            datasets: [{
                label: 'Sales',
                data: JSON.parse(canvas.dataset.values),
                backgroundColor: '#3d8b50',
                hoverBackgroundColor: '#245c3a',
                borderRadius: 4,
                maxBarThickness: 34
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a4529',
                    padding: 10,
                    displayColors: false,
                    callbacks: {
                        label: (ctx) => 'Sales: ' + ctx.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 12, color: '#26332b' }, border: { color: '#cfd8cd' } },
                y: { beginAtZero: true, ticks: { color: '#758176' }, grid: { color: '#e4e9e2' }, border: { display: false } }
            }
        }
    });
});