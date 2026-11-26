# Cloudflare dan origin

Jalankan `nginx/cloudflare-real-ip.sh` untuk menghasilkan konfigurasi IPv4 dan IPv6 dari daftar resmi. Tinjau hasil, simpan sebagai include di server Nginx yang menerima Cloudflare, jalankan `nginx -t`, lalu reload. Pertahankan salinan konfigurasi terakhir yang lolos pemeriksaan. [Cloudflare original visitor IP](https://developers.cloudflare.com/support/troubleshooting/restoring-visitor-ips/restoring-original-visitor-ips/)

Jika Nginx sudah memulihkan IP, teruskan `$remote_addr` ke PHP-FPM dan kosongkan `SECURITY_CLOUDFLARE_RANGES`. Jika Laravel menerima peer Cloudflare langsung, isi variabel tersebut dengan CIDR resmi. Isi `TRUSTED_PROXIES` dengan proxy internal yang benar-benar mengganti header forwarding. Batasi akses origin ke jaringan Cloudflare tanpa memutus jalur administratif yang dikelola. Uji dari staging bahwa header dari sumber lain tidak memengaruhi IP klien.

## HTTPS pada Cloudflare Tunnel ke Nginx loopback

Pada vhost Tunnel yang hanya mendengarkan di `127.0.0.1`, gunakan map dan parameter FastCGI di [contoh Nginx](nginx/request-hardening.conf). Cloudflare menetapkan `X-Forwarded-Proto` berdasarkan skema pengunjung. Map memakai alamat peer asli sebelum Nginx memulihkan IP klien dan hanya menerima nilai `https` dari peer `127.0.0.1`. Pastikan jalur ini khusus cloudflared dan vhost tidak terbuka langsung ke jaringan. [Header Cloudflare](https://developers.cloudflare.com/fundamentals/reference/http-request-headers/#x-forwarded-proto) · [Nginx realip](https://nginx.org/en/docs/http/ngx_http_realip_module.html)

Di lokasi PHP yang melayani `index.php`, pastikan hanya ada satu parameter `HTTPS` yang efektif setelah memeriksa include FastCGI. Teruskan IP klien yang dipulihkan sebagai `REMOTE_ADDR` dan kosongkan parameter `HTTP_X_FORWARDED_PROTO` pada lokasi tersebut. Laravel lalu mengenali TLS dari parameter server `HTTPS` sambil tetap memakai IP klien untuk pembatasan trafik. Validasi konfigurasi dengan `nginx -t` sebelum reload. [Parameter FastCGI Nginx](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_param)

Di staging, periksa respons HTTPS `/login` dengan `curl -sSI https://<host-staging>/login`. Dengan `SECURITY_HSTS_MAX_AGE=86400`, cari `Strict-Transport-Security: max-age=86400`. Periksa di DevTools bahwa aksi login mengirim POST ke URL Livewire HTTPS pada origin yang sama, lalu mengarahkan operator ke daftar pemilihan. Ulangi saat ketiga mode keamanan disetel ke `enforce`.

Jika polling monitor kembali berstatus 404, cocokkan waktu, status upstream, dan `X-Request-ID` dari DevTools dengan log Nginx. Catat apakah rute yang diminta sama dengan `data-update-uri` dari halaman yang baru dimuat serta apakah permintaan berisi header `X-Livewire`. Kirim hanya status, path tanpa query, waktu, dan ID permintaan.

Gunakan mode pencatatan WAF lebih dulu bila didukung. Ukur polling dan trafik NAT sekolah sebelum memasang plafon per IP pada route bilik, Livewire, atau upload. Penolakan aplikasi bersifat sementara dan menggunakan 429 serta `Retry-After`.
