<?php //-->
/**
 * This file is part of a package designed for the CradlePHP Project.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */
require_once __DIR__ . '/package/events.php';
require_once __DIR__ . '/package/helpers.php';
require_once __DIR__ . '/src/events.php';
require_once __DIR__ . '/src/controller.php';

use Cradle\Package\System\Schema;
use Cradle\Http\Request\RequestInterface;
use Cradle\Http\Response\ResponseInterface;

$this->addLogger(function(
    $message,
    $request = null,
    $response = null,
    $type = null,
    $table = null,
    $id = null
) {
    //argument test
    if (!is_string($message)
        || !($request instanceof RequestInterface)
        || !($response instanceof ResponseInterface)
    ) {
        reutrn;
    }

    // let's ignore CLI
    if (php_sapi_name() === 'cli') {
        echo $message . PHP_EOL;
        return;
    }

    if (is_null($type)) {
        switch (true) {
            case strpos($message, 'created') !== FALSE:
                $type = 'create';
                break;
            case strpos($message, 'updated') !== FALSE:
                $type = 'update';
                break;
            case strpos($message, 'restored') !== FALSE:
                $type = 'restore';
                break;
            case strpos($message, 'removed') !== FALSE:
                $type = 'remove';
                break;
            case strpos($message, 'imported') !== FALSE:
                $type = 'import';
                break;
        }
    }

    $payload = $this->makePayload();

    //record logs
    $payload['request']
        ->setStage('schema', 'history')
        ->setStage('history_remote_address', $request->getServer('REMOTE_ADDR'))
        ->setStage('profile_id', $request->getSession('me', 'profile_id'))
        ->setStage('history_page', $request->getServer('REQUEST_URI'))
        ->setStage('history_activity', $message)
        ->setStage('history_type', $type)
        ->setStage('history_table_name', $table)
        ->setStage('history_table_id', $id);

    //try to get the log path from settings
    $logPath = $this->package('global')->config('settings', 'log_path');

    // if log path is not set
    if (!$logPath) {
        // set default log path
        $logPath = $this->package('global')->path('root') . '/log';
    // if relative path
    } else if (strpos($logPath, '/') !== 0) {
        // set absolute path
        $logPath = $this->package('global')->path('root') . '/' . $logPath;
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
        $payload['request']->setStage('history_path', basename($filename));
    }

    $this->trigger(
        'system-model-create',
        $payload['request'],
        $payload['response']
    );
});
