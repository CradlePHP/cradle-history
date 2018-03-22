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

    //get data
    $result = $response->getResults();
    $stage = $request->getStage();
    $post = $request->getPost();
    $body = $request->getBody();

    parse_str($body, $body);

    //change variables and check if string is too long
    foreach ($result as $key => $value) {
        if (!is_array($result[$key])) {
            if (isset($stage[$key])) {
                $stage[$key] = $value;

                if (!is_array($result[$key]) && strlen($stage[$key]) > 300) {
                    $stage[$key] = '< DATA TOO LONG >';
                }
            }

            if (isset($post[$key])) {
                $post[$key] = $value;

                if (!is_array($result[$key]) && strlen($post[$key]) > 300) {
                    $post[$key] = '< DATA TOO LONG >';
                }
            }

            if (isset($body[$key])) {
                $body[$key] = $value;

                if (!is_array($result[$key]) && strlen($body[$key]) > 300) {
                    $body[$key] = '< DATA TOO LONG >';
                }
            }

            if (strlen($result[$key]) > 300) {
                $result[$key] = '< DATA TOO LONG >';
            }

            continue;
        }

        $result[$key] = json_encode($result[$key]);

        if (strlen($result[$key]) > 300) {
            $result[$key] = '< DATA TOO LONG >';
        }
    }

    //then reset
    $request->setStage($stage);
    $request->setPost($post);
    $request->setBody(http_build_query($body));
    $response->setError(false)->setResults($result);

    //record logs
    $logRequest
        ->setStage('history_remote_address', $request->getServer('REMOTE_ADDR'))
        ->setStage('profile_id', $request->getSession('me', 'profile_id'))
        ->setStage('history_page', $request->getServer('REQUEST_URI'))
        ->setStage('history_activity', $message)
        ->setStage('history_flag', 0)
        ->setStage('history_meta', [
            'request' => $request->get(),
            'response' => $response->get(),
        ]);

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

