<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->exclude('docker')
    ->exclude('postgres_data')
    ->notPath('config/reference.php')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@PHP83Migration' => true,
        'strict_param' => true,
        'declare_strict_types' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'no_superfluous_phpdoc_tags' => ['remove_inheritdoc' => true],
        'phpdoc_trim' => true,
        'phpdoc_order' => true,
        'blank_line_before_statement' => ['statements' => ['return', 'throw', 'try']],
        'concat_space' => ['spacing' => 'one'],
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
        'global_namespace_import' => ['import_classes' => true, 'import_functions' => false, 'import_constants' => false],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
;
