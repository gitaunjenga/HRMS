<?php
/**
 * HRMS - TOTP (RFC 6238) implementation — pure PHP, no libraries
 */

// ─── Base32 ───────────────────────────────────────────────────────────────────

function base32Encode(string $input): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output   = '';
    $buffer   = 0;
    $bits     = 0;
    for ($i = 0; $i < strlen($input); $i++) {
        $buffer = ($buffer << 8) | ord($input[$i]);
        $bits  += 8;
        while ($bits >= 5) {
            $bits  -= 5;
            $output .= $alphabet[($buffer >> $bits) & 0x1F];
        }
    }
    if ($bits > 0) {
        $output .= $alphabet[($buffer << (5 - $bits)) & 0x1F];
    }
    // Pad to multiple of 8
    while (strlen($output) % 8 !== 0) {
        $output .= '=';
    }
    return $output;
}

function base32Decode(string $input): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input    = strtoupper(rtrim($input, '='));
    $output   = '';
    $buffer   = 0;
    $bits     = 0;
    for ($i = 0; $i < strlen($input); $i++) {
        $pos = strpos($alphabet, $input[$i]);
        if ($pos === false) continue;
        $buffer = ($buffer << 5) | $pos;
        $bits  += 5;
        if ($bits >= 8) {
            $bits  -= 8;
            $output .= chr(($buffer >> $bits) & 0xFF);
        }
    }
    return $output;
}

// ─── TOTP ─────────────────────────────────────────────────────────────────────

function totpGenerateSecret(): string {
    return base32Encode(random_bytes(20));
}

function totpGetCode(string $base32Secret, int $timeStep = 30, int $digits = 6, int $offset = 0): string {
    $secret  = base32Decode($base32Secret);
    $counter = (int)floor(time() / $timeStep) + $offset;
    $msg     = pack('N*', 0) . pack('N*', $counter);
    $hash    = hash_hmac('sha1', $msg, $secret, true);
    $off     = ord($hash[19]) & 0x0F;
    $code    = (
        ((ord($hash[$off])   & 0x7F) << 24) |
        ((ord($hash[$off+1]) & 0xFF) << 16) |
        ((ord($hash[$off+2]) & 0xFF) << 8)  |
        ( ord($hash[$off+3]) & 0xFF)
    ) % (10 ** $digits);
    return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
}

/** Verify code allowing ±1 time step for clock drift */
function totpVerify(string $base32Secret, string $code): bool {
    $code = trim($code);
    foreach ([-1, 0, 1] as $step) {
        if (hash_equals(totpGetCode($base32Secret, 30, 6, $step), $code)) {
            return true;
        }
    }
    return false;
}

function totpGetUri(string $secret, string $accountName, string $issuer = 'HRMS'): string {
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($accountName)
         . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}

function totpQrUrl(string $secret, string $accountName, string $issuer = 'HRMS'): string {
    $uri = totpGetUri($secret, $accountName, $issuer);
    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($uri);
}

/** Generate 8 single-use backup codes and return their plain text */
function totpGenerateBackupCodes(int $userId): array {
    require_once __DIR__ . '/functions.php';
    execute("DELETE FROM totp_backup_codes WHERE user_id = ?", 'i', $userId);
    $codes = [];
    for ($i = 0; $i < 8; $i++) {
        $plain  = strtoupper(bin2hex(random_bytes(4)));
        $hashed = password_hash($plain, PASSWORD_BCRYPT);
        execute(
            "INSERT INTO totp_backup_codes (user_id, code_hash) VALUES (?, ?)",
            'is', $userId, $hashed
        );
        $codes[] = $plain;
    }
    return $codes;
}

/** Attempt to consume a backup code. Returns true on success. */
function totpUseBackupCode(int $userId, string $code): bool {
    require_once __DIR__ . '/functions.php';
    $code  = strtoupper(trim($code));
    $rows  = fetchAll(
        "SELECT id, code_hash FROM totp_backup_codes WHERE user_id = ? AND used_at IS NULL",
        'i', $userId
    );
    foreach ($rows as $row) {
        if (password_verify($code, $row['code_hash'])) {
            execute(
                "UPDATE totp_backup_codes SET used_at = NOW() WHERE id = ?",
                'i', $row['id']
            );
            return true;
        }
    }
    return false;
}
