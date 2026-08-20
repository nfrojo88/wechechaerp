<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 */

if (isset($_SERVER['REQUEST_URI']) && (strpos($_SERVER['REQUEST_URI'], 'index.php') !== false || strpos($_SERVER['REQUEST_URI'], '/public') === 0)) {
    $uri = $_SERVER['REQUEST_URI'];
    $uri = preg_replace('#^/(public/)?(index\.php/?)?#i', '/', $uri);
    if (empty($uri) || $uri[0] !== '/') {
        $uri = '/' . $uri;
    }
    $_SERVER['REQUEST_URI'] = $uri;
}

require_once __DIR__.'/public/index.php';
