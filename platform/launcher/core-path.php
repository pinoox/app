<?php

function pinoox_project_env_value(string $projectRoot, string $key): ?string
{
    $envFile = rtrim(str_replace('\\', '/', $projectRoot), '/') . '/.env';
    if (!is_file($envFile)) {
        return null;
    }

    foreach (file($envFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$envKey, $value] = explode('=', $line, 2);
        if (trim($envKey) !== $key) {
            continue;
        }

        return trim(trim($value), "\"'");
    }

    return null;
}

function pinoox_project_resolve_path(string $projectRoot, string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    $projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');

    if ($path === '~' || $path === '') {
        return $projectRoot;
    }

    if (str_starts_with($path, '~/')) {
        return $projectRoot . '/' . substr($path, 2);
    }

    if (str_starts_with($path, '../')) {
        return rtrim(str_replace('\\', '/', realpath($projectRoot . '/' . $path) ?: $projectRoot . '/' . $path), '/');
    }

    if (preg_match('/^[A-Za-z]:\//', $path) === 1 || str_starts_with($path, '/')) {
        return rtrim($path, '/');
    }

    return $projectRoot . '/' . $path;
}

function pinoox_resolve_configured_core_path(string $projectRoot): string
{
    $configured = getenv('PINOOX_CORE_PATH') ?: pinoox_project_env_value($projectRoot, 'PINOOX_CORE_PATH');
    if (is_string($configured) && $configured !== '') {
        return pinoox_project_resolve_path($projectRoot, $configured);
    }

    return rtrim(str_replace('\\', '/', $projectRoot), '/') . '/vendor/pinoox/pincore';
}

function pinoox_resolve_configured_pinx_cli_path(string $projectRoot): string
{
    $configured = getenv('PINX_CLI_PATH') ?: pinoox_project_env_value($projectRoot, 'PINX_CLI_PATH');
    if (is_string($configured) && $configured !== '') {
        return pinoox_project_resolve_path($projectRoot, $configured);
    }

    return rtrim(str_replace('\\', '/', $projectRoot), '/') . '/vendor/pinoox/pinx-cli';
}
