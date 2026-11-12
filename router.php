<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (str_starts_with($path, '/admin/') || str_starts_with($path, '/app_admin/')) { require __DIR__.'/index.php'; return true; }
if ($path !== '/' && is_file(__DIR__.$path)) return false;
require __DIR__.'/index.php';
