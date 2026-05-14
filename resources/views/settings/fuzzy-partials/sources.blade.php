{{-- Tab 3: Sumber Data (Read-only) --}}
<div class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
    <h2 class="text-base font-semibold text-gray-900 mb-1">Sumber Data Input</h2>
    <p class="text-sm text-gray-500 mb-4">Mapping variabel input ke sumber data (IoT sensor atau fungsi kalkulasi)</p>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Variabel</th>
                    <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Tipe Sumber</th>
                    <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Detail</th>
                    <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Konfigurasi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inputSources as $src)
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                    <td class="py-3 px-3 font-medium text-gray-800">{{ $src->variable->name ?? '?' }}</td>
                    <td class="py-3 px-3">
                        <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full
                            {{ $src->source_type === 'iot' ? 'bg-emerald-50 text-emerald-700' : 'bg-violet-50 text-violet-700' }}">
                            {{ $src->source_type === 'iot' ? '📡 IoT Sensor' : '⚙️ Function' }}
                        </span>
                    </td>
                    <td class="py-3 px-3 text-gray-600">
                        @if($src->source_type === 'iot')
                            <code class="text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $src->source_name }}.{{ $src->field_name }}</code>
                        @else
                            <code class="text-xs bg-gray-100 px-2 py-0.5 rounded">{{ class_basename($src->function_name) }}</code>
                        @endif
                    </td>
                    <td class="py-3 px-3 text-xs text-gray-500">
                        @if($src->extra_config && is_array($src->extra_config))
                            @foreach($src->extra_config as $k => $v)
                                <span class="inline-block bg-gray-50 px-2 py-0.5 rounded mr-1">{{ $k }}: <strong>{{ $v }}</strong></span>
                            @endforeach
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
