<?php
// PoP login replay protection — bind the session nonce at generate, consume once at verify.
// Demo store is a temp-file per nonce (works across PHP-FPM/Apache processes via atomic
// unlink). Use Redis or a shared DB in production.

function vb_nonce_path(string $nonce): string {
    // sha256 the nonce into the filename → no path traversal from attacker-controlled input.
    return sys_get_temp_dir() . '/vb_popnonce_' . hash('sha256', $nonce);
}

/**
 * Record a nonce as a legitimate, pending login session (TTL seconds), TOGETHER WITH the
 * validations the server asked for it — verify.php reads the signed result against these.
 */
function vb_nonce_put(string $nonce, int $ttlSeconds, array $asked = []): void {
    $record = json_encode(['exp' => time() + $ttlSeconds, 'asked' => (object) $asked]);
    @file_put_contents(vb_nonce_path($nonce), $record, LOCK_EX);
}

/**
 * One-time consume. Returns the validations stored with the nonce (possibly an empty array)
 * only for the caller whose unlink wins (atomic), and only if the nonce existed and has not
 * expired; otherwise null.
 */
function vb_nonce_consume(string $nonce): ?array {
    $path = vb_nonce_path($nonce);
    if (!is_file($path)) return null;

    $record = json_decode((string) @file_get_contents($path), true);

    // Atomic claim: only one concurrent request can successfully unlink the file.
    if (!@unlink($path)) return null;

    // Expired tokens are unlinked above but still rejected.
    $expiresAt = is_array($record) ? (int) ($record['exp'] ?? 0) : 0;
    if ($expiresAt <= 0 || time() >= $expiresAt) return null;

    $asked = $record['asked'] ?? [];
    return is_array($asked) ? $asked : [];
}
