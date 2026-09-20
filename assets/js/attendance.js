/**
 * Attendance JavaScript Engine
 * GPS Geolocation, Leaflet Dark Map, WebRTC Camera Snapshot, Haversine Distance
 */

(function () {
    'use strict';

    // Global state
    const state = {
        officeLat: window.OFFICE_CONFIG?.latitude || -6.224168,
        officeLng: window.OFFICE_CONFIG?.longitude || 106.809675,
        officeRadius: window.OFFICE_CONFIG?.radius_meters || 100,
        enforceRadius: window.OFFICE_CONFIG?.enforce_radius ?? 1,
        userLat: null,
        userLng: null,
        distance: null,
        isInside: false,
        stream: null,
        capturedPhoto: null,
        map: null,
        officeMarker: null,
        radiusCircle: null,
        userMarker: null,
        accuracyCircle: null,
        distanceLine: null,
        isSimulated: false
    };

    // DOM Elements
    const elements = {
        video: document.getElementById('cameraVideo'),
        canvas: document.getElementById('cameraCanvas'),
        photoPreview: document.getElementById('photoPreview'),
        photoContainer: document.getElementById('photoContainer'),
        btnCapture: document.getElementById('btnCapturePhoto'),
        btnRetake: document.getElementById('btnRetakePhoto'),
        btnCheckIn: document.getElementById('btnCheckIn'),
        btnCheckOut: document.getElementById('btnCheckOut'),
        statusBadge: document.getElementById('radiusStatusBadge'),
        distanceText: document.getElementById('distanceIndicatorText'),
        userCoordsText: document.getElementById('userCoordsText'),
        accuracyText: document.getElementById('accuracyText'),
        notesInput: document.getElementById('attendanceNotes'),
        simToggleIn: document.getElementById('simInRadius'),
        simToggleOut: document.getElementById('simOutRadius'),
        simToggleReal: document.getElementById('simRealGps'),
        mapContainer: document.getElementById('liveAttendanceMap'),
        cameraPlaceholder: document.getElementById('cameraPlaceholder')
    };

    /**
     * Hitung jarak 2 titik koordinat (Haversine formula dalam meter)
     */
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000; // meter
        const dLat = ((lat2 - lat1) * Math.PI) / 180;
        const dLon = ((lon2 - lon1) * Math.PI) / 180;
        const a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos((lat1 * Math.PI) / 180) *
            Math.cos((lat2 * Math.PI) / 180) *
            Math.sin(dLon / 2) *
            Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return Math.round(R * c * 10) / 10;
    }

    /**
     * Inisialisasi Peta Leaflet dengan Dark Aesthetic
     */
    function initMap() {
        if (!elements.mapContainer || typeof L === 'undefined') return;

        state.map = L.map('liveAttendanceMap', {
            zoomControl: true,
            attributionControl: false
        }).setView([state.officeLat, state.officeLng], 17);

        // Tile layer OpenStreetMap 100% Gratis Tanpa API Key / Watermark
        const tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            className: 'map-dark-tiles',
            attribution: '&copy; OpenStreetMap contributors'
        });
        tileLayer.addTo(state.map);

        // Custom Icon Kantor
        const officeIcon = L.divIcon({
            className: 'custom-office-icon',
            html: `
                <div class="office-pin-marker">
                    <i class="fa-solid fa-building text-base"></i>
                </div>
            `,
            iconSize: [36, 36],
            iconAnchor: [18, 18]
        });

        // Marker Kantor
        state.officeMarker = L.marker([state.officeLat, state.officeLng], { icon: officeIcon })
            .addTo(state.map)
            .bindPopup(`<b class="text-slate-900">${window.OFFICE_CONFIG?.office_name || 'Lokasi Kantor'}</b><br><span class="text-xs text-slate-700">Pusat Radius Presensi</span>`);

        // Lingkaran Radius Toleransi
        state.radiusCircle = L.circle([state.officeLat, state.officeLng], {
            color: '#06b6d4',
            fillColor: '#06b6d4',
            fillOpacity: 0.15,
            weight: 2,
            dashArray: '5, 5',
            radius: state.officeRadius
        }).addTo(state.map);

        // Custom Icon User Live
        const userIcon = L.divIcon({
            className: 'custom-user-icon',
            html: `<div class="gps-pulse-marker"></div>`,
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });

        // Marker User (Live position)
        state.userMarker = L.marker([state.officeLat, state.officeLng], { icon: userIcon })
            .addTo(state.map)
            .bindPopup('<b class="text-slate-900">Posisi Anda</b>');
    }

    /**
     * Perbarui tampilan UI koordinat, jarak, status radius, dan peta
     */
    function updatePositionUI(lat, lng, accuracy = null) {
        state.userLat = lat;
        state.userLng = lng;
        state.distance = calculateDistance(lat, lng, state.officeLat, state.officeLng);
        state.isInside = state.distance <= state.officeRadius;

        // Update Text Tampilan
        if (elements.userCoordsText) {
            elements.userCoordsText.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
        if (elements.accuracyText) {
            elements.accuracyText.textContent = accuracy ? `±${Math.round(accuracy)}m` : 'Normal';
        }
        if (elements.distanceText) {
            elements.distanceText.textContent = `${state.distance} m`;
        }

        // Update Status Badge
        if (elements.statusBadge) {
            if (state.isInside) {
                elements.statusBadge.className = 'badge-glossy badge-glossy-success';
                elements.statusBadge.innerHTML = `<i class="fa-solid fa-circle-check text-xs"></i> Di Dalam Radius Kantor (${state.distance}m / Maks ${state.officeRadius}m)`;
            } else {
                elements.statusBadge.className = 'badge-glossy badge-glossy-danger';
                elements.statusBadge.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-xs"></i> Di Luar Radius (${state.distance}m / Maks ${state.officeRadius}m)`;
            }
        }

        // Update Button Disabled States jika enforce radius aktif
        const isBlocked = !state.isInside && state.enforceRadius && !state.isSimulated;
        if (elements.btnCheckIn && !elements.btnCheckIn.dataset.done) {
            elements.btnCheckIn.disabled = isBlocked;
            elements.btnCheckIn.classList.toggle('opacity-50', isBlocked);
            elements.btnCheckIn.classList.toggle('cursor-not-allowed', isBlocked);
        }
        if (elements.btnCheckOut && !elements.btnCheckOut.dataset.done) {
            elements.btnCheckOut.disabled = isBlocked;
            elements.btnCheckOut.classList.toggle('opacity-50', isBlocked);
            elements.btnCheckOut.classList.toggle('cursor-not-allowed', isBlocked);
        }

        // Update Peta
        if (state.map && state.userMarker) {
            const newPos = [lat, lng];
            state.userMarker.setLatLng(newPos);

            // Garis penghubung antara kantor dan user
            if (state.distanceLine) {
                state.map.removeLayer(state.distanceLine);
            }
            state.distanceLine = L.polyline([[state.officeLat, state.officeLng], newPos], {
                color: state.isInside ? '#10b981' : '#f43f5e',
                weight: 2,
                opacity: 0.7,
                dashArray: '4, 6'
            }).addTo(state.map);

            // Fit bounds peta agar kedua marker terlihat rapi
            const bounds = L.latLngBounds([[state.officeLat, state.officeLng], newPos]);
            state.map.fitBounds(bounds.pad(0.35), { maxZoom: 18 });
        }
    }

    /**
     * Mengaktifkan Realtime Geolocation Browser
     */
    function startGeolocation() {
        if (!navigator.geolocation) {
            if (window.Toast) {
                Toast.fire({
                    icon: 'warning',
                    title: 'Browser tidak mendukung Geolocation. Menggunakan simulasi koordinat.'
                });
            }
            simulatePosition('in');
            return;
        }

        // Coba dapatkan lokasi sekali
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                updatePositionUI(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
            },
            (err) => {
                console.warn('Geolocation denied or unavailable:', err.message);
                // Fallback default: letakkan di dalam radius agar demo tetap interaktif
                simulatePosition('in');
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );

        // Pantau pergerakan lokasi secara berkala
        navigator.geolocation.watchPosition(
            (pos) => {
                if (!state.isSimulated) {
                    updatePositionUI(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
                }
            },
            (err) => console.log('WatchPosition error:', err),
            { enableHighAccuracy: true }
        );
    }

    /**
     * Mode Simulasi Koordinat untuk Pengujian Cepat
     */
    function simulatePosition(type) {
        state.isSimulated = (type !== 'real');

        if (type === 'in') {
            // Berada 25 meter dari titik kantor (Di dalam radius)
            const simulatedLat = state.officeLat + 0.00015;
            const simulatedLng = state.officeLng + 0.00012;
            updatePositionUI(simulatedLat, simulatedLng, 5);
            if (window.Toast) {
                Toast.fire({ icon: 'info', title: 'Simulasi aktif: Posisi di dalam area kantor' });
            }
        } else if (type === 'out') {
            // Berada 450 meter dari titik kantor (Di luar radius)
            const simulatedLat = state.officeLat + 0.0035;
            const simulatedLng = state.officeLng + 0.0028;
            updatePositionUI(simulatedLat, simulatedLng, 12);
            if (window.Toast) {
                Toast.fire({ icon: 'warning', title: 'Simulasi aktif: Posisi di luar radius kantor' });
            }
        } else if (type === 'real') {
            startGeolocation();
            if (window.Toast) {
                Toast.fire({ icon: 'success', title: 'Mengaktifkan sensor GPS fisik perangkat' });
            }
        }
    }

    /**
     * Inisialisasi Kamera WebRTC
     */
    async function startCamera() {
        if (!elements.video) return;

        try {
            state.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                },
                audio: false
            });
            elements.video.srcObject = state.stream;
            elements.video.classList.remove('hidden');
            if (elements.cameraPlaceholder) {
                elements.cameraPlaceholder.classList.add('hidden');
            }
        } catch (err) {
            console.warn('Camera error or permission denied:', err);
            if (elements.cameraPlaceholder) {
                elements.cameraPlaceholder.innerHTML = `
                    <div class="text-center p-6 text-slate-400">
                        <i class="fa-solid fa-camera-slash text-4xl mb-3 text-slate-500"></i>
                        <p class="text-xs">Kamera tidak aktif atau izin ditolak.</p>
                        <p class="text-[11px] text-cyan-400 mt-1">Presensi tetap dapat dilanjutkan dengan foto cadangan profil.</p>
                    </div>
                `;
            }
        }
    }

    /**
     * Ambil Foto Snapshot dari Kamera
     */
    function captureSnapshot() {
        if (!elements.video || !elements.canvas) return null;

        const video = elements.video;
        const canvas = elements.canvas;
        const context = canvas.getContext('2d');

        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;

        // Gambar frame dari video ke canvas
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Konversi ke base64 data URL
        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
        state.capturedPhoto = dataUrl;

        // Tampilkan preview foto
        if (elements.photoPreview) {
            elements.photoPreview.src = dataUrl;
            elements.photoPreview.classList.remove('hidden');
            video.classList.add('hidden');
        }

        if (elements.btnCapture) elements.btnCapture.classList.add('hidden');
        if (elements.btnRetake) elements.btnRetake.classList.remove('hidden');

        return dataUrl;
    }

    /**
     * Reset / Ambil Ulang Foto
     */
    function retakePhoto() {
        state.capturedPhoto = null;
        if (elements.photoPreview) {
            elements.photoPreview.classList.add('hidden');
        }
        if (elements.video) {
            elements.video.classList.remove('hidden');
        }
        if (elements.btnCapture) elements.btnCapture.classList.remove('hidden');
        if (elements.btnRetake) elements.btnRetake.classList.add('hidden');
    }

    /**
     * Kirim Permintaan Presensi (Check-in atau Check-out)
     */
    async function submitAttendance(action) {
        if (!state.userLat || !state.userLng) {
            Swal.fire({
                icon: 'error',
                title: 'GPS Tidak Terdeteksi',
                text: 'Harap tunggu hingga sensor GPS berhasil mendeteksi koordinat Anda.',
                background: '#0f172a',
                color: '#f8fafc',
                confirmButtonColor: '#06b6d4'
            });
            return;
        }

        // Cek validasi radius ketat
        if (!state.isInside && state.enforceRadius && !state.isSimulated) {
            Swal.fire({
                icon: 'error',
                title: 'Di Luar Radius Kantor!',
                html: `Anda berada <b>${state.distance} meter</b> dari kantor.<br>Batas toleransi maksimal presensi adalah <b>${state.officeRadius} meter</b>.`,
                background: '#0f172a',
                color: '#f8fafc',
                confirmButtonColor: '#f43f5e'
            });
            return;
        }

        // Ambil snapshot otomatis jika belum diambil manual
        if (!state.capturedPhoto && elements.video && !elements.video.classList.contains('hidden')) {
            captureSnapshot();
        }

        const actionText = (action === 'check_in') ? 'Absen Masuk' : 'Absen Pulang';
        const buttonEl = (action === 'check_in') ? elements.btnCheckIn : elements.btnCheckOut;

        // Konfirmasi sebelum kirim
        const confirmResult = await Swal.fire({
            title: `Konfirmasi ${actionText}?`,
            html: `
                <div class="text-left text-sm space-y-2 mt-2 p-3 bg-slate-900/80 rounded-lg border border-white/10">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Jarak ke Kantor:</span>
                        <span class="font-bold ${state.isInside ? 'text-emerald-400' : 'text-rose-400'}">${state.distance} meter</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Status Radius:</span>
                        <span class="font-bold ${state.isInside ? 'text-emerald-400' : 'text-amber-400'}">${state.isInside ? 'Di Dalam Area' : 'Di Luar Area'}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Waktu:</span>
                        <span class="font-mono text-cyan-400">${new Date().toLocaleTimeString()}</span>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: `<i class="fa-solid fa-check mr-1"></i> Ya, ${actionText}`,
            cancelButtonText: 'Batal',
            confirmButtonColor: (action === 'check_in') ? '#10b981' : '#f43f5e',
            cancelButtonColor: '#475569',
            background: '#0f172a',
            color: '#f8fafc'
        });

        if (!confirmResult.isConfirmed) return;

        // Tampilkan loading spinner
        const originalBtnHtml = buttonEl.innerHTML;
        buttonEl.disabled = true;
        buttonEl.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Memproses...`;

        try {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('latitude', state.userLat);
            formData.append('longitude', state.userLng);
            formData.append('notes', elements.notesInput ? elements.notesInput.value : '');
            formData.append('is_simulation', state.isSimulated ? 1 : 0);

            if (state.capturedPhoto) {
                formData.append('photo', state.capturedPhoto);
            }

            const response = await fetch(`${window.BASE_URL}/api/presensi.php`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Presensi Sukses!',
                    html: `
                        <p class="text-sm">${result.message}</p>
                        <div class="mt-4 p-3 bg-slate-900/80 rounded-xl border border-white/10 text-xs text-slate-300">
                            Waktu: <b class="text-cyan-400">${result.time}</b> &bull; Jarak: <b>${result.distance} m</b>
                        </div>
                    `,
                    background: '#0f172a',
                    color: '#f8fafc',
                    confirmButtonColor: '#10b981'
                });

                // Reload halaman agar riwayat hari ini diperbarui
                window.location.reload();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Presensi Gagal',
                    text: result.message,
                    background: '#0f172a',
                    color: '#f8fafc',
                    confirmButtonColor: '#06b6d4'
                });
                buttonEl.disabled = false;
                buttonEl.innerHTML = originalBtnHtml;
            }
        } catch (error) {
            console.error('Submission error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Koneksi Bermasalah',
                text: 'Terjadi kegagalan komunikasi dengan server. Periksa koneksi internet Anda.',
                background: '#0f172a',
                color: '#f8fafc',
                confirmButtonColor: '#06b6d4'
            });
            buttonEl.disabled = false;
            buttonEl.innerHTML = originalBtnHtml;
        }
    }

    /**
     * Event Listeners Binding
     */
    function attachEvents() {
        if (elements.btnCapture) {
            elements.btnCapture.addEventListener('click', captureSnapshot);
        }
        if (elements.btnRetake) {
            elements.btnRetake.addEventListener('click', retakePhoto);
        }
        if (elements.btnCheckIn) {
            elements.btnCheckIn.addEventListener('click', () => submitAttendance('check_in'));
        }
        if (elements.btnCheckOut) {
            elements.btnCheckOut.addEventListener('click', () => submitAttendance('check_out'));
        }

        // Simulator Radios / Buttons
        if (elements.simToggleIn) {
            elements.simToggleIn.addEventListener('click', () => simulatePosition('in'));
        }
        if (elements.simToggleOut) {
            elements.simToggleOut.addEventListener('click', () => simulatePosition('out'));
        }
        if (elements.simToggleReal) {
            elements.simToggleReal.addEventListener('click', () => simulatePosition('real'));
        }
    }

    // Initialize on DOM Ready
    document.addEventListener('DOMContentLoaded', () => {
        initMap();
        startCamera();
        startGeolocation();
        attachEvents();
    });

})();
