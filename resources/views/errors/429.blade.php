@include('errors.state-error', [
    'code' => 429,
    'title' => 'Terlalu Banyak Permintaan',
    'danger' => true,
    'icon' => '#i-alert',
    'heading' => 'Terlalu Banyak Percobaan',
    'message' => 'Sistem membatasi permintaan beruntun untuk menjaga keamanan pemilihan. Silakan tunggu sekitar satu menit sebelum mencoba kembali.',
])
