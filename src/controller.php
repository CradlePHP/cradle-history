<?php //-->
/**
 * This file is part of a package designed for the CradlePHP Project.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
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

    $request
        ->setStage('schema', 'history')
        ->setStage('order', 'history_created', 'DESC');

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
    $this->trigger('system-model-search', $request, $response);

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
        $data['schema']['filterable'] = array_values(
            $data['schema']['filterable']
        );
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

    $template = __DIR__ . '/template';
    if (is_dir($response->getPage('template_root'))) {
        $template = $response->getPage('template_root');
    }

    $partials = __DIR__ . '/template';
    if (is_dir($response->getPage('partials_root'))) {
        $partials = $response->getPage('partials_root');
    }

    //render the body
    $body = $this
        ->package('cradlephp/cradle-system')
        ->template(
            'search',
            $data,
            [
                'search_head',
                'search_form',
                'search_filters',
                'search_actions',
                'search_row_actions'
            ],
            $template,
            $partials
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
 * Render the History Raw Data Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/history/json/:history_id', function ($request, $response) {
    $global = $this->package('global');
    //get the logs
    $request->setStage('schema', 'history');
    $this->trigger('system-model-detail', $request, $response);

    // if errors
    if ($response->isError()) {
        //redirect
        $redirect = '/admin/history/search';

        //this is for flexibility
        if ($request->hasStage('redirect_uri')) {
            $redirect = $request->getStage('redirect_uri');
        }

        //if no redirect
        if ($redirect === 'false') {
            return;
        }

        //add a flash
        $global->flash($response->getMessage(), 'error');
        return $global->redirect($redirect);
    }

    //if we only want the raw data
    if ($request->getStage('render') === 'false') {
        return;
    }

    $data['detail'] = $response->getResults();

    //get schema data
    $schema = Schema::i('history');
    //also pass the schema to the template
    $data['schema'] = $schema->getAll();

    //if we only want the raw data
    if ($request->getStage('render') === 'false') {
        return;
    }

    //----------------------------//
    // 2. Render Template
    //set the class name
    $class = 'page-admin-history-detail page-admin';

    //determine the title
    $data['title'] = $global->translate(
        'Viewing Raw Log for #%s',
        $request->getStage('history_id')
    );

    $template = __DIR__ . '/template';
    if (is_dir($response->getPage('template_root'))) {
        $template = $response->getPage('template_root');
    }

    $partials = __DIR__ . '/template';
    if (is_dir($response->getPage('partials_root'))) {
        $partials = $response->getPage('partials_root');
    }

    //render the body
    $body = $this
        ->package('cradlephp/cradle-system')
        ->template(
            'code',
            $data,
            [],
            $template,
            $partials
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
 * Render the History Model Changes Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/history/changes/:history_id', function ($request, $response) {
    //redirect
    $redirect = '/admin/history/search';

    //this is for flexibility
    if ($request->hasStage('redirect_uri')) {
        $redirect = $request->getStage('redirect_uri');
    }

    $request->setStage('redirect_uri', 'false');

    $route = sprintf(
        '/admin/history/model/changes/%s',
        $request->getStage('history_id')
    );

    $this->routeTo('get', $route, $request, $response);

    if (!$response->isError()) {
        return;
    }

    $route = sprintf(
        '/admin/history/schema/changes/%s',
        $request->getStage('history_id')
    );

    $this->routeTo('get', $route, $request, $response);

    if (!$response->isError()) {
        return;
    }

    //if no redirect
    if ($redirect === 'false') {
        return;
    }

    //add a flash
    $global = $this->package('global');
    $global->flash($response->getMessage(), 'error');
    $global->redirect($redirect);
});

/**
 * Render the History Model Changes Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/history/changes/:history_table_name/:history_table_id', function ($request, $response) {
    $id = $request->getStage('history_table_id');
    $table = $request->getStage('history_table_name');

    $request
        ->setStage('schema', 'history')
        ->setStage('filter', 'history_table_id', $id)
        ->setStage('filter', 'history_table_name', $table)
        ->setStage('range', 0)
        ->setStage('order', 'history_created', 'DESC');

    $this->trigger('system-model-search', $request, $response);

    $rows = $response->getResults('rows');

    $revisions = [];

    foreach($rows as $row) {
        //make a new RnR
        $payload = $this->makePayload();

        //make sure we get this back
        $payload['request']
            ->setStage('render', 'false')
            ->setStage('redirect_uri', 'false');

        $route = sprintf(
            '/admin/history/changes/%s',
            $row['history_id']
        );

        $this->routeTo('get', $route, $payload['request'], $payload['response']);

        // if errors
        if ($response->isError()) {
            //just add an entry
            $revisions[]['detail'] = $row;
            continue;
        }

        if (!$payload['response']->hasResults('history')) {
            //just add an entry
            $revisions[]['detail'] = $row;
            continue;
        }

        $revisions[] = [
            'detail' => $payload['response']->getResults('history'),
            'item' => [
                'noop' => [],
                'schema' => $payload['response']->getResults('schema'),
                'current' => $payload['response']->getResults('current'),
                'original' => $payload['response']->getResults('original')
            ],
        ];
    }

    $data['id'] = $id;
    $data['table'] = $table;

    //if we only want the raw data
    if ($request->getStage('render') === 'false') {
        return $response->setResults('rows', $revisions);
    }

    $data['revisions'] = $revisions;

    //also pass the schema to the template
    $data['schema'] = Schema::i('history')->getAll();

    //----------------------------//
    // 2. Render Template
    //set the class name
    $class = 'page-admin-history-detail page-admin';

    //determine the title
    $data['title'] = $this->package('global')->translate(
        'Viewing Revisions for %s #%s',
        $table,
        $id
    );

    $template = __DIR__ . '/template';
    if (is_dir($response->getPage('template_root'))) {
        $template = $response->getPage('template_root');
    }

    $partials = __DIR__ . '/template';
    if (is_dir($response->getPage('partials_root'))) {
        $partials = $response->getPage('partials_root');
    }

    //render the body
    $body = $this
        ->package('cradlephp/cradle-system')
        ->template(
            'revisions',
            $data,
            [
                'change_model',
                'change_value',
                'change_schema',
                'change_revisions'
            ],
            $template,
            $partials
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
 * Redirect to the Item Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/history/redirect/:history_id', function ($request, $response) {
    //redirect
    $redirect = '/admin/history/search';

    //this is for flexibility
    if ($request->hasStage('redirect_uri')) {
        $redirect = $request->getStage('redirect_uri');
    }

    $request->setStage('redirect_uri', 'false');

    $route = sprintf(
        '/admin/history/model/redirect/%s',
        $request->getStage('history_id')
    );

    $this->routeTo('get', $route, $request, $response);

    if (!$response->isError()) {
        return;
    }

    $route = sprintf(
        '/admin/history/schema/redirect/%s',
        $request->getStage('history_id')
    );

    $this->routeTo('get', $route, $request, $response);

    if (!$response->isError()) {
        return;
    }

    //if no redirect
    if ($redirect === 'false') {
        return;
    }

    //add a flash
    $global = $this->package('global');
    $global->flash($response->getMessage(), 'error');
    $global->redirect($redirect);
});

/**
 * Render the History Model Changes Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/history/model/changes/:history_id', function ($request, $response) {
    $global = $this->package('global');
    //get the versions
    $this->trigger('history-model-versions', $request, $response);

    // if errors
    if ($response->isError()) {
        //redirect
        $redirect = '/admin/history/search';

        //this is for flexibility
        if ($request->hasStage('redirect_uri')) {
            $redirect = $request->getStage('redirect_uri');
        }

        //if no redirect
        if ($redirect === 'false') {
            return;
        }

        //add a flash
        $global->flash($response->getMessage(), 'error');
        return $global->redirect($redirect);
    }

    //if we only want the raw data
    if ($request->getStage('render') === 'false') {
        return;
    }

    $data['detail'] = $response->getResults('history');
    $data['item']['schema'] = $response->getResults('schema');
    $data['item']['current'] = $response->getResults('current');
    $data['item']['original'] = $response->getResults('original');

    //add a noop
    $data['item']['noop'] = [];

    //also pass the schema to the template
    $data['schema'] = Schema::i('history')->getAll();

    //----------------------------//
    // 2. Render Template
    //set the class name
    $class = 'page-admin-history-detail page-admin';

    //determine the title
    $data['title'] = $global->translate(
        'Viewing Changes for #%s',
        $request->getStage('history_id')
    );

    $template = __DIR__ . '/template';
    if (is_dir($response->getPage('template_root'))) {
        $template = $response->getPage('template_root');
    }

    $partials = __DIR__ . '/template';
    if (is_dir($response->getPage('partials_root'))) {
        $partials = $response->getPage('partials_root');
    }

    //render the body
    $body = $this
        ->package('cradlephp/cradle-system')
        ->template(
            'change/model',
            $data,
            [
                'change_model'
            ],
            $template,
            $partials
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
 * Process the History Model Revert
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/history/model/revert/:history_id', function ($request, $response) {
    $global = $this->package('global');
    //get the versions
    $this->trigger('history-model-versions', $request, $response);

    //redirect
    $redirect = '/admin/history/search';

    //this is for flexibility
    if ($request->hasStage('redirect_uri')) {
        $redirect = $request->getStage('redirect_uri');
    }

    // if errors
    if ($response->isError()) {
        //if no redirect
        if ($redirect === 'false') {
            return;
        }

        //add a flash
        $global->flash($response->getMessage(), 'error');
        return $global->redirect($redirect);
    }

    $data['detail'] = $response->getResults('history');
    $data['item']['schema'] = $response->getResults('schema');
    $data['item']['current'] = $response->getResults('current');
    $data['item']['original'] = $response->getResults('original');

    $schema = $response->getResults('schema', 'name');
    $original = $response->getResults('original');

    //reset the stage
    $request->setStage($original);

    if ($schema) {
        $request->setStage('schema', $schema);
        //now trigger the update
        $this->trigger('system-model-update', $request, $response);
    //let's try to do a regular update
    } else {
        // but we cannot do a regular update, because theres other things
        // to consider like how to deal with cache and index, etc...
        // in summary, it's best that we just trigger an event, incase
        // anything is listening to this
        $event = $data['detail']['history_table_name'] . '-update';
        $this->trigger($event, $request, $response);
    }

    //if no redirect
    if ($redirect === 'false') {
        return;
    }

    //interpret
    if ($response->isError()) {
        //add a flash
        $global->flash($response->getMessage(), 'error');
    } else {
        $global->flash(sprintf(
            '%s was successfully reverted',
            $data['detail']['history_table_name']
        ), 'success');

        //record logs
        $this->log(
            sprintf(
                'reverted %s #%s',
                $data['detail']['history_table_name'],
                $data['detail']['history_table_id']
            ),
            $request,
            $response,
            'update',
            $data['detail']['history_table_name'],
            $data['detail']['history_table_id']
        );
    }

    $global->redirect($redirect);
});

/**
 * Redirect to the Model Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/history/model/redirect/:history_id', function ($request, $response) {
    //----------------------------//
    // 1. Prepare Data
    $global = $this->package('global');
    //get the versions
    $this->trigger('history-model-versions', $request, $response);

    //redirect
    $redirect = '/admin/history/search';

    //this is for flexibility
    if ($request->hasStage('redirect_uri')) {
        $redirect = $request->getStage('redirect_uri');
    }

    // if errors
    if ($response->isError()) {
        //if no redirect
        if ($redirect === 'false') {
            return;
        }

        //add a flash
        $global->flash($response->getMessage(), 'error');
        return $global->redirect($redirect);
    }

    if (!$response->getResults('schema', 'name')) {
        //if no redirect
        if ($redirect === 'false') {
            return;
        }

        //add a flash
        $global->flash('Not Found', 'error');
        return $global->redirect($redirect);
    }

    $primary = $response->getResults('schema', 'primary');
    $original = $response->getResults('original');

    $global->redirect(sprintf(
        '/admin/system/model/%s/detail/%s',
        $response->getResults('schema', 'name'),
        $original[$primary]
    ));
});

/**
 * Render the History Model Changes Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/history/schema/changes/:history_id', function ($request, $response) {
    $global = $this->package('global');
    //get the versions
    $this->trigger('history-schema-versions', $request, $response);

    // if errors
    if ($response->isError()) {
        //redirect
        $redirect = '/admin/history/search';

        //this is for flexibility
        if ($request->hasStage('redirect_uri')) {
            $redirect = $request->getStage('redirect_uri');
        }

        //if no redirect
        if ($redirect === 'false') {
            return;
        }

        //add a flash
        $global->flash($response->getMessage(), 'error');
        return $global->redirect($redirect);
    }

    //if we only want the raw data
    if ($request->getStage('render') === 'false') {
        return;
    }

    $data['detail'] = $response->getResults('history');
    $data['item']['schema'] = $response->getResults('schema');
    $data['item']['current'] = $response->getResults('current');
    $data['item']['original'] = $response->getResults('original');

    //also pass the schema to the template
    $data['schema'] = Schema::i('history')->getAll();

    //----------------------------//
    // 2. Render Template
    //set the class name
    $class = 'page-admin-history-detail page-admin';

    //determine the title
    $data['title'] = $global->translate(
        'Viewing Changes for #%s',
        $request->getStage('history_id')
    );

    $template = __DIR__ . '/template';
    if (is_dir($response->getPage('template_root'))) {
        $template = $response->getPage('template_root');
    }

    $partials = __DIR__ . '/template';
    if (is_dir($response->getPage('partials_root'))) {
        $partials = $response->getPage('partials_root');
    }

    //render the body
    $body = $this
        ->package('cradlephp/cradle-system')
        ->template(
            'change/schema',
            $data,
            [
                'change_schema'
            ],
            $template,
            $partials
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
 * Process the History Schema Revert
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/history/schema/revert/:history_id', function ($request, $response) {
    $global = $this->package('global');
    //get the versions
    $this->trigger('history-schema-versions', $request, $response);

    //redirect
    $redirect = '/admin/history/search';

    //this is for flexibility
    if ($request->hasStage('redirect_uri')) {
        $redirect = $request->getStage('redirect_uri');
    }

    // if errors
    if ($response->isError()) {
        //if no redirect
        if ($redirect === 'false') {
            return;
        }

        //add a flash
        $global->flash($response->getMessage(), 'error');
        return $global->redirect($redirect);
    }

    $data['detail'] = $response->getResults('history');
    $data['item']['schema'] = $response->getResults('schema');
    $data['item']['current'] = $response->getResults('current');
    $data['item']['original'] = $response->getResults('original');

    $schema = $response->getResults('schema', 'name');
    $original = $response->getResults('original');

    //reset the stage
    $request->setStage($original);
    $request->setStage('schema', $schema);

    //now trigger the update
    $this->trigger('system-schema-update', $request, $response);

    //if no redirect
    if ($redirect === 'false') {
        return;
    }

    //interpret
    if ($response->isError()) {
        //add a flash
        $global->flash($response->getMessage(), 'error');
    } else {
        $global->flash(sprintf(
            '%s was successfully reverted',
            $data['item']['schema']['singular']
        ), 'success');

        //record logs
        $this->log(
            sprintf(
                'reverted schema: %s',
                $data['item']['schema']['singular']
            ),
            $request,
            $response,
            'update',
            'schema',
            $data['item']['schema']['name']
        );
    }

    $global->redirect($redirect);
});

/**
 * Redirect to the Schema Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/history/schema/redirect/:history_id', function ($request, $response) {
    //----------------------------//
    // 1. Prepare Data
        $global = $this->package('global');
    //get the versions
    $this->trigger('history-schema-versions', $request, $response);

    // if errors
    if ($response->isError()) {
        //redirect
        $redirect = '/admin/history/search';

        //this is for flexibility
        if ($request->hasStage('redirect_uri')) {
            $redirect = $request->getStage('redirect_uri');
        }

        //if no redirect
        if ($redirect === 'false') {
            return;
        }

        //add a flash
        $global->flash($response->getMessage(), 'error');
        return $global->redirect($redirect);
    }

    $global->redirect(sprintf(
        '/admin/system/schema/update/%s?redurect_uri=%s',
        $response->getResults('schema', 'name'),
        urlencode('/admin/system/schema/search')
    ));
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
$this->get('/admin/history/:action/logs', function ($request, $response) {
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
            $request
                ->setStage('schema', 'history')
                ->setStage('filter', 'history_flag', 0)
                ->setStage('order', 'history_created', 'DESC');

            $this->trigger('system-model-search', $request, $response);

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
