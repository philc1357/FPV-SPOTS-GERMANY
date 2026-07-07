<?php
declare(strict_types=1);
// =============================================================
// YouTube-URL-Validierung
// =============================================================
// Extrahiert die 11-stellige YouTube-Video-ID aus verschiedenen
// URL-Formaten (watch?v=, youtu.be/, /shorts/, /embed/).
// Rückgabe: 11-stellige ID oder null bei ungültiger Eingabe.
// =============================================================

if (!function_exists('extractYoutubeId')) {

    function extractYoutubeId(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // parse_url ist robust gegenüber Sonderzeichen
        $parts = @parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';

        $candidate = null;

        // youtu.be/<ID>
        if ($host === 'youtu.be') {
            $candidate = ltrim($path, '/');
        }
        // (www.|m.)youtube.com
        elseif ($host === 'youtube.com' || $host === 'www.youtube.com' || $host === 'm.youtube.com') {
            if ($path === '/watch') {
                // ?v=<ID>
                $query = [];
                if (!empty($parts['query'])) {
                    parse_str($parts['query'], $query);
                }
                $candidate = $query['v'] ?? null;
            } elseif (strpos($path, '/shorts/') === 0) {
                $candidate = substr($path, strlen('/shorts/'));
            } elseif (strpos($path, '/embed/') === 0) {
                $candidate = substr($path, strlen('/embed/'));
            }
        } else {
            return null;
        }

        if (!is_string($candidate)) {
            return null;
        }

        // Falls noch ein Pfad-Rest dranhängt (z. B. /shorts/<ID>/...), abschneiden
        $slashPos = strpos($candidate, '/');
        if ($slashPos !== false) {
            $candidate = substr($candidate, 0, $slashPos);
        }

        // Whitelist-Regex: exakt 11 Zeichen aus dem YouTube-Alphabet
        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate)) {
            return null;
        }

        return $candidate;
    }
}

// =============================================================
// YouTube-Titel über oEmbed-API holen
// =============================================================
// Liefert den offiziellen Titel zur Video-ID (max. 150 Zeichen)
// oder null, wenn das Video nicht erreichbar/privat ist.
// Host ist hardcoded → kein SSRF-Risiko.
// =============================================================
if (!function_exists('fetchYoutubeTitle')) {

    function fetchYoutubeTitle(string $youtubeId): ?string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $youtubeId)) {
            return null;
        }

        $url = 'https://www.youtube.com/oembed?url='
             . urlencode('https://www.youtube.com/watch?v=' . $youtubeId)
             . '&format=json';

        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_USERAGENT      => 'FPV-Spots-Germany/1.0',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($body) || $code !== 200) {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['title']) || !is_string($data['title'])) {
            return null;
        }

        $title = trim($data['title']);
        if ($title === '') {
            return null;
        }

        // Auf 150 Zeichen kürzen (DB-Limit)
        if (mb_strlen($title) > 150) {
            $title = mb_substr($title, 0, 147) . '...';
        }
        return $title;
    }
}
