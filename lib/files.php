<?php
declare(strict_types=1);

function atomic_file_write(string $path, string $bytes): void {
    $tmp = tempnam(dirname($path), '.genstat-');
    if ($tmp === false) throw new RuntimeException('Cannot create temporary file.');
    try {
        if (file_put_contents($tmp, $bytes) !== strlen($bytes)) throw new RuntimeException('Cannot write complete file.');
        if (!rename($tmp, $path)) throw new RuntimeException('Cannot replace file: '.basename($path));
    } finally {
        if (is_file($tmp)) unlink($tmp);
    }
}

function json_bytes(array $data): string {
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
}
