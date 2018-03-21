<?php //-->
/**
 * This file is part of a Custom Package.
 */

use Cradle\Package\System\Schema;
use Cradle\Package\System\Exception;

use Cradle\Http\Request;
use Cradle\Http\Response;

/**
 * Creates a history
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('history-create', function ($request, $response) {
    //set history as schema
    $request->setStage('schema', 'history');

    //trigger model create
    $this->trigger('system-model-create', $request, $response);
});

/**
 * Creates a history
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('history-detail', function ($request, $response) {
    //set history as schema
    $request->setStage('schema', 'history');

    //trigger model detail
    $this->trigger('system-model-detail', $request, $response);
});

/**
 * Removes a history
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('history-remove', function ($request, $response) {
    //set history as schema
    $request->setStage('schema', 'history');

    //trigger model remove
    $this->trigger('system-model-remove', $request, $response);
});

/**
 * Restores a history
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('history-restore', function ($request, $response) {
    //set history as schema
    $request->setStage('schema', 'history');

    //trigger model restore
    $this->trigger('system-model-restore', $request, $response);
});

/**
 * Searches history
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('history-search', function ($request, $response) {
    //set history as schema
    $request->setStage('schema', 'history');

    //trigger model search
    $this->trigger('system-model-search', $request, $response);
});

/**
 * Updates a history
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('history-update', function ($request, $response) {
    //set history as schema
    $request->setStage('schema', 'history');

    //trigger model update
    $this->trigger('system-model-update', $request, $response);
});

/**
 * Updates a history
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('history-mark-as-read', function ($request, $response) {
    //search all unread history
    $request->setStage('filter', 'history_flag', 0);
    $request->setStage('nocache', 1);
    cradle()->trigger('history-search', $request, $response);

    $logs = $response->getResults();

    if (empty($logs['rows'])) {
        return $response->setError(true, 'No new notification');
    }

    //load schema
    $schema = Schema::i($request->getStage('schema'));

    //get primary
    $primary = $schema->getPrimaryFieldName();

    //set payload
    $payload = [];

    //update request and response
    $updateRequest = Request::i()->load();
    $updateResponse = Response::i()->load();

    foreach ($logs['rows'] as $key => $log) {
        $payload[$primary] = $log[$primary];
        $payload['history_flag'] = 1;
        
        $updateRequest->setStage($payload);
        $this->trigger('history-update', $updateRequest, $updateResponse);

        $results[] = $updateResponse->getResults();
    }

    $response->setError(false)->setResults($results);
});
