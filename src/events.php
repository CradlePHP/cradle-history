<?php //-->
/**
 * This file is part of a Custom Package.
 */

use Cradle\Package\System\Schema;

/**
 * Add History Logs
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('system-model-detail', function ($request, $response) {
    //history only
    if ($request->getStage('schema') !== 'history') {
        return;
    }

    //can we view ?
    if ($response->isError()) {
        return;
    }

    // is there a path?
    if (!$response->getResults('history_path')) {
        return;
    }

    // get the log path root
    $root = $this->package('global')->config('settings', 'log_path');

    // if no log path
    if (!trim($root)) {
        // default log path
        $root = 'log';
    }

    // case for relative path
    if (strpos($root, '/') !== 0) {
        $root = $this->package('global')->path('root') . '/' . $root;
    }

    // get the history log file
    $path = $root . '/' . $response->getResults('history_path');

    // does the file exist?
    if (!file_exists($path)) {
        return;
    }

    $contents = file_get_contents($path);

    // set history meta
    $response->setResults('history_meta', json_decode($contents, true));
});

/**
 * Get Schema History Versions
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('history-schema-versions', function ($request, $response) {
    //----------------------------//
    // 1. Prepare Data
    $payload = $this->makePayload();

    $payload['request']
        ->setStage('schema', 'history')
        ->setStage(
            'history_id',
            $request->getStage('history_id')
        );

    //get the original table row
    $this->trigger(
        'system-model-detail',
        $payload['request'],
        $payload['response']
    );

    //can we view ?
    if ($payload['response']->isError()) {
        return;
    }

    if (!$payload['response']->hasResults('history_meta')) {
        return $response->setError(true, 'No log data found.');
    }

    $meta = $payload['response']->getResults('history_meta');

    if (//the request and response are not set
        !isset(
            $meta['request']['stage']['singular'],
            $meta['request']['stage']['plural'],
            $meta['request']['stage']['name'],
            $meta['request']['stage']['icon'],
            $meta['request']['stage']['detail'],
            $meta['request']['stage']['fields'],
            $meta['request']['stage']['suggestion'],
            $meta['response']['json']['results']['original']
        )
    ) {
        return $response->setError(true, 'Not enough log data given.');
    }

    try {
        //process the history item (ie. post, product, etc.)
        $schema = $meta['request']['stage']['name'];
        $schema = Schema::i($schema)->getAll();
    } catch (Exception $e) {
        return $response->setError(true, $e->getMessage());
    }

    //if theres no primary id in the original
    $results = $meta['response']['json']['results'];
    $original = $results['original'];

    $current = [];
    //match the current with the original layout
    foreach ($results as $key => $value) {
        if (isset($original[$key])) {
            $current[$key] = $value;
        }
    }

    $history = $payload['response']->getResults();

    $response
        ->setError(false)
        ->setResults([
            'history' => $history,
            'schema' => $schema,
            'current' => $current,
            'original' => $original
        ]);
});

/**
 * Get Model History Versions
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('history-model-versions', function ($request, $response) {
    //----------------------------//
    // 1. Prepare Data
    $payload = $this->makePayload();

    $payload['request']
        ->setStage('schema', 'history')
        ->setStage(
            'history_id',
            $request->getStage('history_id')
        );

    //get the original table row
    $this->trigger(
        'system-model-detail',
        $payload['request'],
        $payload['response']
    );

    //can we view ?
    if ($payload['response']->isError()) {
        return;
    }

    if (!$payload['response']->hasResults('history_meta')) {
        return $response->setError(true, 'No log data found.');
    }

    $meta = $payload['response']->getResults('history_meta');

    if (//the request and response are not set
        !isset(
            $meta['request']['stage']['schema'],
            $meta['response']['json']['results']['original']
        )
    ) {
        return $response->setError(true, 'Not enough log data given.');
    }

    try {
        //process the history item (ie. post, product, etc.)
        $schema = $meta['request']['stage']['schema'];
        $schema = Schema::i($schema)->getAll();
    } catch (Exception $e) {
        $schema = false;
    }

    //if theres no primary id in the original
    $results = $meta['response']['json']['results'];
    $original = $results['original'];

    $current = [];
    //match the current with the original layout
    foreach ($results as $key => $value) {
        if (isset($original[$key])) {
            $current[$key] = $value;
        }
    }

    $history = $payload['response']->getResults();

    $response->setResults([
        'history' => $history,
        'schema' => $schema,
        'current' => $current,
        'original' => $original
    ]);
});

/**
 * Updates a history
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('history-mark-as-read', function ($request, $response) {
    //search all unread history
    $request
        ->setStage('schema', 'history')
        ->setStage('filter', 'history_flag', 0)
        ->setStage('range', 0);

    $this->trigger('system-model-search', $request, $response);

    if (!$response->getResults('total')) {
        return $response->setError(true, 'No new notification');
    }

    //just mark it all as read
    Schema::i('history')
        ->model()
        ->service('sql')
        ->getResource()
        ->updateRows('history', [
            'history_flag' => 1
        ], 'true');
});
