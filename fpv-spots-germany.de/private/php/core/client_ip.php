<?php
declare(strict_types=1);
// =============================================================
// Liefert die reale Client-IP – bei Betrieb hinter einem Reverse-
// Proxy/CDN (z. B. Cloudflare) würde $_SERVER['REMOTE_ADDR'] sonst
// nur die Proxy-IP liefern und Rate-Limits/Audit-Logs entwerten.
//
// Sicherheitskritisch: X-Forwarded-For wird NUR akzeptiert, wenn
// die unmittelbare Verbindung von einem vertrauenswürdigen Proxy
// kommt. Andernfalls könnte ein Client den Header beliebig setzen
// und IP-basierte Rate-Limits / Audit-Logs spoofen.
//
// Konfiguration: Trusted-Proxies-Liste pflegen, falls hinter Proxy.
// Leere Liste → Verhalten identisch zu $_SERVER['REMOTE_ADDR'].
// =============================================================

if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        // IPs/CIDR-Blöcke vertrauter Proxies (hier leer lassen, wenn kein Proxy davor sitzt)
        $trustedProxies = [
            // '127.0.0.1',
            // '::1',
            // Cloudflare-Beispiel: '173.245.48.0/20', '103.21.244.0/22', …
        ];

        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (empty($trustedProxies) || !in_array($remote, $trustedProxies, true)) {
            return $remote;
        }

        $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($fwd === '') {
            return $remote;
        }

        // XFF kann eine Kette "client, proxy1, proxy2" sein – linkester gültiger Wert
        foreach (array_map('trim', explode(',', $fwd)) as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return $remote;
    }
}
