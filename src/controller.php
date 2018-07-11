<?php //-->
/**
 * This file is part of a Custom Package.
 */

// Back End Controllers
use Cradle\Package\System\Schema;

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
        ->template(
            'search',
            $data,
            [
                'search_head',
                'search_form',
                'search_filters',
                'search_actions',
                'search_row_format',
                'search_row_actions'
            ],
            $response->getPage('template_root'),
            $response->getPage('partials_root')
        );

    //set content
    $response
        ->setPage('title', $data['schema']['plural'])
        ->setPage('class', $class)
        ->setContent($body);

    //if we only want the body
    if ($request->getStage('render') === 'body') {
        return;
    }

    //render page
    $this->trigger('admin-render-page', $request, $response);
});

/**
 * Render the System Model Update Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/history/detail/:history_id', function ($request, $response) {
    //----------------------------//
    // 1. Prepare Data
    // get the settings
    $config = $this->package('global')->config('settings');

    //get schema data
    $schema = Schema::i('history');

    //pass the item with only the post data
    $data = ['item' => $request->getPost()];

    //also pass the schema to the template
    $data['schema'] = $schema->getAll();

    //if this is a return back from processing
    //this form and it's because of an error
    if ($response->isError()) {
        //pass the error messages to the template
        $response->setFlash($response->getMessage(), 'error');
        $data['errors'] = $response->getValidation();
    }

    //get the original table row
    $this->trigger('history-detail', $request, $response);

    //can we update ?
    if ($response->isError()) {
        //redirect
        $redirect = '/admin/history/search';

        //this is for flexibility
        if ($request->hasStage('redirect_uri')) {
            $redirect = $request->getStage('redirect_uri');
        }

        //add a flash
        $this->package('global')->flash($response->getMessage(), 'error');
        return $this->package('global')->redirect($redirect);
    }

    $data['detail'] = $response->getResults();

    //if no item
    if (empty($data['item'])) {
        //pass the item to the template
        $data['item'] = $data['detail'];

        // default log path
        $logPath = 'log';

        // if log path is set
        if (isset($config['log_path'])) {
            $logPath = $config['log_path'];
        }

        // case for relative path
        if (strpos($logPath, '/') !== 0) {
            $logPath = $this->package('global')->path('root') . '/' . $logPath;
        }

        // if history path is set
        if (isset($data['item']['history_path'])) {
            // get the history log file
            $logPath = $logPath . '/' . $data['item']['history_path'];

            // default meta content
            $meta = null;

            // try parsing
            try {
                // read the file
                $meta = @file_get_contents($logPath);
                // encode/decode to format
                $meta = json_encode(json_decode($meta), JSON_PRETTY_PRINT);
            } catch(\Exception $e) {}

            if (!$meta || $meta == 'null') {
                $meta = 'Data is Empty';
            }

            // set history meta
            $data['item']['history_meta'] = $meta;
        }

        //add suggestion value for each relation
        foreach ($data['schema']['relations'] as $name => $relation) {
            if ($relation['many'] > 1) {
                continue;
            }

            $suggestion = '_' . $relation['primary2'];

            $suggestionData = $data['item'];
            if ($relation['many'] == 0) {
                if (!isset($data['item'][$relation['name']])) {
                    continue;
                }

                $suggestionData = $data['item'][$relation['name']];

                if (!$suggestionData) {
                    continue;
                }
            }

            try {
                $data['item'][$suggestion] = Schema::i($relation['name'])
                    ->getSuggestionFormat($suggestionData);
            } catch (Exception $e) {
            }
        }
    }

    //if we only want the raw data
    if ($request->getStage('render') === 'false') {
        return;
    }

    //determine the suggestion
    $data['detail']['suggestion'] = $schema->getSuggestionFormat($data['item']);

    //add CSRF
    $this->trigger('csrf-load', $request, $response);
    $data['csrf'] = $response->getResults('csrf');

    //if there are file fields
    if (!empty($data['schema']['files'])) {
        //add CDN
        $config = $this->package('global')->service('s3-main');
        $data['cdn_config'] = File::getS3Client($config);
    }

    //determine valid relations
    $data['valid_relations'] = [];
    $this->trigger('system-schema-search', $request, $response);
    foreach ($response->getResults('rows') as $relation) {
        $data['valid_relations'][] = $relation['name'];
    }

    $data['redirect'] = urlencode($request->getServer('REQUEST_URI'));

    //----------------------------//
    // 2. Render Template
    //set the class name
    $class = 'page-admin-history-detail page-admin';

    //determine the title
    $data['title'] = $this->package('global')->translate('Viewing history #%s', $request->getStage('history_id'));

    //render the body
    $body = $this
        ->package('cradlephp/cradle-history')
        ->template(
            'detail',
            $data,
            [
                'detail_detail',
                'detail_format'
            ],
            $response->getPage('template_root'),
            $response->getPage('partials_root')
        );

    //set content
    $response
        ->setPage('title', $data['title'])
        ->setPage('class', $class)
        ->setContent($body);

    //if we only want the body
    if ($request->getStage('render') === 'body') {
        return;
    }

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

    //now let the model update take over
    $this->routeTo(
        'get',
        sprintf(
            '/admin/system/model/history/export/%s',
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

            $this->trigger('history-search', $request, $response);

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
            $this->trigger('history-mark-as-read', $request, $response);

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
