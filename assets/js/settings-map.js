/**
 * Settings Map JavaScript
 * Interactive Leaflet Pin Picker & Dynamic Radius Circle Visualizer
 * Sistem Presensi Karyawan GPS
 */

(function () {
    'use strict';

    function initSettingsMap() {
        const mapEl = document.getElementById('settingsMap');
        const latInput = document.getElementById('settingLatitude');
        const lngInput = document.getElementById('settingLongitude');
        const radiusInput = document.getElementById('settingRadius');
        const radiusValueDisplay = document.getElementById('radiusValueDisplay');
        const btnUseCurrentLocation = document.getElementById('btnUseCurrentLocation');

        if (!mapEl) return;

        if (typeof L === 'undefined') {
            console.error('Leaflet JS belum dimuat.');
            return;
        }

        // Ambil nilai koordinat awal dari input atau default
        let currentLat = parseFloat(latInput?.value);
        let currentLng = parseFloat(lngInput?.value);
        let currentRadius = parseInt(radiusInput?.value) || 100;

        if (isNaN(currentLat) || currentLat === 0) currentLat = -6.224168;
        if (isNaN(currentLng) || currentLng === 0) currentLng = 106.809675;

        // Pastikan container memiliki tinggi minimum
        if (!mapEl.style.height || mapEl.offsetHeight === 0) {
            mapEl.style.height = '500px';
        }

        // Inisialisasi Leaflet Map
        const map = L.map('settingsMap', {
            center: [currentLat, currentLng],
            zoom: 17,
            zoomControl: true,
            attributionControl: false
        });

        // Gunakan Tile Layer OpenStreetMap 100% Gratis Tanpa API Key / Watermark
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            className: 'map-dark-tiles',
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Custom Icon Kantor (Pusat Radius)
        const officeIcon = L.divIcon({
            className: 'custom-office-pin',
            html: `
                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-cyan-600 via-indigo-600 to-emerald-400 p-0.5 shadow-xl shadow-cyan-500/50 flex items-center justify-center cursor-grab active:cursor-grabbing hover:scale-110 transition-transform">
                    <div class="w-full h-full bg-slate-950 rounded-full flex items-center justify-center text-cyan-400 border border-white/20">
                        <i class="fa-solid fa-building-circle-check text-base"></i>
                    </div>
                </div>
            `,
            iconSize: [40, 40],
            iconAnchor: [20, 20],
            popupAnchor: [0, -22]
        });

        // Draggable Marker
        const marker = L.marker([currentLat, currentLng], {
            icon: officeIcon,
            draggable: true,
            title: 'Geser titik ini untuk menentukan pusat kantor'
        }).addTo(map);

        marker.bindPopup(`
            <div class="text-xs p-1 text-slate-800">
                <strong class="text-slate-950 block text-sm">Titik Pusat Kantor</strong>
                <span>Geser pin atau klik di peta untuk memindahkan lokasi.</span>
            </div>
        `);

        // Lingkaran Radius Toleransi
        const circle = L.circle([currentLat, currentLng], {
            color: '#06b6d4',
            fillColor: '#06b6d4',
            fillOpacity: 0.2,
            weight: 2,
            dashArray: '6, 6',
            radius: currentRadius
        }).addTo(map);

        // Fungsi memperbarui koordinat
        function updateCoordinates(lat, lng, panTo = false) {
            currentLat = lat;
            currentLng = lng;

            if (latInput) latInput.value = lat.toFixed(8);
            if (lngInput) lngInput.value = lng.toFixed(8);

            marker.setLatLng([lat, lng]);
            circle.setLatLng([lat, lng]);

            if (panTo) {
                map.panTo([lat, lng]);
            }
        }

        // Fungsi memperbarui radius
        function updateRadius(val) {
            currentRadius = parseInt(val) || 100;
            circle.setRadius(currentRadius);

            if (radiusValueDisplay) {
                radiusValueDisplay.textContent = `${currentRadius} Meter`;
            }
        }

        // Event saat marker digeser (real-time saat drag)
        marker.on('drag', function (e) {
            const pos = e.latlng;
            circle.setLatLng(pos);
            if (latInput) latInput.value = pos.lat.toFixed(8);
            if (lngInput) lngInput.value = pos.lng.toFixed(8);
        });

        // Event saat selesai drag
        marker.on('dragend', function (e) {
            const pos = marker.getLatLng();
            updateCoordinates(pos.lat, pos.lng);
            if (window.Toast) {
                Toast.fire({
                    icon: 'info',
                    title: `Titik kantor diperbarui: ${pos.lat.toFixed(6)}, ${pos.lng.toFixed(6)}`
                });
            }
        });

        // Event saat area peta diklik
        map.on('click', function (e) {
            updateCoordinates(e.latlng.lat, e.latlng.lng);
            if (window.Toast) {
                Toast.fire({
                    icon: 'info',
                    title: `Lokasi dipilih: ${e.latlng.lat.toFixed(6)}, ${e.latlng.lng.toFixed(6)}`
                });
            }
        });

        // Event Slider Radius (Real-time input)
        if (radiusInput) {
            radiusInput.addEventListener('input', function (e) {
                updateRadius(e.target.value);
            });
            radiusInput.addEventListener('change', function (e) {
                updateRadius(e.target.value);
                // Sesuaikan zoom jika radius sangat besar
                if (currentRadius >= 400 && map.getZoom() > 16) {
                    map.setZoom(16);
                } else if (currentRadius <= 100 && map.getZoom() < 17) {
                    map.setZoom(17);
                }
            });
        }

        // Tombol Preset Cepat (50m, 100m, 200m, 500m)
        document.querySelectorAll('.btn-radius-preset').forEach(btn => {
            btn.addEventListener('click', function () {
                const preset = parseInt(this.dataset.radius);
                if (radiusInput) radiusInput.value = preset;
                updateRadius(preset);
                if (window.Toast) {
                    Toast.fire({ icon: 'info', title: `Radius disetel ke ${preset} meter` });
                }
            });
        });

        // Input manual Latitude
        if (latInput) {
            latInput.addEventListener('input', function () {
                const val = parseFloat(latInput.value);
                if (!isNaN(val) && val >= -90 && val <= 90) {
                    updateCoordinates(val, currentLng, true);
                }
            });
        }

        // Input manual Longitude
        if (lngInput) {
            lngInput.addEventListener('input', function () {
                const val = parseFloat(lngInput.value);
                if (!isNaN(val) && val >= -180 && val <= 180) {
                    updateCoordinates(currentLat, val, true);
                }
            });
        }

        // Tombol "Gunakan Lokasi GPS Saya Saat Ini"
        if (btnUseCurrentLocation) {
            btnUseCurrentLocation.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    alert('Browser Anda tidak mendukung Geolocation.');
                    return;
                }

                const originalBtnHtml = btnUseCurrentLocation.innerHTML;
                btnUseCurrentLocation.disabled = true;
                btnUseCurrentLocation.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Mendeteksi GPS...`;

                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        const lat = pos.coords.latitude;
                        const lng = pos.coords.longitude;
                        updateCoordinates(lat, lng);
                        map.flyTo([lat, lng], 18, { duration: 1.2 });

                        btnUseCurrentLocation.disabled = false;
                        btnUseCurrentLocation.innerHTML = originalBtnHtml;

                        if (window.Toast) {
                            Toast.fire({
                                icon: 'success',
                                title: 'Titik kantor berhasil disesuaikan dengan GPS Anda!'
                            });
                        }
                    },
                    function (err) {
                        console.warn('Geolocation error:', err);
                        btnUseCurrentLocation.disabled = false;
                        btnUseCurrentLocation.innerHTML = originalBtnHtml;

                        if (window.Swal) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Gagal Mendeteksi GPS',
                                text: 'Pastikan izin akses lokasi aktif pada browser Anda, atau geser pin langsung di peta.',
                                background: '#0f172a',
                                color: '#f8fafc',
                                confirmButtonColor: '#06b6d4'
                            });
                        } else {
                            alert('Gagal mendeteksi lokasi GPS: ' + err.message);
                        }
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            });
        }

        // Invalidate size agar seluruh tile dirender penuh
        setTimeout(() => {
            map.invalidateSize();
        }, 200);

        window.addEventListener('resize', () => {
            map.invalidateSize();
        });
    }

    // Jalankan inisialisasi saat DOM siap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSettingsMap);
    } else {
        initSettingsMap();
    }

})();
