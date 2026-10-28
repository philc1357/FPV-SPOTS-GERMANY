<?php
declare(strict_types=1);

/**
 * Web-Push-Helper: verschickt Browser-Benachrichtigungen an alle Geraete eines Users.
 *
 * Voraussetzung: composer require minishlink/web-push + VAPID-Keys in .env.
 * Der Aufruf ist "best effort" – wirft bewusst keine Exceptions in den aufrufenden Request-Flow.
 */

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

function sendPushToUser(PDO $pdo, int $userId, string $title, string $body, string $url, string $tag): void
{
    if ($userId <= 0) {
        return;
    }

    $publicKey  = $_ENV['VAPID_PUBLIC_KEY']  ?? '';
    $privateKey = $_ENV['VAPID_PRIVATE_KEY'] ?? '';
    $subject    = $_ENV['VAPID_SUBJECT']     ?? 'mailto:kontakt@fpv-spots-germany.de';

    if ($publicKey === '' || $privateKey === '') {
        // Push noch nicht konfiguriert – stiller Fallback, In-App-Benachrichtigung reicht.
        return;
    }

    if (!class_exists(WebPush::class)) {
        error_log('push.php: minishlink/web-push nicht installiert. composer install noetig.');
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $subs = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('push.php load subs: ' . $e->getMessage());
        return;
    }

    if (!$subs) {
        return;
    }

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $url,
        'tag'   => $tag,
    ], JSON_UNESCAPED_UNICODE);

    try {
        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => $subject,
                'publicKey'  => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);
        $webPush->setDefaultOptions(['TTL' => 3600]);
    } catch (Throwable $e) {
        error_log('push.php init: ' . $e->getMessage());
        return;
    }

    foreach ($subs as $sub) {
        try {
            $subscription = Subscription::create([
                'endpoint'        => $sub['endpoint'],
                'publicKey'       => $sub['p256dh'],
                'authToken'       => $sub['auth'],
                'contentEncoding' => 'aes128gcm',
            ]);
            $webPush->queueNotification($subscription, $payload);
        } catch (Throwable $e) {
            error_log('push.php queue: ' . $e->getMessage());
        }
    }

    try {
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            if ($report->isSuccess()) {
                try {
                    $upd = $pdo->prepare("UPDATE push_subscriptions SET last_used_at = NOW() WHERE endpoint = ?");
                    $upd->execute([$endpoint]);
                } catch (PDOException $e) {
                    error_log('push.php update last_used: ' . $e->getMessage());
                }
            } else {
                $response   = $report->getResponse();
                $statusCode = $response ? $response->getStatusCode() : 0;
                // 404 Not Found, 410 Gone → Abonnement entfernen
                if ($statusCode === 404 || $statusCode === 410 || $report->isSubscriptionExpired()) {
                    try {
                        $del = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
                        $del->execute([$endpoint]);
                    } catch (PDOException $e) {
                        error_log('push.php delete stale: ' . $e->getMessage());
                    }
                } else {
                    error_log('push.php send fail ' . $statusCode . ': ' . $report->getReason());
                }
            }
        }
    } catch (Throwable $e) {
        error_log('push.php flush: ' . $e->getMessage());
    }
}
