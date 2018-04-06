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

    $config = $this->package('global')->config('settings');

    if (isset($config['history']['file']) && $config['history']['file']) {

        //generate uniq file name
        $filename = strtotime(date('Y-m-d h:i:s')) + rand();

        // force it, if history path is empty
        if (empty($config['history']['path'])) {
            $config['history']['path'] = 'log';
        }

        $path = $this->package('global')->path('root') . '/' . $config['history']['path'];

        // we are already expecting a log/ during creation of project
        // if directory is writable
        if (!is_writable($path)) {
            chmod($path, 0777); // make it writable
        }

        // get request and response as content
        $content = [
            'request' => $request->get(),
            'response' => $response->get(),
        ];

        // as the name says, put contents in a file
        file_put_contents(
            $filename . '.json',
            json_encode($content)
        );

        // sample expected: 1234567890.json
        $completeFilename = sprintf('%s.json', $filename);
    }

    //record logs
    $logRequest->setStage('history_meta', $completeFilename);
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

