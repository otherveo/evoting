<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $secret = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, $max)];
        }

        return $secret;
    }

    public function verifyCode(string $secret, string $code, ?string $accountId = null, int $window = 1): bool
    {
        $cleanCode = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($cleanCode) !== 6) {
            return false;
        }

        $timeSlice = (int) floor(time() / 30);

        for ($i = -$window; $i <= $window; $i++) {
            $slice = $timeSlice + $i;
            $calculated = $this->getCodeForSlice($secret, $slice);
            if (hash_equals($calculated, $cleanCode)) {
                if ($accountId !== null) {
                    $replayKey = "totp_used:{$accountId}:{$slice}:{$cleanCode}";
                    if (! Cache::add($replayKey, 1, 90)) {
                        return false;
                    }
                }

                return true;
            }
        }

        return false;
    }

    public function getCurrentCode(string $secret): string
    {
        return $this->getCodeForSlice($secret, (int) floor(time() / 30));
    }

    public function getProvisioningUri(string $username, string $secret): string
    {
        $issuer = rawurlencode((string) config('app.name', 'SuaraSekolah'));
        $label = rawurlencode(config('app.name', 'SuaraSekolah').':'.$username);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    public function renderQrSvg(string $provisioningUri, int $size = 220): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);

        return $writer->writeString($provisioningUri);
    }

    private function getCodeForSlice(string $secret, int $timeSlice): string
    {
        $secretKey = $this->base32Decode($secret);
        $time = pack('N*', 0).pack('N*', $timeSlice);
        $hm = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hm, -1)) & 0x0F;
        $hashpart = substr($hm, $offset, 4);
        $value = unpack('N', $hashpart)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';

        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $val = strpos(self::ALPHABET, $secret[$i]);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $result;
    }
}
