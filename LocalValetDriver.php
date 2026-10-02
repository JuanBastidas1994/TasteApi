<?php

use Valet\Drivers\BasicValetDriver;

class LocalValetDriver extends BasicValetDriver
{
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        return file_exists($sitePath.'/index.php') && file_exists($sitePath.'/conexion.php');
    }

    public function isStaticFile(string $sitePath, string $siteName, string $uri)
    {
        if ($this->isActualFile($staticFilePath = $sitePath.$uri)) {
            return $staticFilePath;
        }

        return false;
    }

    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        $_SERVER['PATH_INFO'] = $uri === '/' ? '' : $uri;
        $_SERVER['SCRIPT_FILENAME'] = $sitePath.'/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['DOCUMENT_ROOT'] = $sitePath;

        return $sitePath.'/index.php';
    }
}
