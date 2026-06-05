<?php

namespace App\Services;

class TotpService
{
    /**
     * 指定されたシークレットキーとタイムスタンプから、対応する6桁のワンタイムパスワードコードを生成します。
     * 
     * @param string $secret Base32でエンコードされたシークレットキー
     * @param int|null $time 基準となるタイムスタンプ（nullの場合は現在の時間）
     * @return string|null 生成された6桁コード、失敗時はnull
     */
    public static function generateCode(string $secret, ?int $time = null): ?string
    {
        $secret = strtoupper(str_replace(' ', '', $secret));
        $secretBinary = self::base32Decode($secret);
        if (!$secretBinary) {
            return null;
        }

        $timeStep = (int) floor(($time ?? time()) / 30);
        $timeStepBinary = pack('N*', 0) . pack('N*', $timeStep);

        $hmac = hash_hmac('sha1', $timeStepBinary, $secretBinary, true);

        $offset = ord($hmac[19]) & 0xf;
        $value = (((ord($hmac[$offset]) & 0x7f) << 24) |
                  ((ord($hmac[$offset + 1]) & 0xff) << 16) |
                  ((ord($hmac[$offset + 2]) & 0xff) << 8) |
                  (ord($hmac[$offset + 3]) & 0xff));

        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * ワンタイムパスワードを検証します。
     * 
     * @param string $secret Base32でエンコードされたシークレットキー
     * @param string $code ユーザーが入力した6桁のコード
     * @param int $discrepancy 許容する時間ステップのズレ（1ステップ = 30秒）
     * @return bool
     */
    public static function verify(string $secret, string $code, int $discrepancy = 1): bool
    {
        $secret = strtoupper(str_replace(' ', '', $secret));
        $code = str_replace(' ', '', $code);

        if (strlen($code) !== 6 || !is_numeric($code)) {
            return false;
        }

        $currentTime = time();

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $time = $currentTime + ($i * 30);
            $otp = self::generateCode($secret, $time);
            
            if ($otp !== null && hash_equals($otp, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ランダムなBase32のシークレットキーを生成します。
     * 
     * @param int $length
     * @return string
     */
    public static function generateSecret(int $length = 16): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * 認証アプリ（Google Authenticatorなど）に登録するための otpauth:// URLを生成します。
     * 
     * @param string $label 識別用のラベル（例: ユーザーメールアドレスなど）
     * @param string $secret シークレットキー
     * @param string $issuer 発行者名（サービス名）
     * @return string
     */
    public static function getQrCodeUrl(string $label, string $secret, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);
    }

    /**
     * Base32文字列をデコードします。
     * 
     * @param string $base32
     * @return string|null デコードに失敗した場合はnull
     */
    private static function base32Decode(string $base32): ?string
    {
        $base32 = strtoupper(rtrim($base32, '='));
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $len = strlen($base32);
        
        if ($len === 0) {
            return null;
        }

        $n = 0;
        $j = 0;
        $binary = '';

        for ($i = 0; $i < $len; $i++) {
            $char = $base32[$i];
            $val = strpos($alphabet, $char);
            if ($val === false) {
                return null; // 不正な文字が含まれる
            }
            $n = ($n << 5) | $val;
            $j += 5;
            if ($j >= 8) {
                $j -= 8;
                $binary .= chr(($n >> $j) & 0xff);
            }
        }

        return $binary;
    }
}
