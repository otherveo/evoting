<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SupabaseStorageService
{
    /**
     * Mengunggah foto paslon ke bucket publik Supabase Storage (`paslon-foto`) dari sisi server.
     * Konvensi path: `<election_id>/<candidate_id>-<random8>.<ext>`.
     */
    public function uploadCandidatePhoto(string $electionId, string $candidateId, UploadedFile $file): string
    {
        $baseUrl = rtrim((string) config('evoting.supabase.url'), '/');
        $secretKey = (string) config('evoting.supabase.secret_key');
        $bucket = (string) config('evoting.supabase.bucket', 'paslon-foto');

        if ($baseUrl === '' || $secretKey === '') {
            throw new RuntimeException('Konfigurasi penyimpanan foto belum lengkap.');
        }

        $image = @getimagesize($file->getRealPath());
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mimeType = $file->getMimeType();
        if (! isset($extensions[$mimeType]) || ($image['mime'] ?? null) !== $mimeType
            || $file->getSize() > 2 * 1024 * 1024 || $image[0] * $image[1] > 16000000) {
            throw new RuntimeException('Foto harus berupa JPG, PNG, atau WebP maksimal 2 MB dan 16 megapiksel.');
        }

        $ext = $extensions[$mimeType];
        $objectPath = "{$electionId}/{$candidateId}-".substr(bin2hex(random_bytes(4)), 0, 8).".{$ext}";
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Berkas foto tidak dapat dibaca. Silakan pilih ulang berkas gambar.');
        }

        $endpoint = "{$baseUrl}/storage/v1/object/{$bucket}/{$objectPath}";

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Authorization' => "Bearer {$secretKey}",
                    'apikey' => $secretKey,
                    'x-upsert' => 'true',
                ])
                ->withBody($contents, $mimeType)
                ->post($endpoint);
        } catch (Throwable $e) {
            Log::error('Koneksi ke Supabase Storage gagal saat mengunggah foto paslon', [
                'timestamp' => now()->toIso8601String(),
                'election_id' => $electionId,
                'exception_class' => $e::class,
            ]);

            throw new RuntimeException('Gagal mengunggah foto paslon ke server penyimpanan. Periksa koneksi jaringan dan coba kembali.');
        }

        if (! $response->successful()) {
            Log::error('Respons Supabase Storage tidak berhasil saat mengunggah foto paslon', [
                'timestamp' => now()->toIso8601String(),
                'status' => $response->status(),
                'election_id' => $electionId,
            ]);

            throw new RuntimeException('Gagal mengunggah foto paslon ke penyimpanan. Silakan coba kembali.');
        }

        return $objectPath;
    }

    public function deleteCandidatePhoto(?string $objectPath): void
    {
        if (! $objectPath || str_starts_with($objectPath, 'http') || str_starts_with($objectPath, 'data:')) {
            return;
        }

        $baseUrl = rtrim((string) config('evoting.supabase.url'), '/');
        $secretKey = (string) config('evoting.supabase.secret_key');
        $bucket = (string) config('evoting.supabase.bucket', 'paslon-foto');

        if ($baseUrl === '' || $secretKey === '') {
            return;
        }

        $cleanPath = ltrim($objectPath, '/');
        if (str_starts_with($cleanPath, $bucket.'/')) {
            $cleanPath = substr($cleanPath, strlen($bucket) + 1);
        }

        try {
            Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$secretKey}",
                    'apikey' => $secretKey,
                ])
                ->delete("{$baseUrl}/storage/v1/object/{$bucket}/{$cleanPath}");
        } catch (Throwable $e) {
            Log::warning('Gagal menghapus foto lama dari Supabase Storage', [
                'timestamp' => now()->toIso8601String(),
            ]);
        }
    }
}
