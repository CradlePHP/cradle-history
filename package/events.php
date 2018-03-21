<?php //-->
/**
 * This file is part of a Custom Package.
 */

use Cradle\Storm\SqlFactory;

use Cradle\Package\System\Schema;
use Cradle\Package\System\Exception;

use Cradle\Http\Request;
use Cradle\Http\Response;

/**
 * $ cradle package install cradlephp/cradle-history
 * $ cradle package install cradlephp/cradle-history 1.0.0
 * $ cradle cradlephp/cradle-history install
 * $ cradle cradlephp/cradle-history install 1.0.0
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-history-install', function ($request, $response) {
    //custom name of this package
    $name = 'cradlephp/cradle-history';

    //if it's already installed
    if ($this->package('global')->config('version', $name)) {
        $message = sprintf('%s is already installed', $name);
        return $response->setError(true, $message);
    }

    // install package
    $version = $this->package('cradlephp/cradle-history')->install('0.0.0');

    // update the config
    $this->package('global')->config('version', $name, $version);
    $response->setResults('version', $version);
});

/**
 * $ cradle package update cradlephp/cradle-history
 * $ cradle package update cradlephp/cradle-history 1.0.0
 * $ cradle cradlephp/cradle-history update
 * $ cradle cradlephp/cradle-history update 1.0.0
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-history-update', function ($request, $response) {
    //custom name of this package
    $name = 'cradlephp/cradle-history';

    //get the current version
    $current = $this->package('global')->config('version', $name);

    //if it's not installed
    if (!$current) {
        $message = sprintf('%s is not installed', $name);
        return $response->setError(true, $message);
    }

    // get available version
    $version = $this->package($name)->version();

    //if available <= current
    if (version_compare($version, $current, '<=')) {
        $message = sprintf('%s %s <= %s', $name, $version, $current);
        return $response->setError(true, $message);
    }

    // update package
    $version = $this->package('cradlephp/cradle-history')->install($current);

    // update the config
    $this->package('global')->config('versions', $name, $version);
    $response->setResults('version', $version);
});

/**
 * $ cradle package remove cradlephp/cradle-history
 * $ cradle cradlephp/cradle-history remove
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-history-remove', function ($request, $response) {
    //setup result counters
    $errors = [];

    //scan through each file
    foreach (scandir(__DIR__ . '/schema') as $file) {
        //if it's not a php file
        if(substr($file, -4) !== '.php') {
            //skip
            continue;
        }

        //get the schema data
        $data = include sprintf('%s/schema/%s', __DIR__, $file);

        //if no name
        if (!isset($data['name'])) {
            //skip
            continue;
        }

        //----------------------------//
        // 1. Prepare Data
        $request->setStage('schema', $data['name']);

        //----------------------------//
        // 2. Process Request
        $this->trigger('system-schema-remove', $request, $response);

        //----------------------------//
        // 3. Interpret Results
        if ($response->isError()) {
            //collect all the errors
            $errors[$data['name']] = $response->getMessage();
            continue;
        }

        $processed[] = $data['name'];
    }

    if (!empty($errors)) {
        $response->set('json', 'validation', $errors);
    }

    $response->setResults('schemas', $processed);
});

/**
 * $ cradle elastic flush cradlephp/cradle-history
 * $ cradle cradlephp/cradle-history elastic-flush
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-history-elastic-flush', function ($request, $response) {
    // set parameters
    $request->setStage('name', 'history');
    // trigger global schema flush
    $this->trigger('system-schema-flush-elastic', $request, $response);
    // set response
    $response->setResults('schema', 'history');
});

/**
 * $ cradle elastic map cradlephp/cradle-history
 * $ cradle cradlephp/cradle-history elastic-map
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-history-elastic-map', function ($request, $response) {
    // set parameters
    $request->setStage('name', 'history');
    // trigger global schema flush
    $this->trigger('system-schema-map-elastic', $request, $response);
    // set response
    $response->setResults('schema', 'history');
});

/**
 * $ cradle elastic populate cradlephp/cradle-history
 * $ cradle cradlephp/cradle-history elastic-populate
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-history-elastic-populate', function ($request, $response) {
    // set parameters
    $request->setStage('name', 'history');
    // trigger global schema flush
    $this->trigger('system-schema-populate-elastic', $request, $response);
    // set response
    $response->setResults('schema', 'history');
});

/**
 * $ cradle redis flush cradlephp/cradle-history
 * $ cradle cradlephp/cradle-history redis-flush
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-history-redis-flush', function ($request, $response) {
    // initialize schema
    $schema = Schema::i('history');
    // get redis service
    $redis = $schema->object()->service('redis');
    // remove cached search and detail from redis
    $redis->removeSearch();
    $redis->removeDetail();
    
    $response->setResults('schema', 'history');
});

/**
 * $ cradle redis populate cradlephp/cradle-history
 * $ cradle cradlephp/cradle-history redis-populate
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-history-redis-populate', function ($request, $response) {
    // initialize schema
    $schema = Schema::i('history');
    // get sql service
    $sql = $schema->object()->service('sql');
    $redis = $schema->object()->service('redis');
    // get sql data
    $data = $sql->search();
    // if there is no results
    if (!isset($data['total']) && $data['total'] < 1) {
        // do not proceed
        return $response->setResults('schema', 'history');
    }

    // get slugable fields
    $slugs = $schema->getSlugableFieldNames($schema->getPrimaryFieldName());
    // loop through rows
    foreach ($data['rows'] as $entry) {
        // loop thru slugs
        foreach ($slugs as $slug) {
            // if entry found
            if (isset($entry[$slug])) {
                // create cache data on redis
                $redis->createDetail($slug . '-' . $entry[$slug], $entry);
            }
        }
        
    }

    $response->setResults('schema', 'history');
    
});

/**
 * $ cradle sql build cradlephp/cradle-history
 * $ cradle cradlephp/cradle-history sql-build
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-history-sql-build', function ($request, $response) {
    //load up the database
    $pdo = $this->package('global')->service('sql-main');
    $database = SqlFactory::load($pdo);

    //setup result counters
    $errors = [];
    $processed = [];

    //scan through each file
    foreach (scandir(__DIR__ . '/schema') as $file) {
        //if it's not a php file
        if(substr($file, -4) !== '.php') {
            //skip
            continue;
        }

        //get the schema data
        $data = include sprintf('%s/schema/%s', __DIR__, $file);

        //if no name
        if (!isset($data['name'])) {
            //skip
            continue;
        }

        try {
            $schema = Schema::i($data['name']);
        } catch(Exception $e) {
            continue;
        }

        //remove primary table
        $database->query(sprintf('DROP TABLE IF EXISTS `%s`', $schema->getName()));

        //loop through relations
        foreach ($schema->getRelations() as $table => $relation) {
            //remove relation table
            $database->query(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }

        //now build it back up
        //set the data
        $request->setStage($schema->get());

        //----------------------------//
        // 1. Prepare Data
        //if detail has no value make it null
        if ($request->hasStage('detail')
            && !$request->getStage('detail')
        ) {
            $request->setStage('detail', null);
        }

        //if fields has no value make it an array
        if ($request->hasStage('fields')
            && !$request->getStage('fields')
        ) {
            $request->setStage('fields', []);
        }

        //if validation has no value make it an array
        if ($request->hasStage('validation')
            && !$request->getStage('validation')
        ) {
            $request->setStage('validation', []);
        }

        //----------------------------//
        // 2. Process Request
        //now trigger
        $this->trigger('system-schema-update', $request, $response);

        //----------------------------//
        // 3. Interpret Results
        //if the event returned an error
        if ($response->isError()) {
            //collect all the errors
            $errors[$data['name']] = $response->getValidation();
            continue;
        }

        $processed[] = $data['name'];
    }

    if (!empty($errors)) {
        $response->set('json', 'validation', $errors);
    }

    $response->setResults(['schemas' => $processed]);
});

/**
 * $ cradle sql flush cradlephp/cradle-history
 * $ cradle cradlephp/cradle-history sql-flush
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-history-sql-flush', function ($request, $response) {
    //load up the database
    $pdo = $this->package('global')->service('sql-main');
    $database = SqlFactory::load($pdo);

    //setup result counters
    $errors = [];
    $processed = [];

    //scan through each file
    foreach (scandir(__DIR__ . '/schema') as $file) {
        //if it's not a php file
        if(substr($file, -4) !== '.php') {
            //skip
            continue;
        }

        //get the schema data
        $data = include sprintf('%s/schema/%s', __DIR__, $file);

        //if no name
        if (!isset($data['name'])) {
            //skip
            continue;
        }

        try {
            $schema = Schema::i($data['name']);
        } catch(Exception $e) {
            continue;
        }

        //remove primary table
        $database->query(sprintf('TRUNCATE `%s`', $schema->getName()));

        //loop through relations
        foreach ($schema->getRelations() as $table => $relation) {
            //remove relation table
            $database->query(sprintf('TRUNCATE `%s`', $table));
        }

        $processed[] = $data['name'];
    }

    $response->setResults('schemas', $processed);
});

/**
 * $ cradle sql populate cradlephp/cradle-history
 * $ cradle cradlephp/cradle-history sql-populate
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-history-sql-populate', function ($request, $response) {
    //scan through each file
    foreach (scandir(__DIR__ . '/schema') as $file) {
        //if it's not a php file
        if(substr($file, -4) !== '.php') {
            //skip
            continue;
        }

        //get the schema data
        $data = include sprintf('%s/schema/%s', __DIR__, $file);

        //if no name
        if (!isset($data['name'], $data['fixtures'])
            || !is_array($data['fixtures'])
        ) {
            //skip
            continue;
        }

        $actionRequest = Request::i()->load();
        $actionResponse = Response::i()->load();
        foreach($data['fixtures'] as  $fixture) {
            $actionRequest
                ->setStage($fixture)
                ->setStage('schema', 'history');

            $this->trigger('system-object-create', $actionRequest, $actionResponse);
        }
    }
});
