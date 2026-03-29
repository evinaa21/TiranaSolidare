<?php
/**
 * Pure-PHP Web Push sender — no external library required.
 *
 * Implements:
 *   - VAPID key generation  (EC P-256)
 *   - VAPID JWT signing      (ES256)
 *   - RFC 8291 payload encryption (aes128gcm with ECDH + HKDF + AES-128-GCM)
 *
 * Requirements: ext-openssl (always available on XAMPP)
 *               PHP ≥ 8.0 (openssl_pkey_derive)
 *               hash_hkdf (built-in ≥ 7.1.2)
 */

// ── Helpers ──────────────────────────────────────────────────────────────────

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

/**
 * Load a raw 65-byte uncompressed P-256 public key (\x04 || x || y)
 * as a PHP OpenSSL key resource so openssl_pkey_derive() can use it.
 */
function load_p256_public_key(string $raw): \OpenSSLAsymmetricKey
{
    // Wrap in SubjectPublicKeyInfo DER:
    //   SEQUENCE {
    //     SEQUENCE { OID id-ecPublicKey, OID prime256v1 }
    //     BIT STRING \x00 <uncompressed point>
    //   }
    $algId = "\x30\x13"                       // SEQUENCE length=19
           . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"  // OID id-ecPublicKey
           . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // OID prime256v1
    $bitStr  = "\x03\x42\x00" . $raw;          // BIT STRING, len=66, 0 padding bits
    $inner   = $algId . $bitStr;               // 21 + 68 = 89 bytes
    $der     = "\x30\x59" . $inner;            // SEQUENCE length=89
    $pem     = "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END PUBLIC KEY-----\n";
    $key = openssl_pkey_get_public($pem);
    if ($key === false) {
        throw new \RuntimeException('Failed to load P-256 public key: ' . openssl_error_string());
    }
    return $key;
}

/**
 * Convert a DER-encoded ECDSA signature to the raw r || s format required by JOSE (ES256).
 */
function der_sig_to_raw(string $der): string
{
    $pos = 2; // skip SEQUENCE tag + length
    // INTEGER r
    $pos++;   // skip 0x02
    $rLen = ord($der[$pos++]);
    $r    = substr($der, $pos, $rLen);
    $pos += $rLen;
    // INTEGER s
    $pos++;   // skip 0x02
    $sLen = ord($der[$pos++]);
    $s    = substr($der, $pos, $sLen);

    // Remove leading 0x00 padding integer sign bytes and pad to 32 bytes
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    return $r . $s;
}

// ── VAPID key generation ─────────────────────────────────────────────────────

/**
 * Generate a new VAPID EC P-256 key pair.
 * Returns ['public' => base64url, 'private' => base64url].
 * Store these in .env as VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY.
 */
function vapid_generate_keys(): array
{
    $key = openssl_pkey_new([
        'curve_name'        => 'prime256v1',
        'private_key_type'  => OPENSSL_KEYTYPE_EC,
    ]);
    if ($key === false) {
        throw new \RuntimeException('openssl_pkey_new failed: ' . openssl_error_string());
    }
    $details   = openssl_pkey_get_details($key);
    $rawPub    = "\x04" . $details['ec']['x'] . $details['ec']['y']; // 65 bytes uncompressed
    $rawPriv   = $details['ec']['d'];                                  // 32 bytes
    return [
        'public'  => base64url_encode($rawPub),
        'private' => base64url_encode($rawPriv),
    ];
}

// ── VAPID JWT ────────────────────────────────────────────────────────────────

/**
 * Build a VAPID authorization string for the given endpoint audience.
 *
 * @param string $audience   https://push.service.com (scheme + host only)
 * @param string $subject    mailto:contact@example.com
 * @param string $pubKeyB64u base64url-encoded VAPID public key (65 bytes)
 * @param string $privKeyB64u base64url-encoded VAPID private key (32 bytes)
 */
function vapid_auth_header(
    string $audience,
    string $subject,
    string $pubKeyB64u,
    string $privKeyB64u
): array {
    // Build JWT
    $header  = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = base64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200, // 12 h
        'sub' => $subject,
    ]));
    $sigInput = $header . '.' . $payload;

    // Reconstruct private key as PEM from raw bytes
    $rawPriv = base64url_decode($privKeyB64u);
    $rawPub  = base64url_decode($pubKeyB64u);

    // ECPrivateKey DER: SEQUENCE { version INTEGER 1, privateKey OCTET STRING, [0] OID, [1] public key }
    $privDer = "\x30\x77"
             . "\x02\x01\x01"                        // version = 1
             . "\x04\x20" . $rawPriv                  // privateKey (32 bytes)
             . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" // [0] OID prime256v1
             . "\xa1\x44\x03\x42\x00" . $rawPub;     // [1] publicKey (65 bytes uncompressed)

    $pem = "-----BEGIN EC PRIVATE KEY-----\n"
         . chunk_split(base64_encode($privDer), 64, "\n")
         . "-----END EC PRIVATE KEY-----\n";

    $privKey = openssl_pkey_get_private($pem);
    if ($privKey === false) {
        throw new \RuntimeException('Failed to load VAPID private key: ' . openssl_error_string());
    }

    $derSig = '';
    openssl_sign($sigInput, $derSig, $privKey, OPENSSL_ALGO_SHA256);
    $rawSig = der_sig_to_raw($derSig);

    $token = $sigInput . '.' . base64url_encode($rawSig);
    return [
        'Authorization' => 'vapid t=' . $token . ', k=' . $pubKeyB64u,
    ];
}

