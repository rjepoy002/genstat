<?php
declare(strict_types=1);
// This operation is deliberately available only to the local PHP CLI.
if (PHP_SAPI !== 'cli') {http_response_code(403); exit('CLI only.');}
require_once __DIR__.'/../lib/template-refresh.php';
$root = dirname(__DIR__);
try {
    if ($argc !== 3 || !in_array($argv[1], ['--plan', '--apply'], true)) throw new InvalidArgumentException('Usage: php tools/refresh-template.php --plan storage/refresh-review.json | --apply storage/refresh-review.json');
    $file = $argv[2];
    if ($argv[1] === '--plan') {
        $resolved = realpath(dirname($file));
        $storage = realpath($root.'/storage');
        if ($resolved !== $storage || !preg_match('/^[a-zA-Z0-9_-]+\.json$/D', basename($file)) || str_starts_with(basename($file), 'draft-') || in_array(basename($file), ['refresh-in-progress.json'])) throw new InvalidArgumentException('Write review plans directly in storage/ using a filename other than draft-* or refresh-in-progress.json.');
        $report = refresh_review($root); atomic_file_write($file, json_bytes($report));
        echo 'Review written: '.$file.PHP_EOL.'Formula changes: '.count($report['formulaChanges']).'; input definition changes: '.count($report['inputDefinitionChanges']).'; conflicts: '.count($report['conflicts']).PHP_EOL;
        echo $report['canApply'] ? "Review the JSON, then use --apply with that file.\n" : "Apply is blocked until conflicts are explicitly resolved. Existing drafts and layout are unchanged.\n";
    } else {
        $review = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $result = refresh_apply($root, $review);
        echo 'Refresh applied. Migrated drafts: '.$result['migratedDrafts'].PHP_EOL.'Verified backup: '.$result['backup'].PHP_EOL;
    }
} catch (Throwable $e) {fwrite(STDERR, $e->getMessage().PHP_EOL); exit(1);}
