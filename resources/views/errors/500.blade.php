@include('errors.state-error', [
    'code' => 500,
    'title' => 'Kendala Sistem',
    'danger' => true,
    'icon' => '#i-alert',
    'heading' => 'Terjadi Kendala pada Sistem',
    'message' => 'Permintaan Anda belum dapat diselesaikan saat ini. Silakan muat ulang halaman beberapa saat lagi atau hubungi petugas.',
])
