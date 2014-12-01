<?php
namespace application;

/**
 * fat-free framework application initialisation
 *
 * @package fatfree framework boilerplate
 * @author Vijay Mahrra <vijay.mahrra@gmail.com>
 * @copyright (c) Copyright 2013 Vijay Mahrra
 * @license GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
 */

$f3 = require_once('../vendor/bcosca/fatfree/lib/base.php');

// read config and overrides
// @see http://fatfreeframework.com/framework-variables#configuration-files
$f3->config('config/default.ini');
if (file_exists('config/config.ini'))
    $f3->config('config/config.ini');

// setup class autoloader
// @see http://fatfreeframework.com/quick-reference#autoload
$f3->set('AUTOLOAD', __dir__.';../vendor/bcosca/fatfree/lib/;classes/;../vendor/');

// custom error handler if debugging
$debug = $f3->get('DEBUG');
    // default error pages if site is not being debugged
if (PHP_SAPI !== 'cli' && empty($debug)) {
    $f3->set('ONERROR',
        function() use($f3) {
            header('Expires:  ' . \helpers\time::http(time() + $f3->get('error.ttl')));
            if ($f3->get('ERROR.code') == '404') {
                include_once 'ui/views/error/404.phtml';
            } else {
                include_once 'ui/views/error/error.phtml';
            }
        }
    );
}

// setup application logging
$logger = new \Log($f3->get('application.logfile'));
\Registry::set('logger', $logger);

// setup database connection params
// @see http://fatfreeframework.com/databases
if ($f3->get('db.driver') == 'sqlite') {
    $dsn = $f3->get('db.dsn');
    $dsn = substr($dsn, 0, strpos($dsn, '/')) . realpath('../') . substr($dsn, strpos($dsn, '/'));
    $db = new \DB\SQL($dsn);
    // attach any other sqlite databases - this example uses the full pathname to the db
    if ($f3->exists('db.sqlite.attached')) {
        $attached = $f3->get('db.sqlite.attached');
        $st = $db->prepare('ATTACH :filename AS :dbname');
        foreach ($attached as $dbname => $filename) {
            $st->execute(array(':filename' => $filename, ':dbname' => $dbname));
        }
    }    
} else {
    if (!$f3->get('db.dsn')) {
        $f3->set('db.dsn', sprintf("%s:host=%s;port=%d;dbname=%s",
            $f3->get('db.driver'), $f3->get('db.hostname'), $f3->get('db.port'), $f3->get('db.name'))
        );
    }
    $db = new \DB\SQL($f3->get('db.dsn'), $f3->get('db.username'), $f3->get('db.password'));
}
\Registry::set('db', $db);


// setup routes
// @see http://fatfreeframework.com/routing-engine
// firstly load routes from ini file
$f3->config('config/routes.ini');

$f3->run();

// log script execution time if debugging
if ($debug || $f3->get('application.environment') == 'development') {
    // log database transactions if level 3
    if ($debug == 3) {
        $logger->write(\Registry::get('db')->log());
    }
    $execution_time = round(microtime(true) - $f3->get('TIME'), 3);
    $logger->write('Script executed in ' . $execution_time . ' seconds using ' . round(memory_get_usage() / 1024 / 1024, 2) . '/' . round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB memory/peak');
}
