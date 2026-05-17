@props([
    'chartId'  => 'efficiencyChart',
    'labels'   => [],
    'hdpData'  => [],
    'fcrData'  => [],
    'chartDataByRange' => [],
    'defaultRange' => '30d',
    'title'    => 'Production Efficiency Trends',
    'subtitle' => 'Comparing HDP vs FCR',
])

<div
    x-data="{
        range: @js($defaultRange),
        _chart: null,
        datasets: @js($chartDataByRange),

        get currentData() {
            return this.datasets[this.range] ?? { labels: @js($labels), hdp: @js($hdpData), fcr: @js($fcrData) };
        },

        get subtitle() {
            return {
                '30d': 'HDP vs FCR — 30 hari terakhir',
                '90d': 'HDP vs FCR — 90 hari terakhir',
                'ytd': 'HDP vs FCR — year to date',
            }[this.range] ?? 'Comparing HDP vs FCR';
        },

        init() {
            this.$watch('range', () => this.$nextTick(() => this.render()));
            this.$nextTick(() => this.render());
        },

        render() {
            if (this._chart) this._chart.destroy();
            const canvas = this.$refs.canvas;
            if (!canvas) return;

            const data = this.currentData;

            this._chart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: data.labels ?? [],
                    datasets: [
                        {
                            label: 'HDP %',
                            data: data.hdp ?? [],
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59,130,246,0.08)',
                            yAxisID: 'y',
                            tension: 0.4,
                            pointRadius: 2,
                            borderWidth: 2,
                            fill: true,
                        },
                        {
                            label: 'FCR',
                            data: data.fcr ?? [],
                            borderColor: '#EF4444',
                            backgroundColor: 'rgba(239,68,68,0.08)',
                            yAxisID: 'y1',
                            tension: 0.4,
                            pointRadius: 2,
                            borderWidth: 2,
                            fill: true,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: { usePointStyle: true, padding: 20, font: { size: 11, family: 'Inter' } },
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15,23,42,0.9)',
                            padding: 10,
                            cornerRadius: 8,
                        },
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: false,
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: { font: { size: 10 }, callback: v => v + '%' },
                        },
                        y1: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: false,
                            grid: { drawOnChartArea: false },
                            ticks: { font: { size: 10 } },
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 10 }, maxTicksLimit: 12 },
                        },
                    },
                },
            });
        },
    }"
    {{ $attributes->merge(['class' => 'bg-white border border-gray-100 rounded-xl p-5 shadow-sm flex flex-col h-full']) }}
>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4 shrink-0">
        <div>
            <h3 class="text-base font-semibold text-gray-800">{{ $title }}</h3>
            <p class="text-xs text-gray-400" x-text="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="flex items-center bg-gray-100 rounded-lg p-0.5">
            <button @click="range = '30d'" :class="range === '30d' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'" class="px-3 py-1.5 text-xs font-medium rounded-md transition-all">30 Hari</button>
            <button @click="range = '90d'" :class="range === '90d' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'" class="px-3 py-1.5 text-xs font-medium rounded-md transition-all">90 Hari</button>
            <button @click="range = 'ytd'" :class="range === 'ytd' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'" class="px-3 py-1.5 text-xs font-medium rounded-md transition-all">YTD</button>
        </div>
    </div>
    <div class="flex-1 relative min-h-[200px]">
        <canvas x-ref="canvas"></canvas>
    </div>
</div>
