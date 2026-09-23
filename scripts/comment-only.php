<?php

/**
 * Fails when a documentation pass has moved the code.
 *
 * A pass that documents the package touches docblocks and nothing else, and this is what proves
 * it: every changed line of the diff must be a comment line, and no PHP file may have been added.
 *
 *   composer docs:comments                 the working tree against HEAD
 *   composer docs:comments src/Services    only under one path
 *   composer docs:comments --base main     since a branch point instead of the working tree
 *
 * Exit code 1 as soon as one line of code appears, so it can stand in a pipeline.
 */
$arguments = $argv;
array_shift($arguments);

$base = 'HEAD';
$paths = [];

for ($i = 0; $i < count($arguments); $i++) {
    if ($arguments[$i] === '--base' && isset($arguments[$i + 1])) {
        $base = $arguments[$i + 1];
        $i++;

        continue;
    }

    $paths[] = $arguments[$i];
}

// With no argument the whole repository; a directory means the PHP under it, a file means itself.
$specs = ['*.php'];

if ($paths !== []) {
    $specs = [];

    foreach ($paths as $path) {
        $specs[] = is_dir($path) ? rtrim($path, '/').'/*.php' : $path;
    }
}

function git(array $arguments): string
{
    $escaped = implode(' ', array_map('escapeshellarg', $arguments));
    $output = shell_exec('git '.$escaped.' 2>/dev/null');

    return $output === null ? '' : $output;
}

// A documentation pass does not add PHP files: new ones are code.
$added = array_filter(
    explode("\n", trim(git(array_merge(['ls-files', '--others', '--exclude-standard', '--'], $specs)))),
    static fn (string $line): bool => str_ends_with($line, '.php'),
);

if ($added !== []) {
    fwrite(STDERR, "New PHP files in the tree — a documentation pass must not add code:\n");
    fwrite(STDERR, '  '.implode("\n  ", $added)."\n");

    exit(1);
}

$diff = git(array_merge(['diff', $base, '-U0', '--'], $specs));
$offenders = [];

foreach (explode("\n", $diff) as $line) {
    if ($line === '' || $line[0] !== '+' && $line[0] !== '-') {
        continue;
    }

    if (str_starts_with($line, '+++') || str_starts_with($line, '---')) {
        continue;
    }

    // A changed line is a comment when it opens or continues one, or is empty.
    // The delimiter cannot be `#`: it is one of the comment markers being matched.
    if (preg_match('~^[+-]\s*(//|/\*|\*|#|$)~', $line) === 1) {
        continue;
    }

    $offenders[] = $line;
}

if ($offenders !== []) {
    fwrite(STDERR, "These changed lines are not comments:\n");
    fwrite(STDERR, '  '.implode("\n  ", array_slice($offenders, 0, 40))."\n");

    if (count($offenders) > 40) {
        fwrite(STDERR, '  ... and '.(count($offenders) - 40)." more.\n");
    }

    fwrite(STDERR, "The working tree touched code: a documentation pass must leave it still.\n");

    exit(1);
}

fwrite(STDOUT, "Only comments changed.\n");

exit(0);
