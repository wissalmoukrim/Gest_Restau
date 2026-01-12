<?php

// AJOUTER CETTE LIGNE EN PREMIER
set_time_limit(300); // 300 secondes = 5 minutes

use App\Kernel;

// Augmenter le temps d'exécution maximal
set_time_limit(300); // 5 minutes
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');

// Augmenter la mémoire limit si nécessaire
ini_set('memory_limit', '512M');

// Désactiver la limite de temps pour le script
if (function_exists('ini_set')) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};