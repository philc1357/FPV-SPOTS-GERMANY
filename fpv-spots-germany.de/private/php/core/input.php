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
