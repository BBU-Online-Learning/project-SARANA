<?php

/** Serves synthetic test renders and public assets only; never boots the application. */
$directory = getenv('UI_PREVIEW_DIRECTORY');
if (getenv('APP_ENV') !== 'testing' || ! $directory
    || ! preg_match('/^elearning_ui_[a-f0-9]{16}$/', basename($directory))
    || realpath(dirname($directory)) !== realpath(sys_get_temp_dir())) {
    http_response_code(403);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(css|js|backend|build)/[a-zA-Z0-9_./-]+$#', $path) && ! str_contains($path, '..')) {
    return false;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('#^/(super_admin|admin|teacher|student)-(home|profile-edit|chat-index|classes-index)\.html$#', $path)) {
    $file = $directory.$path;
    if (is_file($file)) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        readfile($file);
        exit;
    }
}
http_response_code(404);
echo 'Synthetic UI preview only. Live application actions are disabled.';
