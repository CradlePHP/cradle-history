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

    $meta = [];
    if (isset($config['history']['file']) && $config['history']['file']) {
        //To Enable
        //Insert a new settings for history
        // Ex: 'history' => [
        //      'file' => true,
        //      'path' => 'some/path/under/config'
        //      ];
        // Note: Make sure folder permission is 777 of the folders

        //generate uniq file name
        $filename = strtotime(date('Y-m-d h:i:s')) + rand();

        //change if history path is empty
        if (empty($config['history']['path'])) {
            $config['history']['path'] = 'log';
        }

        $path = $this->package('global')->path('config') . '/' . $config['history']['path'];

        //if not existing create the folder
        if (!is_dir($path)) {
            mkdir($path, 0777);
        }

        $meta = [
            'request' => $request->get(),
            'response' => $response->get(),
        ];

        file_put_contents(
            $path . '/' . $filename . '.json',
            json_encode($meta)
        );

        //set the new meta
        $meta = [
            'log_file' => sprintf('%s/%s.json', $config['history']['path'], $filename)
        ];
    }

    //if history setting is not set (default)
    if (!isset($meta['log_file'])) {
        //get meta
        $data['results'] = $response->hasResults() ? $response->getResults() : '';
        $data['stage'] = $request->hasStage() ? $request->getStage() : '';
        $data['post'] = $request->hasPost() ? $request->getPost() : '';
        $body = !empty($request->getBody()) ? $request->getBody(): '';

        if (strlen($body) > 300) {
            $body = '< DATA TOO LONG >';
        }

        //case for long string and data
        foreach ($data as $keyword => $result) {
            if (is_array($result)) {
                foreach ($result as $key => $row) {
                    if (is_array($row)) {
                        if (count($row) > 100) {
                            $data[$keyword][$key] = '< DATA TOO LONG >';
                            continue;
                        }

                        foreach ($row as $id => $value) {
                            if (is_array($value)) {
                                $value = json_encode($value);
                            }

                            if (strlen($value) > 300) {
                                $data[$keyword][$key][$id] = '< DATA TOO LONG >';
                            }
                        }

                        continue;
                    }

                    if (strlen($row) > 300) {
                        $data[$keyword][$key] = '< DATA TOO LONG >';
                    }
                }

                continue;
            }

            if (strlen($result) > 300) {
                $data[$keyword] = '< DATA TOO LONG >';
            }
        }

        //then reset
        $request->setStage($data['stage']);
        $request->setPost($data['post']);
        $request->setBody($body);
        $response->setError(false)->setResults($data['results']);

        $meta = [
            'request' => $request->get(),
            'response' => $response->get(),
        ];
    }

    //record logs
    $logRequest->setStage('history_meta', $meta);
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

