<?php
/**
 * Footer Template - Modern Dark Glossy UI
 * Sistem Presensi Karyawan GPS
 */
?>
    </main>

    <!-- Glassmorphic Footer -->
    <footer class="glass-navbar border-t border-white/5 py-6 mt-12 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
            <div class="flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                <span class="font-semibold text-slate-300">GeoPresensi System</span> &mdash;
                <span>Sistem Presensi Karyawan GPS & Radius Validasi</span>
            </div>
            <div class="flex items-center space-x-6 text-slate-500">
                <span class="hover:text-cyan-400 transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-satellite-dish text-cyan-500"></i> Geolocation Active
                </span>
                <span class="hover:text-emerald-400 transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-shield-halved text-emerald-500"></i> Haversine Verified
                </span>
                <span>&copy; <?= date('Y') ?> All Rights Reserved.</span>
            </div>
        </div>
    </footer>

    <!-- SweetAlert2 for glossy alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Global Digital Clock Script -->
    <script>
        function updateLiveClock() {
            const clockEls = document.querySelectorAll('.live-digital-clock, #liveClock');
            if (!clockEls.length) return;
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const timeStr = `${hours}:${minutes}:${seconds} WIB`;
            clockEls.forEach(el => {
                el.textContent = timeStr;
            });
        }
        setInterval(updateLiveClock, 1000);
        updateLiveClock();

        // Custom Toast Helper
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            background: '#0f172a',
            color: '#f8fafc',
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
    </script>
</body>
</html>
