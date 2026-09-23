<?php

/**
 * Reports the public classes of the package that no documentation page mentions.
 *
 * The documentation site is built from the markdown under `docs/`, so the only thing that keeps
 * it honest is measuring it: a refactoring that splits a trait into seven and adds ten services
 * leaves the pages describing yesterday's shape, and nothing complains. This is the complaint.
 *
 *   composer docs:coverage              the report
 *   composer docs:coverage -- --json    the same, as JSON
 *   composer docs:coverage -- --fail    exit 1 when anything is undocumented (a gate)
 *   composer docs:coverage -- --max=20  exit 1 above a tolerated number (the ratchet)
 *
 * A class that is genuinely internal — a page of the administration panel, a relation manager —
 * belongs in the report anyway: the point is to see it, not to guess it.
 */
$arguments = $argv;
array_shift($arguments);

$asJson = in_array('--json', $arguments, true);
$fail = in_array('--fail', $arguments, true);
$max = null;

foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--max=')) {
        $max = (int) substr($argument, 6);
    }
}

/**
 * @return array<string, string> short name => path relative to src/
 */
function declarations(string $root): array
{
    $declarations = [];
    $pattern = '/^(?:final |abstract |readonly )*(?:class|interface|trait|enum) (\w+)/m';

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());

        if (preg_match($pattern, $contents, $matches) === 1) {
            $declarations[$matches[1]] = str_replace($root.'/', '', $file->getPathname());
        }
    }

    ksort($declarations);

    return $declarations;
}

/**
 * The pages of the documentation, as one text.
 */
function documentation(string $root): string
{
    $text = '';

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $text .= (string) file_get_contents($file->getPathname())."\n";
        }
    }

    return $text;
}

$declarations = declarations('src');
$documentation = documentation('docs');

$documented = [];
$undocumented = [];

foreach ($declarations as $name => $path) {
    // A name counts as documented when a page writes it as a whole word — `StateService` but not
    // `WorkflowStateServiceHelper` — and a fully qualified mention counts too:
    // `RoBYCoNTe\FilamentFlow\Services\StateService` names the class just as well.
    if (preg_match('/(?<![\w])'.preg_quote($name, '/').'(?![\w])/u', $documentation) === 1) {
        $documented[$name] = $path;
    } else {
        $undocumented[$name] = $path;
    }
}

$total = count($declarations);
$covered = count($documented);
$missing = count($undocumented);
$percentage = $total === 0 ? 0 : (int) round($covered / $total * 100);

if ($asJson) {
    echo json_encode([
        'total' => $total,
        'documented' => $covered,
        'undocumented' => $missing,
        'percentage' => $percentage,
        'classes' => $undocumented,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
} else {
    echo "Documentation coverage: {$covered} of {$total} public classes mentioned in docs/ ({$percentage}%).\n\n";

    if ($undocumented !== []) {
        echo "Not mentioned by any page, grouped by area:\n";

        $byArea = [];

        foreach ($undocumented as $name => $path) {
            $area = str_contains($path, '/') ? dirname($path) : '.';
            $byArea[$area][] = $name;
        }

        ksort($byArea);

        foreach ($byArea as $area => $names) {
            echo sprintf("  %-52s %d\n", $area, count($names));
        }

        echo "\nUndocumented classes:\n";

        foreach ($undocumented as $name => $path) {
            echo sprintf("  %-46s %s\n", $name, $path);
        }
    }
}

if ($fail && $missing > 0) {
    fwrite(STDERR, "\n{$missing} public class(es) no page mentions.\n");

    exit(1);
}

if ($max !== null && $missing > $max) {
    fwrite(STDERR, "\n{$missing} public class(es) no page mentions, above the tolerated {$max}.\n");

    exit(1);
}

exit(0);
