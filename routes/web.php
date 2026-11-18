<?php

use App\Http\Controllers\AuthController;
use App\Livewire\Admin\AuditLogPage;
use App\Livewire\Admin\OperatorIndexPage;
use App\Livewire\Auth\ActivateAccountPage;
use App\Livewire\Auth\LoginPage;
use App\Livewire\Auth\VerifyTwoFactorPage;
use App\Livewire\Operator\ElectionDetailPage;
use App\Livewire\Operator\ElectionIndexPage;
use App\Livewire\Operator\ElectionRecapPage;
use App\Livewire\Operator\VoterMonitorPage;
use App\Livewire\Public\LiveRecapPage;
use App\Livewire\Public\PublicResultsPage;
use App\Livewire\Public\PublicTurnoutPage;
use App\Livewire\Voter\BallotPage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
})->name('home');

// UI-001: Masuk Staf
Route::get('/login', LoginPage::class)->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// UI-002 & UI-003: Verifikasi 2FA Admin & Aktivasi Akun Pertama
Route::middleware(['staff.auth'])->group(function () {
    Route::get('/auth/2fa', VerifyTwoFactorPage::class)->name('auth.2fa');
    Route::get('/auth/activate', ActivateAccountPage::class)->name('auth.activate');
});

// Area Admin (UI-004, UI-005, UI-006) — Hanya Admin (FR-004, BR-021)
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['staff.auth', 'staff.role:admin'])
    ->group(function () {
        Route::get('/operators', OperatorIndexPage::class)->name('operators');
        Route::get('/audit-logs', AuditLogPage::class)->name('audit-logs');
    });

// Area Operator (UI-007 s.d. UI-013) — Hanya Operator (FR-004, BR-021)
Route::prefix('operator')
    ->name('operator.')
    ->middleware(['staff.auth', 'staff.role:operator'])
    ->group(function () {
        Route::get('/elections', ElectionIndexPage::class)->name('elections');
        Route::get('/elections/{election}', ElectionDetailPage::class)->whereUuid('election')->name('elections.detail');
        Route::get('/elections/{election}/monitor', VoterMonitorPage::class)->whereUuid('election')->name('elections.monitor');
        Route::get('/elections/{election}/recap', ElectionRecapPage::class)->whereUuid('election')->name('elections.recap');
    });

// Area Pemilih (UI-014 s.d. UI-017) — Akses via Token QR Sekali Pakai
Route::middleware(['throttle:voter-access'])->group(function () {
    Route::get('/v/{token}', BallotPage::class)->name('voter.ballot');
});

// Area Publik (UI-018 & UI-019) — Tanpa Login (KP-008)
Route::prefix('p')
    ->name('public.')
    ->middleware(['throttle:public-access'])
    ->group(function () {
        Route::get('/{election}/turnout', PublicTurnoutPage::class)->whereUuid('election')->name('turnout');
        Route::get('/{election}/results', PublicResultsPage::class)->whereUuid('election')->name('results');
    });

// Perubahan 7: Halaman Khusus Rekapitulasi Real-Time dengan Access Key 64 Karakter (Path Berbeda dari /p/...)
Route::middleware(['throttle:live-recap'])->group(function () {
    Route::get('/pantau-rekap/{election}', LiveRecapPage::class)->whereUuid('election')->name('live-recap.view');
});
