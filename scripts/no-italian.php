<?php

/**
 * Fails when an Italian word leaks into a comment, a docblock or a page.
 *
 * Everything in this package is written in English — the rule lives in
 * `docs/guide/conventions.md` — and the words of a host's domain have a way of slipping in: a
 * docblock that reads "the exchanges of the istruttoria" is English with a hole in it, and the
 * hole is what a reader remembers. One of them lived in `Support/OpenRequests.php` until the day
 * this guard was written.
 *
 *   composer check:language
 */
// Every word is Italian-only: none is an English word a comment could legitimately use. That is
// why `dove`, `come`, `per` and `non` are missing — they are English words too. `\b` keeps
// `ente` out of `sentence` and `nel` out of `channel`.
$words = [
    'istruttoria', 'ufficio', 'pratica', 'pratiche', 'richiedente', 'istanza', 'istanze',
    'protocollo', 'protocollazione', 'scadenza', 'scadenze', 'allegato', 'allegati',
    'concessione', 'contributo', 'rigetto', 'rigettata', 'ammissione', 'ammissibile',
    'esito', 'motivazione', 'beneficiario', 'bando', 'bandi', 'domanda', 'domande',
    'ente', 'fascicolo', 'inoltrata', 'presentata', 'integrazione', 'verbale', 'perizia',
    'italiano', 'della', 'delle', 'degli', 'dello', 'nella', 'negli', 'perché', 'più',
    'così', 'già', 'può', 'sono', 'viene', 'essere', 'anche', 'senza', 'questo', 'questa',
    'questi', 'queste', 'allora', 'invece', 'sempre', 'molto',
];

$pattern = '/\b('.implode('|', array_map(static fn (string $word): string => preg_quote($word, '/'), $words)).')\b/iu';

$roots = ['src', 'resources/views', 'config', 'tests', 'docs'];
$extensions = ['php', 'blade.php', 'md'];
$offenders = [];

foreach ($roots as $root) {
    if (! is_dir($root)) {
        continue;
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($files as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());

        // The Italian translations speak Italian on purpose: they are the one place the
        // language of a host is allowed to be.
        if (str_contains($path, '/lang/it/') || str_ends_with($path, '/it.json')) {
            continue;
        }

        // The page that names the forbidden words is the one place they are written down: it
        // is the manual of this guard.
        if (str_ends_with($path, 'docs/guide/conventions.md')) {
            continue;
        }

        $extension = str_ends_with($path, '.blade.php') ? 'blade.php' : $file->getExtension();

        if (! in_array($extension, $extensions, true)) {
            continue;
        }

        $lines = explode("\n", (string) file_get_contents($path));

        foreach ($lines as $number => $line) {
            if (! isCommentLine($line, $extension)) {
                continue;
            }

            if (preg_match($pattern, $line) === 1) {
                $offenders[] = $path.':'.($number + 1).' → '.trim($line);
            }
        }
    }
}

/**
 * A page is prose all the way down; a source file only speaks in its comments — a string is a
 * label a host writes in its own language, and the guard has no business reading it.
 */
function isCommentLine(string $line, string $extension): bool
{
    if ($extension === 'md') {
        return true;
    }

    $trimmed = ltrim($line);

    return str_starts_with($trimmed, '*')
        || str_starts_with($trimmed, '//')
        || str_starts_with($trimmed, '/*')
        || str_starts_with($trimmed, '#')
        || str_contains($line, '{{--');
}

if ($offenders !== []) {
    fwrite(STDERR, "Italian words in an English text:\n  ".implode("\n  ", $offenders)."\n");
    fwrite(STDERR, "Say it in English: the package does not speak the language of any host.\n");

    exit(1);
}

fwrite(STDOUT, "No Italian words in comments and pages.\n");

exit(0);
