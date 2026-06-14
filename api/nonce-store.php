<?php
// PoP login replay protection — bind the session nonce at generate, consume once at verify.
// Demo store is a temp-file per nonce (works across PHP-FPM/Apache processes via atomic
// unlink). Use Redis/DB in production — see Documents/Production_Checklist.md item 4.8.

function vb_nonce_path(string $nonce): string {
    // sha256 the nonce into the filename → no path traversal from attacker-controlled input.
    return sys_get_temp_dir() . '/vb_popnonce_' . hash('sha256', $nonce);
}

/** Record a nonce as a legitimate, pending login session (TTL seconds). */
function vb_nonce_put(string $nonce, int $ttlSeconds): void {
    @file_put_contents(vb_nonce_path($nonce), (string) (time() + $ttlSeconds), LOCK_EX);
}

/**
 * One-time consume. Returns true only for the caller whose unlink wins (atomic),
 * and only if the nonce existed and has not expired.
 */
function vb_nonce_consume(string $nonce): bool {
    $path = vb_nonce_path($nonce);
    if (!is_file($path)) return false;

    $expiresAt = (int) @file_get_contents($path);

    // Atomic claim: only one concurrent request can successfully unlink the file.
    if (!@unlink($path)) return false;

    // Expired tokens are unlinked above but still rejected.
    return $expiresAt > 0 && time() < $expiresAt;
}
