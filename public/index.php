<?php

// AJOUTER CETTE LIGNE EN PREMIER
set_time_limit(300); // 300 secondes = 5 minutes

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};