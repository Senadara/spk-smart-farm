@props([
    'id',
    'latInputId' => 'latitude',
    'lngInputId' => 'longitude',
    'addressInputId' => null,
    'initialQuery' => '',
    'title' => 'Pilih titik lokasi',
    'help' => 'Cari wilayah atau klik titik pada peta. Latitude dan longitude akan terisi otomatis.',
])

<div
    class="location-picker rounded-lg border border-emerald-100 bg-emerald-50/40 p-4"
    data-location-picker
    data-map-id="{{ $id }}-map"
    data-lat-input-id="{{ $latInputId }}"
    data-lng-input-id="{{ $lngInputId }}"
    data-address-input-id="{{ $addressInputId }}"
>
    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h4 class="text-sm font-bold text-gray-900">{{ $title }}</h4>
            <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
        </div>
        <span data-location-picker-status class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-500">
            Menunggu titik
        </span>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-[1fr_auto_auto]">
        <input
            type="search"
            data-location-picker-search
            value="{{ $initialQuery }}"
            placeholder="Cari lokasi, contoh: Ngantang, Malang"
            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
        >
        <button
            type="button"
            data-location-picker-search-button
            class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800"
        >
            Cari lokasi
        </button>
        <button
            type="button"
            data-location-picker-current-button
            class="rounded-lg border border-emerald-200 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50"
        >
            Gunakan lokasi saya
        </button>
    </div>

    <div data-location-picker-results class="mt-3 hidden overflow-hidden rounded-lg border border-gray-200 bg-white"></div>

    <div id="{{ $id }}-map" class="mt-4 h-72 overflow-hidden rounded-lg border border-gray-200 bg-gray-100"></div>

    <p class="mt-3 text-xs text-gray-500">
        Estimasi jarak dan waktu pengiriman dihitung dari koordinat yang tersimpan. Koreksi pin jika hasil pencarian belum tepat.
    </p>
</div>

