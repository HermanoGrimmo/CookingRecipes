<?php

declare(strict_types=1);

/**
 * PHP CS Fixer Konfiguration für das CookingRecipes-Projekt.
 *
 * Basiert auf den Symfony-Coding-Standards mit strikter Typisierung.
 */

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->exclude('docker')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => true,
        'phpdoc_order' => true,
        'concat_space' => ['spacing' => 'one'],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
;
