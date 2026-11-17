<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Log;
use Throwable;

trait HandlesSafeErrors
{
    /**
     * Mencatat detail exception mentah ke berkas log harian ber-timestamp (storage/logs/laravel-YYYY-MM-DD.log)
     * dan menampilkan pesan bahasa Indonesia yang ramah kepada pengguna tanpa membocorkan detail teknis (FR-031, NFR-006).
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

        if ($errorField !== null && $errorField !== '') {
            $this->addError($errorField, $friendlyMessage);
        }

        $this->dispatch('toast', message: $friendlyMessage, type: 'error');
    }
}