@once
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <style>
            .location-picker .leaflet-container {
                font-family: Inter, sans-serif;
            }
        </style>
    @endpush

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const defaultPoint = [-7.9666204, 112.6326321];

                function numericValue(input) {
                    if (!input || input.value === '') {
                        return null;
                    }

                    const value = Number.parseFloat(input.value);

                    return Number.isFinite(value) ? value : null;
                }

                function setStatus(wrapper, text, tone = 'neutral') {
                    const status = wrapper.querySelector('[data-location-picker-status]');
                    if (!status) {
                        return;
                    }

                    const tones = {
                        neutral: 'bg-white text-gray-500',
                        success: 'bg-emerald-100 text-emerald-700',
                        warning: 'bg-amber-100 text-amber-700',
                        danger: 'bg-red-100 text-red-700',
                    };

                    status.className = 'rounded-full px-3 py-1 text-xs font-semibold ' + (tones[tone] || tones.neutral);
                    status.textContent = text;
                }

                function setCoordinates(wrapper, markerState, latInput, lngInput, lat, lng, zoom = 15) {
                    latInput.value = Number(lat).toFixed(7);
                    lngInput.value = Number(lng).toFixed(7);

                    const point = [Number(lat), Number(lng)];
                    if (!markerState.marker) {
                        markerState.marker = L.marker(point, { draggable: true }).addTo(markerState.map);
                        markerState.marker.on('dragend', function () {
                            const position = markerState.marker.getLatLng();
                            setCoordinates(wrapper, markerState, latInput, lngInput, position.lat, position.lng, markerState.map.getZoom());
                        });
                    } else {
                        markerState.marker.setLatLng(point);
                    }

                    markerState.map.setView(point, zoom);
                    setStatus(wrapper, 'Koordinat siap', 'success');
                }

                function renderResults(wrapper, results, onChoose) {
                    const box = wrapper.querySelector('[data-location-picker-results]');
                    box.replaceChildren();

                    if (!results.length) {
                        const empty = document.createElement('div');
                        empty.className = 'px-4 py-3 text-sm text-gray-500';
                        empty.textContent = 'Lokasi tidak ditemukan. Coba kata kunci yang lebih spesifik.';
                        box.appendChild(empty);
                        box.classList.remove('hidden');

                        return;
                    }

                    results.forEach((result) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'block w-full border-b border-gray-100 px-4 py-3 text-left text-sm hover:bg-emerald-50 last:border-b-0';
                        button.textContent = result.display_name;
                        button.addEventListener('click', () => onChoose(result));
                        box.appendChild(button);
                    });

                    box.classList.remove('hidden');
                }

                function initPicker(wrapper) {
                    if (!window.L) {
                        setStatus(wrapper, 'Peta gagal dimuat', 'danger');

                        return;
                    }

                    const mapElement = document.getElementById(wrapper.dataset.mapId);
                    const latInput = document.getElementById(wrapper.dataset.latInputId);
                    const lngInput = document.getElementById(wrapper.dataset.lngInputId);
                    const addressInput = wrapper.dataset.addressInputId
                        ? document.getElementById(wrapper.dataset.addressInputId)
                        : null;
                    const searchInput = wrapper.querySelector('[data-location-picker-search]');
                    const searchButton = wrapper.querySelector('[data-location-picker-search-button]');
                    const currentButton = wrapper.querySelector('[data-location-picker-current-button]');

                    if (!mapElement || !latInput || !lngInput) {
                        setStatus(wrapper, 'Input koordinat tidak lengkap', 'danger');

                        return;
                    }

                    const initialLat = numericValue(latInput);
                    const initialLng = numericValue(lngInput);
                    const hasInitialPoint = initialLat !== null && initialLng !== null;
                    const markerState = {
                        map: L.map(mapElement),
                        marker: null,
                    };

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors',
                    }).addTo(markerState.map);

                    markerState.map.setView(hasInitialPoint ? [initialLat, initialLng] : defaultPoint, hasInitialPoint ? 15 : 11);

                    if (hasInitialPoint) {
                        setCoordinates(wrapper, markerState, latInput, lngInput, initialLat, initialLng, 15);
                    }

                    markerState.map.on('click', function (event) {
                        setCoordinates(wrapper, markerState, latInput, lngInput, event.latlng.lat, event.latlng.lng, markerState.map.getZoom());
                    });

                    [latInput, lngInput].forEach((input) => {
                        input.addEventListener('change', function () {
                            const lat = numericValue(latInput);
                            const lng = numericValue(lngInput);
                            if (lat !== null && lng !== null) {
                                setCoordinates(wrapper, markerState, latInput, lngInput, lat, lng, 15);
                            }
                        });
                    });

                    searchButton.addEventListener('click', async function () {
                        const query = searchInput.value.trim();
                        if (!query) {
                            setStatus(wrapper, 'Isi kata kunci lokasi', 'warning');

                            return;
                        }

                        setStatus(wrapper, 'Mencari lokasi...', 'neutral');
                        searchButton.disabled = true;

                        try {
                            const url = new URL('https://nominatim.openstreetmap.org/search');
                            url.searchParams.set('format', 'jsonv2');
                            url.searchParams.set('limit', '6');
                            url.searchParams.set('accept-language', 'id');
                            url.searchParams.set('q', query);

                            const response = await fetch(url.toString());
                            const results = response.ok ? await response.json() : [];

                            renderResults(wrapper, results, function (result) {
                                setCoordinates(wrapper, markerState, latInput, lngInput, result.lat, result.lon, 15);
                                if (addressInput) {
                                    addressInput.value = result.display_name;
                                }
                                setStatus(wrapper, 'Lokasi dipilih', 'success');
                                wrapper.querySelector('[data-location-picker-results]').classList.add('hidden');
                            });

                            setStatus(wrapper, results.length ? 'Pilih hasil pencarian' : 'Tidak ditemukan', results.length ? 'neutral' : 'warning');
                        } catch (error) {
                            setStatus(wrapper, 'Pencarian gagal', 'danger');
                        } finally {
                            searchButton.disabled = false;
                        }
                    });

                    searchInput.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            searchButton.click();
                        }
                    });

                    currentButton.addEventListener('click', function () {
                        if (!navigator.geolocation) {
                            setStatus(wrapper, 'Browser tidak mendukung lokasi', 'warning');

                            return;
                        }

                        setStatus(wrapper, 'Mengambil lokasi...', 'neutral');
                        navigator.geolocation.getCurrentPosition(
                            function (position) {
                                setCoordinates(
                                    wrapper,
                                    markerState,
                                    latInput,
                                    lngInput,
                                    position.coords.latitude,
                                    position.coords.longitude,
                                    16
                                );
                            },
                            function () {
                                setStatus(wrapper, 'Izin lokasi ditolak', 'warning');
                            },
                            { enableHighAccuracy: true, timeout: 10000 }
                        );
                    });

                    setTimeout(() => markerState.map.invalidateSize(), 150);
                }

                document.querySelectorAll('[data-location-picker]').forEach(initPicker);
            });
        </script>
    @endpush
@endonce
