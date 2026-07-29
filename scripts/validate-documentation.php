<?php

declare(strict_types=1);

use Assist\Tools\Documentation\DocumentationChangeValidator;

require __DIR__ . '/lib/DocumentationChangeValidator.php';

/** @return never */
function documentationUsage(string $message = ''): void
{
    if ($message !== '') {
        fwrite(STDERR, $message . PHP_EOL . PHP_EOL);
    }
    fwrite(STDERR, "Usage: php scripts/validate-documentation.php --base-ref REF [--head-ref REF]\n");
    fwrite(STDERR, "       php scripts/validate-documentation.php --changed-files FILE\n");
    exit(2);
}

/** @return array{base-ref?:string,head-ref?:string,changed-files?:string} */
function documentationArguments(array $arguments): array
{
    $parsed = [];
    for ($index = 1; $index < count($arguments); $index++) {
        $argument = $arguments[$index];
        if (!in_array($argument, ['--base-ref', '--head-ref', '--changed-files'], true)) {
            documentationUsage('Unknown argument: ' . $argument);
        }
        if (!isset($arguments[$index + 1]) || str_starts_with($arguments[$index + 1], '--')) {
            documentationUsage('Missing value for ' . $argument);
        }
        $parsed[substr($argument, 2)] = $arguments[++$index];
    }

    return $parsed;
}

/** @return list<string> */
function documentationGitDiff(string $base, string $head): array
{
    foreach ([$base, $head] as $reference) {
        if (preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $reference) !== 1) {
            documentationUsage('Invalid Git reference: ' . $reference);
        }
    }

    $command = ['git', 'diff', '--name-only', '--diff-filter=ACMR', $base, $head, '--'];
    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        documentationUsage('Unable to start git diff.');
    }
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0 || !is_string($output)) {
        documentationUsage('Unable to read changed files: ' . trim((string) $error));
    }

    return preg_split('/\R/', trim($output), -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

$cliArguments = [];
foreach ($_SERVER['argv'] ?? [] as $argument) {
    if (is_string($argument)) {
        $cliArguments[] = $argument;
    }
}
$arguments = documentationArguments($cliArguments);
if (isset($arguments['changed-files'])) {
    $contents = file_get_contents($arguments['changed-files']);
    if (!is_string($contents)) {
        documentationUsage('Unable to read changed-files input.');
    }
    $changedFiles = preg_split('/\R/', trim($contents), -1, PREG_SPLIT_NO_EMPTY) ?: [];
} elseif (isset($arguments['base-ref'])) {
    $changedFiles = documentationGitDiff($arguments['base-ref'], $arguments['head-ref'] ?? 'HEAD');
} else {
    documentationUsage('A base reference or changed-files input is required.');
}

$validator = new DocumentationChangeValidator();
$errors = $validator->validate($changedFiles);
if ($errors !== []) {
    fwrite(STDERR, "Documentation governance failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Documentation governance passed (%d changed file%s checked).\n",
    count($changedFiles),
    count($changedFiles) === 1 ? '' : 's'
));
