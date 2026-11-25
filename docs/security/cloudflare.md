# Cloudflare dan origin

Jalankan `nginx/cloudflare-real-ip.sh` untuk menghasilkan konfigurasi IPv4 dan IPv6 dari daftar resmi. Tinjau hasil, simpan sebagai include di server Nginx yang menerima Cloudflare, jalankan `nginx -t`, lalu reload. Pertahankan salinan konfigurasi terakhir yang lolos pemeriksaan. [Cloudflare original visitor IP](https://developers.cloudflare.com/support/troubleshooting/restoring-visitor-ips/restoring-original-visitor-ips/)

Jika Nginx sudah memulihkan IP, teruskan `$remote_addr` ke PHP-FPM dan kosongkan `SECURITY_CLOUDFLARE_RANGES`. Jika Laravel menerima peer Cloudflare langsung, isi variabel tersebut dengan CIDR resmi. Isi `TRUSTED_PROXIES` dengan proxy internal yang benar-benar mengganti header forwarding. Batasi akses origin ke jaringan Cloudflare tanpa memutus jalur administratif yang dikelola. Uji dari staging bahwa header dari sumber lain tidak memengaruhi IP klien.

Gunakan mode pencatatan WAF lebih dulu bila didukung. Ukur polling dan trafik NAT sekolah sebelum memasang plafon per IP pada route bilik, Livewire, atau upload. Penolakan aplikasi bersifat sementara dan menggunakan 429 serta `Retry-After`.