// ── RFC 8291 payload encryption ──────────────────────────────────────────────

/**
 * Encrypt a push payload using the RFC 8291 "aes128gcm" content encoding.
 *
 * @param string $plaintext     The notification JSON payload
 * @param string $p256dhB64u    base64url-encoded subscription p256dh key (65 bytes)
 * @param string $authB64u      base64url-encoded subscription auth secret (16 bytes)
 * @return array ['body' => binary string, 'encoding' => 'aes128gcm']
 */
function push_encrypt_payload(string $plaintext, string $p256dhB64u, string $authB64u): array
{
    $uaPublicKey  = base64url_decode($p256dhB64u); // 65 bytes uncompressed P-256 point
    $authSecret   = base64url_decode($authB64u);   // 16 bytes

    // Generate ephemeral sender key pair
    $serverKey     = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $serverDetails = openssl_pkey_get_details($serverKey);
    $serverPublicKey = "\x04" . $serverDetails['ec']['x'] . $serverDetails['ec']['y']; // 65 bytes

    // ECDH: shared secret between server private key and UA public key
    $uaKeyResource = load_p256_public_key($uaPublicKey);
    $ecdhSecret    = openssl_pkey_derive($uaKeyResource, $serverKey, 32);
    if ($ecdhSecret === false) {
        throw new \RuntimeException('ECDH failed: ' . openssl_error_string());
    }

    // RFC 8291 § 3.3: derive IKM using HKDF with auth_secret as salt
    // ikm_info = "WebPush: info\x00" || ua_public_key || as_public_key
    $ikmInfo = "WebPush: info\x00" . $uaPublicKey . $serverPublicKey;
    $IKM     = hash_hkdf('sha256', $ecdhSecret, 32, $ikmInfo, $authSecret);

    // Generate salt (16 random bytes) for the record header
    $salt = random_bytes(16);

    // Derive CEK and nonce using HKDF with the salt
    $cek   = hash_hkdf('sha256', $IKM, 16, "Content-Encoding: aes128gcm\x00", $salt);
    $nonce = hash_hkdf('sha256', $IKM, 12, "Content-Encoding: nonce\x00", $salt);

    // Pad plaintext and append record delimiter 0x02 (single final record)
    $paddedPlaintext = $plaintext . "\x02";

    // AES-128-GCM encrypt
    $tag       = '';
    $ciphertext = openssl_encrypt($paddedPlaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($ciphertext === false) {
        throw new \RuntimeException('AES-128-GCM encrypt failed: ' . openssl_error_string());
    }

    // Build aes128gcm record:
    // header = salt (16) || rs (4 BE) || keyID_length (1) || keyID (65)
    $rs = max(4096, strlen($paddedPlaintext) + 16); // record size >= ciphertext size
    $header = $salt
            . pack('N', $rs)          // 4-byte big-endian record size
            . pack('C', 65)           // 1-byte key ID length
            . $serverPublicKey;       // 65-byte uncompressed ephemeral public key

    return [
        'body'     => $header . $ciphertext . $tag,
        'encoding' => 'aes128gcm',
    ];
}

// ── Send a single push ───────────────────────────────────────────────────────

/**
 * Send a Web Push notification to a single subscription.
 *
 * @param array  $subscription ['endpoint'=>'...', 'p256dh'=>'...', 'auth'=>'...']
 * @param array  $notification  ['title'=>'...', 'body'=>'...', 'url'=>'...']
 * @param string $vapidPublic    base64url VAPID public key
 * @param string $vapidPrivate   base64url VAPID private key
 * @param string $vapidSubject   mailto: or https: contact URI
 * @return bool  true on success (HTTP 2xx or 201)
 */
function send_web_push(
    array  $subscription,
    array  $notification,
    string $vapidPublic,
    string $vapidPrivate,
    string $vapidSubject = 'mailto:admin@tiranasolidare.al'
): bool {
    $endpoint = $subscription['endpoint'];
    $parsed   = parse_url($endpoint);
    $audience = $parsed['scheme'] . '://' . $parsed['host'];

    $payload   = json_encode($notification, JSON_UNESCAPED_UNICODE);
    $encrypted = push_encrypt_payload($payload, $subscription['p256dh'], $subscription['auth']);

    $vapidHeaders = vapid_auth_header($audience, $vapidSubject, $vapidPublic, $vapidPrivate);

    $headers = array_merge($vapidHeaders, [
        'Content-Type'     => 'application/octet-stream',
        'Content-Encoding' => $encrypted['encoding'],
        'TTL'              => '86400',
    ]);

    $curlHeaders = array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $encrypted['body'],
        CURLOPT_HTTPHEADER     => $curlHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("web_push curl error for {$endpoint}: {$error}");
        return false;
    }

    // 201 Created = delivered; 410 Gone = subscription expired (should be removed)
    if ($httpCode === 410) {
        // Subscription is no longer valid — caller should clean it up
        throw new \RuntimeException('subscription_expired');
    }

    return $httpCode >= 200 && $httpCode < 300;
}
