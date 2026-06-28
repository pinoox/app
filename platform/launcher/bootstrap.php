<?php

/**
 * Boot via pincore (standalone single-app host).
 */
$projectRoot = dirname(__DIR__, 2);
$corePathResolver = __DIR__ . '/core-path.php';

if (is_file($corePathResolver)) {
    require_once $corePathResolver;
}

$corePath = function_exists('pinoox_resolve_configured_core_path')
    ? pinoox_resolve_configured_core_path($projectRoot)
    : $projectRoot . '/vendor/pinoox/pincore';

require_once rtrim($corePath, '/\\') . '/launcher/bootstrap.php';
