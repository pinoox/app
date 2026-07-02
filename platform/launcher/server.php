<?php

/**
 * Router script for PHP's built-in development server (php pinx dev / pincore serve).
 */

use Pinoox\Component\Server\FrontController;

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$documentRoot = rtrim(str_replace('\\', '/', (string) getcwd()), '/');

require_once $documentRoot . '/vendor/autoload.php';

$inspectorEnabled = (string) getenv('PINX_INSPECTOR_ENABLED') === '1';
$inspectorRoute = rtrim((string) (getenv('PINX_INSPECTOR_ROUTE') ?: '/~inspector'), '/');
$inspectorRouter = (string) getenv('PINX_INSPECTOR_ROUTER');

if ($inspectorEnabled && $inspectorRouter !== '' && is_file($inspectorRouter) && ($uri === $inspectorRoute || str_starts_with($uri, $inspectorRoute . '/'))) {
    $_SERVER['PINX_INSPECTOR_PROJECT_ROOT'] = $documentRoot;
    $_SERVER['PINX_INSPECTOR_BASE_PATH'] = $inspectorRoute;
    require $inspectorRouter;

    return;
}

$target = $documentRoot . ($uri === '/' ? '' : $uri);

if (FrontController::shouldRoute($uri, $documentRoot)) {
    FrontController::applyServerGlobals($uri);
    if ($inspectorEnabled) {
        ob_start();
    }
    require $documentRoot . '/index.php';
    if ($inspectorEnabled) {
        echo pinx_inspector_inject_widget((string) ob_get_clean(), $inspectorRoute);
    }

    return;
}

if ($uri !== '/' && $uri !== '' && is_file($target)) {
    return false;
}

if ($inspectorEnabled) {
    ob_start();
}

require $documentRoot . '/index.php';

if ($inspectorEnabled) {
    echo pinx_inspector_inject_widget((string) ob_get_clean(), $inspectorRoute);
}

function pinx_inspector_inject_widget(string $html, string $inspectorRoute): string
{
    if ($html === '' || stripos($html, '</body>') === false || stripos($html, '<html') === false) {
        return $html;
    }

    if (!class_exists(\Pinoox\PinxInspector\WidgetRenderer::class)) {
        return $html;
    }

    $widget = \Pinoox\PinxInspector\WidgetRenderer::render($inspectorRoute);

    return preg_replace('/<\/body>/i', $widget . '</body>', $html, 1) ?? $html;
}
