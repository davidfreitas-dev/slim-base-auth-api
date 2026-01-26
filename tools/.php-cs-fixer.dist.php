<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(dirname(__DIR__) . '/src')
    ->in(dirname(__DIR__) . '/config')
    ->exclude('vendor');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@PHP8x4Migration' => true,
        
        // Importações
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const']
        ],
        'no_unused_imports' => true,
        
        // Arrays modernos
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters']
        ],
        
        // Rigor e type safety
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        
        // Ordem de elementos da classe
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],
        
        // Espaçamento entre membros da classe
        'class_attributes_separation' => [
            'elements' => [
                'method' => 'one',
                'property' => 'none',
            ],
        ],
        
        // Modernização
        'use_arrow_functions' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRiskyAllowed(true);