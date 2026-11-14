<?php
declare(strict_types=1);
// =============================================================
// Redirect-Helfer
//
// Gemeinsam genutzt von Login, Registrierung und Login-Pflicht-Seiten,
// damit man nach einer Aktion auf derselben Seite landet – ohne Open
// Redirect oder Header-Injection.
// =============================================================

// -------------------------------------------------------------
// Nur interne, relative Pfade erlauben ("/…", nicht "//host", keine
// Steuerzeichen/Backslashes). Alles andere -> Fallback "/".
// -------------------------------------------------------------
if (!function_exists('safe_redirect_target')) {
    function safe_redirect_target(?string $raw): string
    {
        if ($raw === null || $raw === '' || strlen($raw) > 500) return '/';
        if (!preg_match('#^/[A-Za-z0-9_\-./?&=%\#+,:~\[\]]*$#', $raw)) return '/';
        if (str_starts_with($raw, '//')) return '/';
        return $raw;
    }
}

// -------------------------------------------------------------
// Parameter der Login-/Registrierungs-Modals aus der URL entfernen,
// damit sie nicht in Redirect-Zielen "kleben bleiben". Anker bleibt.
// -------------------------------------------------------------
if (!function_exists('strip_login_params')) {
    function strip_login_params(string $url): string
    {
        $hash = '';
        if (($pos = strpos($url, '#')) !== false) {
            $hash = substr($url, $pos);
            $url  = substr($url, 0, $pos);
        }
        $parts = explode('?', $url, 2);
        $path  = $parts[0];
        $pairs = [];
        if (isset($parts[1]) && $parts[1] !== '') {
            foreach (explode('&', $parts[1]) as $pair) {
                $key = urldecode(explode('=', $pair, 2)[0]);
                if ($pair === '' || in_array($key, ['showLogin', 'showRegister', 'redirect'], true)) continue;
                $pairs[] = $pair;
            }
        }
        return $path . ($pairs ? '?' . implode('&', $pairs) : '') . $hash;
    }
}

// -------------------------------------------------------------
// Ziel für "Login nötig": Startseite mit offenem Login-Banner,
// nach dem Login geht es zum Ausgangsziel zurück.
// -------------------------------------------------------------
if (!function_exists('login_required_url')) {
    function login_required_url(?string $target = null): string
    {
        $url = '/?showLogin=1';
        if ($target !== null) {
            $target = strip_login_params(safe_redirect_target($target));
            if ($target !== '/') $url .= '&redirect=' . rawurlencode($target);
        }
        return $url;
    }
}
