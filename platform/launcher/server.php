<?php

/**
 * Router script for PHP's built-in development server (php pinx dev / pincore serve).
 */

use Pinoox\Component\Server\FrontController;

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$documentRoot = rtrim(str_replace('\\', '/', (string) getcwd()), '/');

require_once $documentRoot . '/vendor/autoload.php';

$studioEnabled = (string) getenv('PINX_STUDIO_ENABLED') === '1';
$studioRoute = rtrim((string) (getenv('PINX_STUDIO_ROUTE') ?: '/~studio'), '/');
$studioRouter = (string) getenv('PINX_STUDIO_ROUTER');

if ($studioEnabled && $studioRouter !== '' && is_file($studioRouter) && ($uri === $studioRoute || str_starts_with($uri, $studioRoute . '/'))) {
    $_SERVER['PINX_STUDIO_PROJECT_ROOT'] = $documentRoot;
    $_SERVER['PINX_STUDIO_BASE_PATH'] = $studioRoute;
    require $studioRouter;

    return;
}

$target = $documentRoot . ($uri === '/' ? '' : $uri);

if (FrontController::shouldRoute($uri, $documentRoot)) {
    FrontController::applyServerGlobals($uri);
    if ($studioEnabled) {
        ob_start();
    }
    require $documentRoot . '/index.php';
    if ($studioEnabled) {
        echo pinx_studio_inject_widget((string) ob_get_clean(), $studioRoute);
    }

    return;
}

if ($uri !== '/' && $uri !== '' && is_file($target)) {
    return false;
}

if ($studioEnabled) {
    ob_start();
}

require $documentRoot . '/index.php';

if ($studioEnabled) {
    echo pinx_studio_inject_widget((string) ob_get_clean(), $studioRoute);
}

function pinx_studio_inject_widget(string $html, string $studioRoute): string
{
    if ($html === '' || stripos($html, '</body>') === false || stripos($html, '<html') === false) {
        return $html;
    }

    $route = htmlspecialchars($studioRoute, ENT_QUOTES, 'UTF-8');
    $widget = <<<HTML
<script>
(function () {
  if (window.__PINX_STUDIO_WIDGET__) return;
  window.__PINX_STUDIO_WIDGET__ = true;
  var a = document.createElement('a');
  a.href = '{$route}';
  a.target = '_blank';
  a.rel = 'noreferrer';
  a.title = 'Open Pinx Studio';
  a.textContent = 'P';
  a.style.cssText = 'position:fixed;left:16px;bottom:16px;z-index:2147483647;width:42px;height:42px;border-radius:999px;background:#0b7a75;color:#fff;display:flex;align-items:center;justify-content:center;text-decoration:none;font:700 18px system-ui,-apple-system,Segoe UI,sans-serif;box-shadow:0 10px 28px rgba(15,23,42,.24);border:1px solid rgba(255,255,255,.35)';
  document.addEventListener('DOMContentLoaded', function () { document.body.appendChild(a); });
})();
</script>
HTML;

    return preg_replace('/<\/body>/i', $widget . '</body>', $html, 1) ?? $html;
}
