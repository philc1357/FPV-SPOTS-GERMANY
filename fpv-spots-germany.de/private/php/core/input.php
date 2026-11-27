<?php
declare(strict_types=1);
// =============================================================
// Sichere Eingabe-Helfer
//
// $_POST-Werte können statt Strings auch Arrays sein
// (z. B. "name[]=x"). Unter strict_types führt das bei trim(),
// hash_equals() usw. zu einem TypeError und damit zu einem 500er.
// post_str() liefert deshalb immer einen String: Nicht-Strings
// werden wie ein fehlendes Feld behandelt.
// =============================================================
if (!function_exists('post_str')) {
    function post_str(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? null;
        return is_string($value) ? $value : $default;
    }
}

// =============================================================
// CSRF-Prüfung
// Leeres/fehlendes Session-Token gilt immer als ungültig – sonst
// wäre hash_equals('', '') === true: Ein Cross-Site-POST kommt
// wegen SameSite=Lax ohne Session-Cookie an (leere Session), und
// das Feld lässt der Angreifer einfach weg (Login-CSRF).
// Nicht-Strings (Arrays aus POST/JSON) werden abgelehnt statt
// einen TypeError auszulösen.
// =============================================================
if (!function_exists('csrf_valid')) {
    function csrf_valid(mixed $submitted): bool
    {
        $expected = $_SESSION['csrf_token'] ?? '';
        return is_string($expected) && $expected !== ''
            && is_string($submitted) && hash_equals($expected, $submitted);
    }
}
