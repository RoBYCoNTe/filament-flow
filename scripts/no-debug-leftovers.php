<?php

/**
 * Fails when the source carries a debugging leftover.
 *
 * A package ships to other people's applications: a `dd()` left in a validation path, a
 * `file_put_contents('/tmp/...')` written on every transition, an `error_log()` nobody reads —
 * they are not a style slip, they are something a host pays for. Two of them lived in
 * `Actions/StateAction.php` until the day this guard was written.
 *
 *   composer check:debug
 */
// `\b` matters: without it `array(` matches `ray(`, and a guard that cries wolf gets switched off.
$patterns = [
    '/\bfile_put_contents\s*\(/',
    '/\bdd\s*\(/',
    '/\bdump\s*\(/',
    '/\bvar_dump\s*\(/',
    '/\bprint_r\s*\(/',
    '/\berror_log\s*\(/',
    '/\bray\s*\(/',
    '/__halt_compiler/',
    "/['\"]\/tmp\//",
];

$roots = ['src', 'resources/js'];
$offenders = [];

foreach ($roots as $root) {
    if (! is_dir($root)) {
        continue;
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($files as $file) {
        if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'js'], true)) {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        $lines = explode("\n", (string) $contents);

        foreach ($lines as $number => $line) {
            // The patterns are regular expressions with a word boundary: a method named
            // `->dump()` and a word like `array` must not be mistaken for a leftover.
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line) === 1) {
                    $offenders[] = $file->getPathname().':'.($number + 1).' → '.trim($line);
                }
            }
        }
    }
}

if ($offenders !== []) {
    fwrite(STDERR, "Debugging leftovers found:\n  ".implode("\n  ", $offenders)."\n");
    fwrite(STDERR, "Remove them: a host must not pay for our diagnostics.\n");

    exit(1);
}

fwrite(STDOUT, "No debugging leftovers.\n");

exit(0);
