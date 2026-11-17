<?php

namespace App\Console\Commands;

use App\Services\StaffAccountService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SeedOperatorCommand extends Command
{
    protected $signature = 'seed:operator
                            {username? : Username akun Operator (contoh: operator1)}
                            {full_name? : Nama lengkap Operator}';

    protected $description = 'Membuat akun Operator baru dengan kata sandi sementara sekali tampil (KP-006, MOD-015)';

    public function handle(StaffAccountService $service): int
    {
        $username = (string) ($this->argument('username') ?: $this->ask('Masukkan username Operator', 'operator1'));
        $fullName = (string) ($this->argument('full_name') ?: $this->ask('Masukkan nama lengkap Operator', 'Operator Pemilihan'));

        try {
            $result = $service->createAccount(
                username: $username,
                fullName: $fullName,
                role: 'operator',
                actorType: 'sistem',
                actorId: null
            );
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $account = $result['account'];
        $tempPassword = $result['temporary_password'];

        $this->components->info('Akun Operator berhasil dibuat.');
        $this->table(
            ['Atribut', 'Nilai'],
            [
                ['ID Akun', $account->id],
                ['Username', $account->username],
                ['Nama Lengkap', $account->full_name],
                ['Peran', 'Operator (Wajib Ganti Sandi saat login pertama, tanpa TOTP)'],
                ['Kata Sandi Sementara (TAMPIL SEKALI)', $tempPassword],
            ]
        );
        $this->components->warn('Simpan kata sandi sementara di atas sekarang. Kata sandi ini tidak dapat ditampilkan kembali.');

        return self::SUCCESS;
    }
}
