<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/web')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => ['default' => 'align_single_space_minimal'],
        // Replacing deprecated 'braces' rule with recommended rule set
        'control_structure_braces' => true,
        'control_structure_continuation_position' => true,
        'declare_parentheses' => true,
        'no_multiple_statements_per_line' => true,
        'braces_position' => true,
        'statement_indentation' => true,
        'concat_space' => ['spacing' => 'one'],
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'single_quote' => true,
    ])
    ->setFinder($finder)
    ->setUsingCache(true);
