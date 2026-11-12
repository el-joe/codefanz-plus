/**
 * A13 charts: <canvas data-analytics-chart="id-of-json-script">. Chart.js is loaded on demand only on pages with a
 * chart. The data comes from a JSON <script> rendered by the server (cached snapshots only); the table next to each
 * chart remains the accessible source of the same numbers.
 */
export function init() {
    const canvases = document.querySelectorAll('canvas[data-analytics-chart]');

    if (canvases.length === 0) {
        return;
    }

    import('chart.js/auto').then(({ default: Chart }) => {
        const styles = getComputedStyle(document.documentElement);
        const color = (name, fallback) => styles.getPropertyValue(name).trim() || fallback;
        const isRtl = document.documentElement.dir === 'rtl';

        canvases.forEach((canvas) => {
            const source = document.getElementById(canvas.dataset.analyticsChart);

            if (!source) {
                return;
            }

            const data = JSON.parse(source.textContent);
            const brand = color('--color-brand', '#6e56ff');
            const grid = color('--color-line-subtle', '#e5e7eb');
            const text = color('--color-fg-secondary', '#6b7280');

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{ label: data.label, data: data.series, backgroundColor: brand, borderRadius: 4, maxBarThickness: 28 }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : undefined,
                    plugins: { legend: { display: false }, tooltip: { rtl: isRtl } },
                    scales: {
                        x: { reverse: isRtl, grid: { display: false }, ticks: { color: text, maxRotation: 0, autoSkip: true } },
                        y: { position: isRtl ? 'right' : 'left', beginAtZero: true, grid: { color: grid }, ticks: { color: text, precision: 0 } },
                    },
                },
            });
        });
    });
}
