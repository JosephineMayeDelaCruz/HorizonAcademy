<?php
/**
 * ═══════════════════════════════════════════════════════════
 * TOTP — config/totp.php
 * ═══════════════════════════════════════════════════════════
 * Pure-PHP, no-library TOTP implementation (RFC 6238 / RFC 4226).
 * Used for physical presence verification in the enrollment queue.
 *
 * Functions:
 *   generate_totp_secret()          — random 16-char Base32 secret
 *   get_totp_code($secret)          — current 6-digit TOTP code
 *   verify_totp_code($secret, $code)— validates code ±1 window for clock drift
 */

// ── Base32 alphabet (RFC 4648) ─────────────────────────────
const TOTP_BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
const TOTP_WINDOW       = 30;   // seconds per time step
const TOTP_DIGITS       = 6;    // output code length
const TOTP_DRIFT        = 1;    // windows to accept on either side of current

/**
 * Generate a cryptographically random 16-character Base32 secret.
 * 16 Base32 chars = 80 bits of entropy — the standard TOTP secret size.
 *
 * @return string  Uppercase Base32 string, e.g. "JBSWY3DPEHPK3PXP"
 */
function generate_totp_secret(): string {
    $secret = '';
    $bytes  = random_bytes(16); // 128 bits of CSPRNG entropy

    // Map every 5 bits of the random bytes to one Base32 character.
    // We accumulate bits in an integer buffer and drain 5 at a time.
    $buffer   = 0;
    $bitsLeft = 0;

    foreach (str_split($bytes) as $byte) {
        $buffer    = ($buffer << 8) | ord($byte);
        $bitsLeft += 8;

        while ($bitsLeft >= 5) {
            $bitsLeft -= 5;
            $secret   .= TOTP_BASE32_CHARS[($buffer >> $bitsLeft) & 0x1F];
        }
    }

    // Trim or pad to exactly 16 characters.
    return substr(str_pad($secret, 16, 'A'), 0, 16);
}

/**
 * Decode a Base32 string into raw bytes.
 * Handles upper- and lower-case input; ignores padding characters.
 *
 * @param  string $base32  Base32-encoded string
 * @return string          Raw binary string
 */
function _totp_base32_decode(string $base32): string {
    $base32  = strtoupper($base32);
    $charMap = array_flip(str_split(TOTP_BASE32_CHARS));

    $buffer   = 0;
    $bitsLeft = 0;
    $output   = '';

    foreach (str_split($base32) as $char) {
        if ($char === '=') {
            break; // padding — stop
        }
        if (!isset($charMap[$char])) {
            continue; // skip unknown characters gracefully
        }

        $buffer    = ($buffer << 5) | $charMap[$char];
        $bitsLeft += 5;

        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $output   .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }

    return $output;
}

/**
 * Compute the HOTP value for a given secret and counter (RFC 4226).
 * This is the core algorithm shared by get_totp_code() and verify_totp_code().
 *
 * @param  string $secret   Base32-encoded shared secret
 * @param  int    $counter  Time step counter (Unix time ÷ TOTP_WINDOW)
 * @return string           Zero-padded TOTP_DIGITS-digit code
 */
function _totp_hotp(string $secret, int $counter): string {
    // Pack the 64-bit counter as a big-endian unsigned integer.
    $counterBytes = pack('N*', 0) . pack('N*', $counter);

    // Decode the Base32 secret to raw bytes for use as the HMAC key.
    $keyBytes = _totp_base32_decode($secret);

    // HMAC-SHA1: produces a 20-byte digest.
    $hash = hash_hmac('sha1', $counterBytes, $keyBytes, true);

    // Dynamic truncation (RFC 4226 §5.4):
    // Use the low-order 4 bits of the last byte as an offset.
    $offset = ord($hash[19]) & 0x0F;

    // Extract 4 bytes at the offset, mask the MSB, interpret as unsigned int.
    $code = (
        ((ord($hash[$offset])     & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) <<  8) |
         (ord($hash[$offset + 3]) & 0xFF)
    );

    // Modulo to TOTP_DIGITS digits, then zero-pad.
    return str_pad((string)($code % (10 ** TOTP_DIGITS)), TOTP_DIGITS, '0', STR_PAD_LEFT);
}

/**
 * Return the current 6-digit TOTP code for the given secret.
 * Uses the current Unix timestamp divided into 30-second windows.
 *
 * @param  string $secret  Base32-encoded shared secret
 * @return string          Current 6-digit code, e.g. "048271"
 */
function get_totp_code(string $secret): string {
    $counter = (int)floor(time() / TOTP_WINDOW);
    return _totp_hotp($secret, $counter);
}

/**
 * Validate a submitted TOTP code against the current window and
 * one window before/after to tolerate minor clock drift.
 *
 * Accepts codes from counters in the range [current - TOTP_DRIFT, current + TOTP_DRIFT].
 * Uses hash_equals() to prevent timing-based side-channel attacks.
 *
 * @param  string $secret  Base32-encoded shared secret
 * @param  string $code    6-digit code submitted by the user
 * @return bool            true if the code is valid, false otherwise
 */
function verify_totp_code(string $secret, string $code): bool {
    // Reject obviously malformed input early.
    if (!preg_match('/^\d{' . TOTP_DIGITS . '}$/', $code)) {
        return false;
    }

    $counter = (int)floor(time() / TOTP_WINDOW);

    for ($drift = -TOTP_DRIFT; $drift <= TOTP_DRIFT; $drift++) {
        $expected = _totp_hotp($secret, $counter + $drift);
        if (hash_equals($expected, $code)) {
            return true;
        }
    }

    return false;
}
