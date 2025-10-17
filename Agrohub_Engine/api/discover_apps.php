<?php
/**
 * Discover local apps under the apps/ directory.
 * Returns JSON array of discovered apps with minimal metadata.
 */
require_once 'config.php';
require_once 'utils.php';

header('Content-Type: application/json; charset=utf-8');

$appsDir = realpath(__DIR__ . '/../apps');
$result = [];

if ($appsDir && is_dir($appsDir)) {
    $entries = scandir($appsDir);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $appsDir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path)) {
            // Determine if app has index.php or index.html
            $hasIndexPhp = file_exists($path . DIRECTORY_SEPARATOR . 'index.php');
            $hasIndexHtml = file_exists($path . DIRECTORY_SEPARATOR . 'index.html');
            if ($hasIndexPhp || $hasIndexHtml) {
                // Build metadata (best-effort)
                $meta = [
                    'technical_name' => $entry,
                    'id' => $entry,
                    'name' => ucfirst(str_replace(['-', '_'], ' ', $entry)),
                    'description' => '',
                    'icon' => 'fas fa-th-large',
                    'category' => 'Website',
                    'color' => 'from-blue-400 to-blue-600',
                    'installed' => true,
                    'version' => '1.0'
                ];
                // Try to read a small manifest file if present
                $manifestPath = $path . DIRECTORY_SEPARATOR . 'module.json';
                if (file_exists($manifestPath)) {
                    $content = @file_get_contents($manifestPath);
                    if ($content) {
                        $m = json_decode($content, true);
                        if (is_array($m)) {
                            $meta['name'] = $m['name'] ?? $meta['name'];
                            $meta['description'] = $m['description'] ?? $meta['description'];
                            $meta['icon'] = $m['icon'] ?? $meta['icon'];
                            $meta['category'] = $m['category'] ?? $meta['category'];
                            $meta['color'] = $m['color'] ?? $meta['color'];
                            $meta['version'] = $m['version'] ?? $meta['version'];
                        }
                    }
                }
                $result[] = $meta;
            }
        }
    }
}

echo json_encode(['success' => true, 'data' => $result]);
exit();
