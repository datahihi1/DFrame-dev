<?php

/*
| Basic Configuration
|--------------------------------------------------------------------------
| This file serves as the entry point for the DFrame web application.
| It sets up the environment, handles autoloading, and initializes the application.
*/

ob_start();
define('D_RUN', microtime(true));

/*
| Define ROOT_DIR (Base of the framework)
|------------------------------------------------------------------------------------------------
| This defines the root directory of the application.
| It checks if the directory exists and is readable.
| If not, it returns a 500 error.
|------------------------------------------------------------------------------------------------
*/
if (!defined('ROOT_DIR')) {
    $rootDir = dirname(__DIR__) . DIRECTORY_SEPARATOR;
    if (!is_dir($rootDir) || !is_readable($rootDir)) {
        http_response_code(500);
        die('Application root directory not accessible');
    }
    /** Define the root directory constant of DFrame application */
    define('ROOT_DIR', $rootDir);
}

/*
| Define INDEX_DIR - Base of entry file (index.php)
|------------------------------------------------------------------------------------------------
| This defines the index directory of the application.
| It checks if the directory exists and is readable.
| If not, it returns a 500 error.
|------------------------------------------------------------------------------------------------
*/
if (!defined('INDEX_DIR')) {
    $indexDir = __DIR__ . DIRECTORY_SEPARATOR;
    if (!is_dir($indexDir) || !is_readable($indexDir)) {
        http_response_code(500);
        die('Index directory not accessible');
    }
    /** Define the index directory constant of DFrame application */
    define('INDEX_DIR', $indexDir);
}

/*
| Autoloading
|------------------------------------------------------------------------------------------------
| This loads the Composer autoloader to include all dependencies.
| If the autoloader is not found, it returns a 500 error.
|------------------------------------------------------------------------------------------------
*/
$autoloadFile = ROOT_DIR . '/vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    http_response_code(500);
    die(file_get_contents('source/miss.html') ?: 'Autoloader not found. Please run <code>composer install</code>.');
}
require_once $autoloadFile;

/*
| Set Maintenance Mode if needed
|------------------------------------------------------------------------------------------------
| This checks the environment variable to determine if maintenance mode should be enabled.
*/

// \DFrame\Application\App::setMaintenanceMode(true);

/*
| Initialize and boot the DFrame web application
|------------------------------------------------------------------------------------------------
| This sets up the application environment and prepares it for web requests.
| After initialization, it boots the application to handle incoming requests.
|------------------------------------------------------------------------------------------------
*/

// script.php
use DFrame\Reports\Report;
Report::setup(true, INDEX_DIR . '/logs/html.log', Report::html());

\DFrame\Application\App::initialize()->bootWeb();
