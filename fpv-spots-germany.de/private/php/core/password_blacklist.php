<?php
declare(strict_types=1);
// =============================================================
// Passwort-Blacklist: verhindert die Wahl weit verbreiteter
// Passwörter (Top-1000-Liste aus best1050.txt).
// =============================================================

if (!function_exists('is_blacklisted_password')) {
    function is_blacklisted_password(string $password): bool
    {
        static $blacklist = null;

        if ($blacklist === null) {
            $file = __DIR__ . '/../../data/best1050.txt';
            $blacklist = [];
            if (is_readable($file)) {
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $entry = strtolower(trim($line));
                    if ($entry === '' || $entry === '------') {
                        continue;
                    }
                    $blacklist[$entry] = true;
                }
            }
        }

        return isset($blacklist[strtolower(trim($password))]);
    }
}
