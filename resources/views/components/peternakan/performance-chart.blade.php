{{--
    Production Efficiency Chart (Reusable)

    Dual-axis line chart: HDP% (left axis) vs FCR (right axis).
    Uses Chart.js with time range toggle (30 Days / 90 Days).

    Props:
    - $chartId  : string — Canvas ID
    - $labels   : array  — X-axis labels
    - $hdpData  : array  — HDP % values
    - $fcrData  : array  — FCR values
    - $title    : string — Chart title
    - $subtitle : string — Chart subtitle
--}}

@props([
    'chartId'  => 'efficiencyChart',
    'labels'   => [],
    'hdpData'  => [],
    'fcrData'  => [],
    'title'    => 'Production Efficiency Trends',
    'subtitle' => 'Comparing HDP vs FCR over last 30 days',
])

<div
    x-data="{
        range: '30d',
        _chart: null,
        labels: @js($labels),
        hdpData: @js($hdpData),
        fcrData: @js($fcrData),

        init() {
            this.$nextTick(() => this.render());
        },

        render() {
            if (this._chart) this._chart.destroy();
            const canvas = this.$refs.canvas;
            if (!canvas) return;

            this._chart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: this.labels,
                    datasets: [
                        {
                            label: 'HDP %',
                            data: this.hdpData,
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
                            data: this.fcrData,
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
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: { size: 11, family: 'Inter' },
                            },
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15,23,42,0.9)',
                            padding: 10,
                            cornerRadius: 8,
                            titleFont: { family: 'Inter', size: 12 },
                            bodyFont: { family: 'Inter', size: 11 },
                        },
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: false,
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: { font: { size: 10, family: 'Inter' }, callback: v => v + '%' },
                        },
                        y1: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: false,
                            grid: { drawOnChartArea: false },
                            ticks: { font: { size: 10, family: 'Inter' } },
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 10, family: 'Inter' } },
                        },
                    },
                },
            });
        },
    }"
    {{ $attributes->merge(['class' => 'bg-white border border-gray-100 rounded-xl p-5 shadow-sm']) }}
>
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-base font-semibold text-gray-800">{{ $title }}</h3>
            <p class="text-xs text-gray-400">{{ $subtitle }}</p>
        </div>
        <div class="flex items-center bg-gray-100 rounded-lg p-0.5">
            <button
                @click="range = '30d'"
                :class="range === '30d' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                class="px-3 py-1.5 text-xs font-medium rounded-md transition-all"
            >30 Days</button>
            <button
                @click="range = '90d'"
                :class="range === '90d' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                class="px-3 py-1.5 text-xs font-medium rounded-md transition-all"
            >90 Days</button>
        </div>
    </div>
    <div class="h-52">
        <canvas x-ref="canvas"></canvas>
    </div>
</div>
