**Tipe Balapan sudah tersedia di branch lokal `feat/recap-race`.** Saat memilih **Mode Animasi**, operator bisa memilih **Tipe Sekuensial** atau **Tipe Balapan**.

Di Balapan, semua paslon mulai dari nol. **Total Suara** tetap terlihat, **Suara Tersisa** berkurang setiap reveal, dan kartu paslon bergeser saat peringkat berubah. Operator bisa berganti antara **Buka Suara** dan reveal otomatis di tengah proses. Kecepatan otomatis bisa diubah hingga **16×**, dengan interval tercepat **20 ms**.

Saya uji di Chrome desktop dan HP dengan PostgreSQL lokal. Setelah beberapa kali berganti cara reveal, 100 suara tetap terbagi tepat **22/34/44**, tanpa suara hilang atau dihitung ganda. Animasi tetap berjalan penuh saat *reduced motion* aktif, dan alur Sekuensial **Simpan → Play** lulus uji. Seluruh **16 tes PHPUnit lulus** dengan 339 assertion.

Branch siap ditinjau sebelum merge dan push.