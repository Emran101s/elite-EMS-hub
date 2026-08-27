<?php

// Larastan's own bootstrap — loads the Laravel app container so static
// analysis can resolve facades, models, and container bindings. Analysis
// only; nothing here runs at request time.
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

return $app;
