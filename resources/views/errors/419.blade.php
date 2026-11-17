@include('errors.state-error', [
    'code' => 419,
    'title' => 'Sesi Halaman Kedaluwarsa',
    'danger' => false,
    'icon' => '#i-clock',
    'heading' => 'Sesi Halaman Telah Berakhir',
    'message' => 'Halaman terlalu lama tidak aktif sehingga token keamanan berakhir. Silakan muat ulang halaman dan coba kembali.',
])
