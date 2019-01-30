<?php //-->

cradle(function() {
    //setup result counters
    $response = $this->getResponse();
    $logs = [];
    $processed = [];

    //if there was an error
    if ($response->isError()) {
        $logs[] = [
            'type' => 'error',
            'message' => 'Error from the previous version. Skipping...'
        ];

        $response->setResults('logs', 'cradlephp/cradle-history', '0.0.2', $logs);
        return;
    }

    //scan through each file
    foreach (scandir(__DIR__ . '/../schema') as $file) {
        //if it's not a php file
        if (substr($file, -4) !== '.php') {
            //skip
            continue;
        }

        //get the schema data
        $data = include sprintf('%s/../schema/%s', __DIR__, $file);

        //if no name
        if (!isset($data['name'])) {
            //skip
            continue;
        }

        //setup a new RnR
        $payload = $this->makePayload();
        //set the data
        $payload['request']->setStage($data);

        //----------------------------//
        // 1. Prepare Data
        //if detail has no value make it null
        if ($payload['request']->hasStage('detail')
            && !$payload['request']->getStage('detail')
        ) {
            $payload['request']->setStage('detail', null);
        }

        //if fields has no value make it an array
        if ($payload['request']->hasStage('fields')
            && !$payload['request']->getStage('fields')
        ) {
            $payload['request']->setStage('fields', []);
        }

        //if validation has no value make it an array
        if ($payload['request']->hasStage('validation')
            && !$payload['request']->getStage('validation')
        ) {
            $payload['request']->setStage('validation', []);
        }

        $logs[] = [
            'type' => 'info',
            'message' => sprintf('Updating %s schema', $data['name'])
        ];

        //----------------------------//
        // 2. Process Request
        //now trigger
        $this->trigger(
            'system-schema-update',
            $payload['request'],
            $payload['response']
        );

        //----------------------------//
        // 3. Interpret Results
        //if the event does hot have an error
        if (!$payload['response']->isError()) {
            $processed[] = $data['name'];
            continue;
        }

        $this->getResponse()->setError(true);
        $errors = $payload['response']->getValidation();
        foreach($errors as $key => $message) {
            $logs[] = ['type' => 'error', 'message' => $message];
        }
    }

    $schemas = $response->getResults('schemas');

    if (!is_array($schemas)) {
        $schemas = [];
    }

    $schemas = array_merge($schemas, $processed);

    $response
        ->setResults('logs', 'cradlephp/cradle-history', '0.0.2', $logs)
        ->setResults('schemas', $schemas);
});
