<?php
// api/index.php - Serverless Entrypoint & Request Router for Vercel

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = trim($path, '/');

$rootDir = dirname(__DIR__);

// Route mapping
if ($path === '' || $path === 'index.php') {
    require $rootDir . '/index.php';
    exit;
}

$targetFile = $rootDir . '/' . $path;

// If directory, look for index.php inside it
if (is_dir($targetFile)) {
    $targetFile = rtrim($targetFile, '/') . '/index.php';
}

if (file_exists($targetFile) && pathinfo($targetFile, PATHINFO_EXTENSION) === 'php') {
    chdir(dirname($targetFile));
    require $targetFile;
    exit;
}

http_response_code(404);
echo "<div style='font-family:sans-serif;padding:40px;text-align:center;'>";
echo "<h2>404 - Page Not Found</h2>";
echo "<p>The requested page <code>" . htmlspecialchars($path) . "</code> was not found on this server.</p>";
echo "<a href='/'>Return to Home</a>";
echo "</div>";
