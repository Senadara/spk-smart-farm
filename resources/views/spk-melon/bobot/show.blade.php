@extends('layouts.app')

@section('content')
    <div x-data="{ openDetail: false }">
        <!-- Header & Breadcrumb -->
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <nav class="text-sm font-medium text-gray-500 mb-1">
                    <ol class="list-none p-0 inline-flex">
                        <li class="flex items-center">
                            <a href="{{ route('spk-melon.sesi-penilaian.index') }}" class="hover:text-emerald-600">Sesi
                                Penilaian</a>
                            <i data-lucide="chevron-right" class="w-4 h-4 mx-2"></i>
                        </li>
                        <li class="flex items-center">
                            <a href="{{ route('spk-melon.sesi-penilaian.show', $sesi->id) }}"
                                class="hover:text-emerald-600">{{ $sesi->namaSesi }}</a>
                            <i data-lucide="chevron-right" class="w-4 h-4 mx-2"></i>
                        </li>
                        <li class="flex items-center text-gray-700">Bobot Kriteria Fuzzy AHP</li>
                    </ol>
                </nav>
                <h2 class="text-2xl font-bold text-gray-800">
                    Kalkulasi Bobot Kriteria
                </h2>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('spk-melon.sesi-penilaian.show', $sesi->id) }}"
                    class="flex items-center gap-2 px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-xl text-sm min-h-[44px] shadow-sm transition-all">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    Kembali
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="mb-6 p-4 rounded-md bg-emerald-50 border border-emerald-200 flex items-start">
                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600 mt-0.5 mr-3"></i>
                <div class="text-emerald-800 text-sm font-medium">{{ session('success') }}</div>
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 p-4 rounded-md bg-red-50 border border-red-200 flex items-start">
                <i data-lucide="alert-circle" class="w-5 h-5 text-red-600 mt-0.5 mr-3"></i>
                <div class="text-red-800 text-sm font-medium">{{ session('error') }}</div>
            </div>
        @endif

        <!-- Info Banner -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Bobot Kriteria Terhitung</h3>
                @if ($isFromDb)
                    <p class="text-sm text-gray-500">Menampilkan bobot yang tersimpan di database.</p>
                @else
                    <p class="text-sm text-emerald-600 font-medium">Bobot baru saja dihitung berdasarkan data perbandingan.
                    </p>
                @endif
            </div>
            @if (!$isReadOnly)
                <div x-data="{ showConfirm: false }">
                    <button @click="showConfirm = true"
                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-md shadow-sm flex items-center transition-colors">
                        <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i> Hitung Ulang
                    </button>
                    <!-- Modal Confirm -->
                    <div x-show="showConfirm" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                            <div x-show="showConfirm" x-transition.opacity
                                class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                                @click="showConfirm = false"></div>
                            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                            <div x-show="showConfirm" x-transition.scale
                                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                    <div class="sm:flex sm:items-start">
                                        <div
                                            class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                                            <i data-lucide="alert-triangle" class="h-6 w-6 text-yellow-600"></i>
                                        </div>
                                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                            <h3 class="text-lg leading-6 font-medium text-gray-900">Hitung Ulang Bobot?</h3>
                                            <div class="mt-2">
                                                <p class="text-sm text-gray-500">Yakin ingin menghitung ulang bobot
                                                    kriteria? Data lama akan ditandai sebagai history (soft delete) dan
                                                    bobot baru akan di-generate.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                    <form
                                        action="{{ route('spk-melon.sesi-penilaian.bobot-kriteria.calculate', $sesi->id) }}"
                                        method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 sm:ml-3 sm:w-auto sm:text-sm">
                                            Ya, Hitung Ulang
                                        </button>
                                    </form>
                                    <button @click="showConfirm = false" type="button"
                                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                        Batal
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Layout: Chart (Left) + Result Table (Right) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Result Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
                <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                    <h3 class="text-base font-semibold text-gray-800">Tabel Bobot Akhir (Crisp)</h3>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kriteria</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-800 uppercase">Bobot (%)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($result['kriteriaList'] as $i => $kriteria)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $kriteria->kode }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $kriteria->nama }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        @if ($kriteria->kategori === 'produktivitas')
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-teal-100 text-teal-800">Produktivitas</span>
                                        @elseif($kriteria->kategori === 'kualitas')
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">Kualitas</span>
                                        @elseif($kriteria->kategori === 'lingkungan')
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Lingkungan</span>
                                        @else
                                            <span class="text-gray-500">{{ $kriteria->kategori }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-bold text-gray-900">
                                        {{ number_format($result['normalizedWeights'][$i] * 100, 2) }}%
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Chart -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-base font-semibold text-gray-800">Distribusi Bobot</h3>
                </div>
                <div class="p-4 flex-1" style="min-height: 300px;">
                    <canvas id="bobotChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Expandable Breakdown -->
        <details class="group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6"
            x-on:toggle="openDetail = $event.target.open">
            <summary
                class="p-4 bg-gray-50 cursor-pointer flex justify-between items-center list-none font-medium text-gray-700 hover:bg-gray-100 transition-colors">
                <div class="flex items-center">
                    <i data-lucide="calculator" class="w-5 h-5 mr-3 text-gray-500"></i>
                    <span class="text-lg font-semibold text-gray-800">Lihat Detail Perhitungan <em>Fuzzy AHP</em></span>
                </div>
                <span class="transition group-open:rotate-180">
                    <i data-lucide="chevron-down" class="w-5 h-5 text-gray-500"></i>
                </span>
            </summary>

            <div class="p-6 border-t border-gray-200 space-y-8">
                <!-- Tahap 1 -->
                <div>
                    <h4 class="text-md font-bold text-gray-800 border-l-4 border-emerald-500 pl-3 mb-4">Tahap 1: Matriks
                        Perbandingan Fuzzy</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-xs font-mono">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left">Kriteria</th>
                                    @foreach ($result['kriteriaList'] as $k)
                                        <th class="px-3 py-2 text-center">{{ $k->kode }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @for ($i = 0; $i < $result['n']; $i++)
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left">{{ $result['kriteriaList'][$i]->kode }}
                                        </th>
                                        @for ($j = 0; $j < $result['n']; $j++)
                                            @if (isset($result['fuzzyMatrix'][$i][$j]))
                                                @php $val = $result['fuzzyMatrix'][$i][$j]; @endphp
                                                <td class="px-3 py-2 text-center whitespace-nowrap">
                                                    ({{ number_format($val['l'], 4) }}; {{ number_format($val['m'], 4) }};
                                                    {{ number_format($val['u'], 4) }})
                                                </td>
                                            @else
                                                <td class="px-3 py-2 text-center">-</td>
                                            @endif
                                        @endfor
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tahap 2 -->
                <div>
                    <h4 class="text-md font-bold text-gray-800 border-l-4 border-emerald-500 pl-3 mb-4">Tahap 2: Fuzzy
                        Geometric Mean (<span class="italic font-serif">r<sub>i</sub></span>)</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Kode</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Nama Kriteria</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-500 italic">r<sub>L</sub></th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-500 italic">r<sub>M</sub></th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-500 italic">r<sub>U</sub></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($result['kriteriaList'] as $i => $k)
                                    <tr>
                                        <td class="px-4 py-2 font-medium">{{ $k->kode }}</td>
                                        <td class="px-4 py-2">{{ $k->nama }}</td>
                                        <td class="px-4 py-2 text-right font-mono">
                                            {{ isset($result['geometricMeans'][$i]) ? number_format($result['geometricMeans'][$i]['l'], 4) : '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono">
                                            {{ isset($result['geometricMeans'][$i]) ? number_format($result['geometricMeans'][$i]['m'], 4) : '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono">
                                            {{ isset($result['geometricMeans'][$i]) ? number_format($result['geometricMeans'][$i]['u'], 4) : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 font-medium border-t-2 border-gray-300">
                                <tr>
                                    <td colspan="2" class="px-4 py-2 text-left">Σ (S)</td>
                                    <td class="px-4 py-2 text-right font-mono text-emerald-700">
                                        {{ !empty($result['sumGeometricMean']) ? number_format($result['sumGeometricMean']['l'], 4) : '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono text-emerald-700">
                                        {{ !empty($result['sumGeometricMean']) ? number_format($result['sumGeometricMean']['m'], 4) : '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono text-emerald-700">
                                        {{ !empty($result['sumGeometricMean']) ? number_format($result['sumGeometricMean']['u'], 4) : '-' }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Tahap 3 -->
                <div>
                    <h4 class="text-md font-bold text-gray-800 border-l-4 border-emerald-500 pl-3 mb-2">Tahap 3: Sintesis
                        Bobot Fuzzy (<span class="italic font-serif">w<sub>i</sub></span>)</h4>
                    <p class="text-sm text-gray-600 mb-4 ml-4">Formula pembagian resiprokal fuzzy (Buckley, 1985): <code
                            class="bg-gray-100 px-1 rounded text-pink-600">w_i = (r_L / S_U, r_M / S_M, r_U / S_L)</code>
                    </p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Kode</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Nama Kriteria</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-500 italic">w<sub>L</sub></th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-500 italic">w<sub>M</sub></th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-500 italic">w<sub>U</sub></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($result['kriteriaList'] as $i => $k)
                                    <tr>
                                        <td class="px-4 py-2 font-medium">{{ $k->kode }}</td>
                                        <td class="px-4 py-2">{{ $k->nama }}</td>
                                        <td class="px-4 py-2 text-right font-mono text-blue-700">
                                            {{ isset($result['fuzzyWeights'][$i]) ? number_format($result['fuzzyWeights'][$i]['l'], 4) : '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono text-blue-700">
                                            {{ isset($result['fuzzyWeights'][$i]) ? number_format($result['fuzzyWeights'][$i]['m'], 4) : '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono text-blue-700">
                                            {{ isset($result['fuzzyWeights'][$i]) ? number_format($result['fuzzyWeights'][$i]['u'], 4) : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tahap 4 & 5 -->
                <div>
                    <h4 class="text-md font-bold text-gray-800 border-l-4 border-emerald-500 pl-3 mb-4">Tahap 4 & 5:
                        Defuzzifikasi (Center of Area) & Normalisasi Akhir</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Kode</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-500">W (CoA) = (L+M+U)/3</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-500">Normalisasi (W_final)</th>
                                    <th class="px-4 py-2 text-right font-bold text-gray-800">Persentase</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($result['kriteriaList'] as $i => $k)
                                    <tr>
                                        <td class="px-4 py-2 font-medium">{{ $k->kode }}</td>
                                        <td class="px-4 py-2 text-right font-mono text-gray-600">
                                            {{ number_format($result['crispWeights'][$i], 6) }}</td>
                                        <td class="px-4 py-2 text-right font-mono text-purple-700 font-bold">
                                            {{ number_format($result['normalizedWeights'][$i], 6) }}</td>
                                        <td class="px-4 py-2 text-right font-bold">
                                            {{ number_format($result['normalizedWeights'][$i] * 100, 2) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 font-medium border-t-2 border-gray-300">
                                <tr>
                                    <td class="px-4 py-2 text-left">Σ (Total)</td>
                                    <td class="px-4 py-2 text-right font-mono text-gray-800">
                                        {{ number_format($result['sumCrispWeights'], 6) }}</td>
                                    <td class="px-4 py-2 text-right font-mono text-emerald-700 font-bold">
                                        {{ number_format(array_sum($result['normalizedWeights']), 6) }}</td>
                                    <td class="px-4 py-2 text-right font-bold">100%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            </div>
        </details>

        <!-- Keterangan Footer -->
        <div class="bg-gray-50 rounded-xl p-5 border border-gray-200">
            <h4 class="text-sm font-bold text-gray-800 mb-2 flex items-center">
                <i data-lucide="info" class="w-4 h-4 mr-2 text-blue-600"></i> Catatan Interpretasi:
            </h4>
            <ul class="text-sm text-gray-600 list-disc pl-5 space-y-1">
                <li>Bobot akhir (W_final) menunjukkan tingkat kepentingan setiap kriteria dalam pengambilan keputusan.</li>
                <li>Hasil perhitungan di atas akan digunakan pada tahap kalkulasi algoritma SPK utama (SPK-07).</li>
                <li>Jika ada perubahan pada matriks perbandingan, perhitungan bobot harus diulangi untuk mendapatkan nilai
                    terbaru.</li>
                <li>Pastikan matriks perbandingan memiliki nilai Consistency Ratio (CR) < 0.10 sebelum bobot digunakan untuk
                        mengambil keputusan. (CR saat ini: {{ number_format($cr ?? 0, 4) }})</li>
            </ul>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Init Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Prepare Chart Data
            const kriteriaList = @json(collect($result['kriteriaList'])->map(function ($k) {
                    return $k->kode . ' - ' . $k->nama;
                }));
            const categories = @json(collect($result['kriteriaList'])->pluck('kategori'));
            const weights = @json($result['normalizedWeights']);

            // Sort data descending by weight
            let chartData = kriteriaList.map((label, i) => ({
                label: label,
                weight: weights[i],
                category: categories[i]
            })).sort((a, b) => b.weight - a.weight);

            // Map colors
            const colorMap = {
                'produktivitas': '#14b8a6', // teal-500
                'kualitas': '#10b981', // emerald-500
                'lingkungan': '#3b82f6', // blue-500
            };

            const bgColors = chartData.map(item => colorMap[item.category] || '#9ca3af');

            // Init Chart
            const ctx = document.getElementById('bobotChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.map(item => item.label),
                    datasets: [{
                        label: 'Bobot Akhir',
                        data: chartData.map(item => item.weight),
                        backgroundColor: bgColors,
                        borderRadius: 4,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 1.0,
                            ticks: {
                                callback: function(value) {
                                    return (value * 100).toFixed(0) + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let val = context.parsed.x;
                                    return ` Bobot: ${val.toFixed(4)} (${(val * 100).toFixed(2)}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
@endpush
