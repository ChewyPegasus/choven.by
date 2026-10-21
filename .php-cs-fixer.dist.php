<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'legacy', 'vendor', 'assets', 'public', 'templates', 'translations'])
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        'phpdoc_to_comment' => false,
        'phpdoc_summary' => false,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
