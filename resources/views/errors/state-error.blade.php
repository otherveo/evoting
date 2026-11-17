@php
  ob_start();
@endphp
<div class="container page view-enter">
  <div class="state-page">
    <div class="state-icon {{ ($danger ?? false) ? 'is-danger' : 'is-neutral' }}" aria-hidden="true">
      <svg class="icon"><use href="{{ $icon ?? '#i-alert' }}"></use></svg>
    </div>
    <div class="eyebrow">
      <svg class="icon"><use href="#i-shield"></use></svg>
      <span>KODE STATUS {{ $code ?? 400 }}</span>
    </div>
    <h1>{{ $heading ?? 'Permintaan Tidak Dapat Diproses' }}</h1>
    <p>{{ $message ?? 'Terjadi kendala saat membuka halaman ini. Silakan kembali ke halaman utama.' }}</p>

    <div class="state-actions" style="margin-top: 24px;">
      @php
        /** @var \App\Models\StaffAccount|null $u */
        $u = auth('staff')->user();
        $homeUrl = $u ? ($u->isAdmin() ? route('admin.operators') : route('operator.elections')) : route('login');
      @endphp
      <a href="{{ $homeUrl }}" class="btn btn-primary">
        <svg class="icon"><use href="#i-arrow-left"></use></svg>
        <span>Kembali ke Beranda</span>
      </a>
    </div>
  </div>
</div>
@php
  $slot = new \Illuminate\Support\HtmlString(ob_get_clean());
@endphp
@include('layouts.app', ['title' => $title ?? 'Kendala Permintaan', 'slot' => $slot])
