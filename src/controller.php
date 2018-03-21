<?php //-->
/**
 * This file is part of a Custom Package.
 */

// Back End Controllers
use Cradle\Package\System\Schema;

/**
 * Renders a create form
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/history/detail/:history_id', function ($request, $response) {
    //----------------------------//
    //set redirect
    $request->setStage('redirect_uri', '/admin/history/search');

    //now let the object detail take over
    $this->routeTo(
        'get',
        sprintf(
            '/admin/system/object/history/detail/%s',
            $request->getStage('history_id')
        ),
        $request,
        $response
    );
});

/**
 * Renders a search page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/history/search', function ($request, $response) {
    //----------------------------//
    // 1. Prepare Data
    $schema = Schema::i('history');

    //if this is a return back from processing
    //this form and it's because of an error
    if ($response->isError()) {
        //pass the error messages to the template
        $response->setFlash($response->getMessage(), 'error');
    }

    //set a default range
    if (!$request->hasStage('range')) {
        $request->setStage('range', 50);
    }

    //filter possible filter options
    //we do this to prevent SQL injections
    if (is_array($request->getStage('filter'))) {
        foreach ($request->getStage('filter') as $key => $value) {
            //if invalid key format or there is no value
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key) || !strlen($value)) {
                $request->removeStage('filter', $key);
            }
        }
    }

    //filter possible sort options
    //we do this to prevent SQL injections
    if (is_array($request->getStage('order'))) {
        foreach ($request->getStage('order') as $key => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                $request->removeStage('order', $key);
            }
        }
    }

    //trigger job
    $this->trigger('history-search', $request, $response);

    //if we only want the raw data
    if ($request->getStage('render') === 'false') {
        return;
    }

    //form the data
    $data = array_merge(
        //we need to case for things like
        //filter and sort on the template
        $request->getStage(),
        //this is from the search event
        $response->getResults()
    );

    //also pass the schema to the template
    $data['schema'] = $schema->getAll();

    //if there's an active field
    if ($data['schema']['active']) {
        //find it
        foreach ($data['schema']['filterable'] as $i => $filter) {
            //if we found it
            if ($filter === $data['schema']['active']) {
                //remove it from the filters
                unset($data['schema']['filterable'][$i]);
            }
        }

        //reindex filterable
        $data['schema']['filterable'] = array_values($data['schema']['filterable']);
    }

    $data['filterable_relations'] = [];
    foreach ($data['schema']['relations'] as $relation) {
        if ($relation['many'] < 2) {
            $data['filterable_relations'][] = $relation;
        }
    }

    //determine valid relations
    $data['valid_relations'] = [];
    $this->trigger('system-schema-search', $request, $response);
    foreach ($response->getResults('rows') as $relation) {
        $data['valid_relations'][] = $relation['name'];
    }

    //----------------------------//
    // 2. Render Template
    //set the class name
    $class = 'page-admin-history-search page-admin';

    //render the body
    $body = $this
        ->package('cradlephp/cradle-history')
        ->template('search', $data, [
            'search_head',
            'search_form',
            'search_filters',
            'search_actions',
            'search_row_format',
            'search_row_actions'
        ]);

    //set content
    $response
        ->setPage('title', $data['schema']['plural'])
        ->setPage('class', $class)
        ->setContent($body);

    //if we only want the body
    if ($request->getStage('render') === 'body') {
        return;
    }
    // cradle()->inspect($data['schema']['relations']['history_profile']); exit;
    //render page
    $this->trigger('admin-render-page', $request, $response);
});

/**
 * Renders an update form
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/history/export/:type', function ($request, $response) {
    //----------------------------//
    //set redirect
    $request->setStage('redirect_uri', '/admin/profile/search');

    //now let the object update take over
    $this->routeTo(
        'get',
        sprintf(
            '/admin/system/object/history/export/%s',
            $request->getStage('type')
        ),
        $request,
        $response
    );
});

/**
 * Show/Read History Logs (AJAX)
 * based on the given data.
 *
 * @param Request $request
 * @param Response $response
 */
$cradle->get('/admin/history/:action/logs', function ($request, $response) {
    if (!$request->hasStage('action')) {
        //Set JSON Content
        return $response->setContent(json_encode([
            'error'      => true,
            'message'    => 'Invalid History Action',
        ]));
    }

    $data = $request->getStage();

    switch (strtolower($data['action'])) {
        case 'get':
            $request->setStage('filter', 'history_flag', 0);
            $request->setStage('nocache', 1);
            $request->setStage('order', 'history_created', 'DESC');

            cradle()->trigger('history-search', $request, $response);

            $results = $response->getResults();

            if ($response->isError()) {
                //Set JSON Content
                return $response->setContent(json_encode([
                    'error'      => true,
                    'message'    => $response->getMessage(),
                    'validation' => $response->getValidation()
                ]));
            }

            //process data
            foreach ($results['rows'] as $key => $value) {
                $timestamp = strtotime($value['history_created']);

                $strTime = array("second", "minute", "hour", "day", "month", "year");
                $length = array("60","60","24","30","12","10");

                $currentTime = time();
                if ($currentTime >= $timestamp) {
                    $diff     = time()- $timestamp;
                    for ($i = 0; $diff >= $length[$i] && $i < count($length)-1; $i++) {
                        $diff = $diff / $length[$i];
                    }

                    $diff = round($diff);
                    $results['rows'][$key]['ago'] = $diff . " " . $strTime[$i] . "(s) ago ";
                }
            }

            //set message
            $data['message'] = 'New history logs loaded';

            break;
        case 'read':
            //mark all unread logs to read
            cradle()->trigger('history-mark-as-read', $request, $response);

            $results = $response->getResults();

            if ($response->isError()) {
                //Set JSON Content
                return $response->setContent(json_encode([
                    'error'      => true,
                    'message'    => $response->getMessage(),
                ]));
            }

            //set message
            $data['message'] = 'All new history log marked as read';

            break;
        default:
            if ($response->isError()) {
                //Set JSON Content
                return $response->setContent(json_encode([
                    'error'      => true,
                    'message'    => 'Invalid History Action',
                ]));
            }
            break;
    }

    //Set JSON Content
    return $response->setContent(json_encode([
        'error' => false,
        'message' => $data['message'],
        'results' => $results
    ]));
});

// Front End Controllers
