<?php
/**
 * Einmalig auf dem Server ausführen, um ein VAPID-Keypair zu erzeugen.
 *
 * Aufruf (vom Projekt-Root, also dort wo composer.json liegt):
 *   php fpv-spots-germany.de/private/php/cli/generate_vapid.php
 *
 * Anschließend die ausgegebenen Werte in die .env übernehmen:
 *   VAPID_PUBLIC_KEY=...
 *   VAPID_PRIVATE_KEY=...
 *   VAPID_SUBJECT=mailto:kontakt@fpv-spots-germany.de
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Nur via CLI aufrufen.\n");
}

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();

echo "VAPID_PUBLIC_KEY="  . $keys['publicKey']  . "\n";
echo "VAPID_PRIVATE_KEY=" . $keys['privateKey'] . "\n";
echo "VAPID_SUBJECT=mailto:kontakt@fpv-spots-germany.de\n";
