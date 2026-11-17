@include('errors.state-error', [
    'code' => 403,
    'title' => 'Akses Ditolak',
    'danger' => true,
    'icon' => '#i-lock',
    'heading' => 'Akses Tidak Diizinkan',
    'message' => 'Anda tidak memiliki wewenang untuk membuka halaman atau menjalankan tindakan ini. Hak akses staf dibatasi sesuai peran masing-masing.',
])
