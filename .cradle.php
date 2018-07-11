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
use Cradle\Package\System\Schema;

$this->addLogger(function($message, $request = null, $response = null) {
    if (!$request) {
        echo $message . PHP_EOL;
        return;
    }

    $logRequest = Request::i()->load();
    $logResponse = Response::i()->load();

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

        default:
            $type =  null;
            break;
    }

    //record logs
    $logRequest
        ->setStage('history_remote_address', $request->getServer('REMOTE_ADDR'))
        ->setStage('profile_id', $request->getSession('me', 'profile_id'))
        ->setStage('history_page', $request->getServer('REQUEST_URI'))
        ->setStage('history_activity', $message)
        ->setStage('history_type', $type);

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

    $activity = $this
        ->package('global')
        ->config('packages', 'cradlephp/cradle-activity');

    // if there's an activity package
    // and the package is active
    // and there's a schema involved
    // and schema's primary id was given
    // then we create an attached activity for it
    if ($activity
        && $activity['active']
        && $request->getStage('schema')
        && $response->getResults(
            Schema::i($request
                ->getStage('schema'))
                ->getPrimaryFieldName()
            )
    ) {
        $activityRequest = Request::i()->load();
        $activityResponse = Response::i()->load();

        $results = $logResponse->getResults();

        $activityRequest->setStage('history_id', $results['history_id']);
        $activityRequest->setStage('activity_schema', $request->getStage('schema'));
        $activityRequest->setStage(
            'activity_schema_primary',
            $response->getResults(
            Schema::i($request
                ->getStage('schema'))
                ->getPrimaryFieldName()
            )
        );

        $this->trigger('activity-create', $activityRequest, $activityResponse);
    }
});

/**
 * Add Template Builder
 */
$this->package('cradlephp/cradle-history')->addMethod('template', function (
    $file,
    array $data = [],
    $partials = [],
    $customFileRoot  = null,
    $customPartialsRoot = null
) {
    // get the root directory
    $root =  $customFileRoot;
    $partialRoot = $customPartialsRoot;
    $originalRoot =  sprintf('%s/src/template/', __DIR__);

    if (!$customFileRoot) {
        $root = $originalRoot;
    }

    if (!$customPartialRoot) {
        $partialRoot = $originalRoot;
    }

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

        $paths[$partial] = $partialRoot . $path;
    }

    $file = $root . $file . '.html';

    //render
    return cradle('global')->template($file, $data, $paths);
});
