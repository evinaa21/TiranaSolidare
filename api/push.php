<?php
/**
 * Push Subscription API
 * Handles Web Push subscription management and VAPID public key distribution.
 *
 * Endpoints:
 *   GET  ?action=vapid_public_key  — returns the VAPID public key for the browser PushManager
 *   POST ?action=subscribe         — saves a push subscription for the authenticated user
 *   POST ?action=unsubscribe       — removes a push subscription by endpoint
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {

    // ── VAPID PUBLIC KEY ─────────────────────────────────────
    case 'vapid_public_key':
        $key = getenv('VAPID_PUBLIC_KEY');
        if (!$key) {
            json_error('VAPID not configured.', 503);
        }
        json_success(['public_key' => $key]);
        break;

    // ── SUBSCRIBE ────────────────────────────────────────────
    case 'subscribe':
        require_method('POST');
        $user = require_auth();

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        $endpoint = trim($data['endpoint'] ?? '');
        $p256dh   = trim($data['keys']['p256dh'] ?? '');
        $auth     = trim($data['keys']['auth'] ?? '');

        if (!$endpoint || !$p256dh || !$auth) {
            json_error('Subscription data e paplotë.', 422);
        }

        // Limit to 10 subscriptions per user to prevent runaway registrations
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?');
        $countStmt->execute([$user['id']]);
        if ((int)$countStmt->fetchColumn() >= 10) {
            // Remove oldest subscription to make room
            $pdo->prepare(
                'DELETE FROM push_subscriptions WHERE user_id = ? ORDER BY created_at ASC LIMIT 1'
            )->execute([$user['id']]);
        }

        // Upsert — update keys if the endpoint already exists (key rotation)
        $stmt = $pdo->prepare(
            'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), user_id = VALUES(user_id)'
        );
        $stmt->execute([$user['id'], $endpoint, $p256dh, $auth]);

        json_success(['message' => 'Subscription u ruajt.'], 201);
        break;

    // ── UNSUBSCRIBE ──────────────────────────────────────────
    case 'unsubscribe':
        require_method('POST');
        $user = require_auth();

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        $endpoint = trim($data['endpoint'] ?? '');
        if (!$endpoint) {
            json_error('Endpoint mungon.', 422);
        }

        $pdo->prepare(
            'DELETE FROM push_subscriptions WHERE endpoint = ? AND user_id = ?'
        )->execute([$endpoint, $user['id']]);

        json_success(['message' => 'Subscription u hoq.']);
        break;

    default:
        json_error('Veprim i panjohur.', 400);
}
