<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

trait HandlesSafeErrors
{
    /**
     * Pengaman idempotency server-side agar spam-klik atau pengiriman ganda
     * tidak menghasilkan transaksi/data duplikat.
     */
    protected function acquireIdempotencyLock(string $key, int $ttlSeconds = 5): bool
    {
        return Cache::add('evoting_idem:'.sha1(static::class.':'.$key), 1, $ttlSeconds);
    }

    /**
     * Mencatat detail exception mentah ke berkas log harian ber-timestamp (storage/logs/laravel-YYYY-MM-DD.log),
     * memberikan penjelasan penyebab spesifik pada form error (tanpa membocorkan teks cURL/SQL mentah),
     * dan menampilkan popup singkat 1–2 kata.
     *
     * @param  array<string, mixed>  $context
     */
    protected function reportSafeError(
        Throwable $exception,
        string $friendlyMessage,
        ?string $errorField = null,
        array $context = []
    ): void {
        Log::error($friendlyMessage, array_merge([
            'timestamp' => now()->toIso8601String(),
            'component' => static::class,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ], $context));

        $specificMessage = $this->resolveSpecificSafeMessage($exception, $friendlyMessage);

        if ($errorField !== null && $errorField !== '') {
            $this->addError($errorField, $specificMessage);
        }

        $this->dispatch('toast', message: 'Gagal', type: 'error', reason: $specificMessage);
    }

    protected function resolveSpecificSafeMessage(Throwable $e, string $fallback): string
    {
        if ($e instanceof InvalidArgumentException || $e instanceof RuntimeException) {
            $msg = $e->getMessage();
            if ($msg !== '' && ! str_contains(strtolower($msg), 'curl') && ! str_contains(strtolower($msg), 'sqlstate')) {
                return $msg;
            }
        }

        if ($e instanceof QueryException) {
            $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());

            return match ($sqlState) {
                '23505' => "{$fallback} Penyebab: Data yang dimasukkan sudah terdaftar (duplikat).",
                '23514' => "{$fallback} Penyebab: Nilai yang dimasukkan melanggar batas aturan validasi data.",
                '23503' => "{$fallback} Penyebab: Data ini masih terhubung dengan data lain.",
                '42501' => "{$fallback} Penyebab: Operasi ditolak oleh pembatasan hak akses basis data.",
                'EV001' => "{$fallback} Penyebab: Data suara dan audit bersifat permanen dan tidak dapat diubah secara langsung.",
                '08006', '08001', '08003' => "{$fallback} Penyebab: Koneksi ke server basis data terputus sementara.",
                default => "{$fallback} Penyebab: Kendala pemrosesan basis data (kode {$sqlState}).",
            };
        }

        $raw = strtolower($e->getMessage());
        if (str_contains($raw, 'curl') || str_contains($raw, 'ssl') || str_contains($raw, 'timed out')) {
            return "{$fallback} Penyebab: Gangguan koneksi jaringan ke layanan penyimpanan.";
        }

        return $fallback;
    }
}
