<?php

namespace App\Console\Commands;

use App\Services\StaffAccountService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SeedAdminCommand extends Command
{
    protected $signature = 'seed:admin
                            {username? : Username akun Admin (contoh: admin)}
                            {full_name? : Nama lengkap Admin}';

    protected $description = 'Membuat akun Admin baru dengan kata sandi sementara sekali tampil (KP-006, MOD-015)';

    public function handle(StaffAccountService $service): int
    {
        $username = (string) ($this->argument('username') ?: $this->ask('Masukkan username Admin', 'admin'));
        $fullName = (string) ($this->argument('full_name') ?: $this->ask('Masukkan nama lengkap Admin', 'Administrator Sistem'));

        try {
            $result = $service->createAccount(
                username: $username,
                fullName: $fullName,
                role: 'admin',
                actorType: 'sistem',
                actorId: null
            );
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $account = $result['account'];
        $tempPassword = $result['temporary_password'];

        $this->components->info('Akun Admin berhasil dibuat.');
        $this->table(
            ['Atribut', 'Nilai'],
            [
                ['ID Akun', $account->id],
                ['Username', $account->username],
                ['Nama Lengkap', $account->full_name],
                ['Peran', 'Admin (Wajib Ganti Sandi + Aktivasi TOTP saat login pertama)'],
                ['Kata Sandi Sementara (TAMPIL SEKALI)', $tempPassword],
            ]
        );
        $this->components->warn('Simpan kata sandi sementara di atas sekarang. Kata sandi ini tidak dapat ditampilkan kembali.');

        return self::SUCCESS;
    }
}
