<?php //-->
/**
 * This file is part of a Custom Package.
 */
require_once __DIR__ . '/package/events.php';
require_once __DIR__ . '/src/events.php';
require_once __DIR__ . '/src/controller.php';
require_once __DIR__ . '/package/helpers.php';

use Cradle\Http\Request;
use Cradle\Http\Response;

$this->addLogger(function($message, $request, $response) {
    $logRequest = Request::i()->load();
    $logResponse = Response::i()->load();

    //record logs
    $logRequest
        ->setStage('history_remote_address', $request->getServer('REMOTE_ADDR'))
        ->setStage('profile_id', $request->getSession('me', 'profile_id'))
        ->setStage('history_page', $request->getServer('REQUEST_URI'))
        ->setStage('history_activity', $message)
        ->setStage('history_flag', 0);

    //try to get the log path from settings
    $logPath = $this->package('global')->config('settings', 'log_path');

    // if log path is not set
    if (!$logPath) {
        // set default log path
        $logPath = $this->package('global')->path('root') . '/log';
    } else {
        // if relative path
        if (strpos($logPath, '/') !== 0) {
            // set absolute path
            $logPath = $this->package('global')->path('root') . '/' . $logPath;
        }
    }

    //generate uniq file name
    $filename = sprintf('%s/%s.json', $logPath, md5(uniqid()));

    //if its not a directory
    if (!is_dir(dirname($filename))) {
        // create directory
        mkdir(dirname($filename), 0777);
    }

    //if directory is writable
    if (is_writable(dirname($filename))) {
        // as the name says, put contents in a file
        file_put_contents($filename, json_encode([
            'request' => $request->get(),
            'response' => $response->get(),
        ]));

        //record logs
        $logRequest->setStage('history_path', basename($filename));
    }    

    $this->trigger('history-create', $logRequest, $logResponse);
});

/**
 * Add Template Builder
 */
$this->package('cradlephp/cradle-history')->addMethod('template', function (
    $file,
    array $data = [],
    $partials = []
) {
    // get the root directory
    $root =  sprintf('%s/src/template/', __DIR__);

    // check for partials
    if (!is_array($partials)) {
        $partials = [$partials];
    }

    $paths = [];

    foreach ($partials as $partial) {
        //Sample: product_comment => product/_comment
        //Sample: flash => _flash
        $path = str_replace('_', '/', $partial);
        $last = strrpos($path, '/');

        if($last !== false) {
            $path = substr_replace($path, '/_', $last, 1);
        }

        $path = $path . '.html';

        if (strpos($path, '_') === false) {
            $path = '_' . $path;
        }

        $paths[$partial] = $root . $path;
    }

    $file = $root . $file . '.html';

    //render
    return cradle('global')->template($file, $data, $paths);
});
