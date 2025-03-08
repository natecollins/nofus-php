<?php
// https://cs.symfony.com/doc/rules/index.html

$rules = [
    '@PHP82Migration:risky' => true,
    '@PHP83Migration' => true,
    '@PHPUnit100Migration:risky' => true,
    '@PSR12' => true,
    'align_multiline_comment' => true,
    'array_syntax' => true,
    'binary_operator_spaces' => true,
    'blank_line_after_opening_tag' => false,
    'cast_spaces' => true,
    'class_attributes_separation' => [
        'elements' => [
            'method' => 'one',
            'property' => 'none',
        ]
    ],
    'concat_space' => ['spacing' => 'one'],
    'function_declaration' => true,
    'get_class_to_class_keyword' => true,
    'global_namespace_import' => [
        'import_functions' => true,
        'import_classes' => true,
    ],
    'is_null' => true,
    'linebreak_after_opening_tag' => true,
    'lowercase_cast' => true,
    'magic_constant_casing' => true,
    'modernize_strpos' => true,
    'native_function_casing' => true,
    'native_function_invocation' => [
        'strict' => true,
        'scope' => 'namespaced',
    ],
    'new_with_parentheses' => true,
    'no_alias_functions' => true,
    'no_blank_lines_after_class_opening' => true,
    'no_blank_lines_after_phpdoc' => true,
    'no_empty_comment' => true,
    'no_empty_phpdoc' => true,
    'no_empty_statement' => true,
    'no_extra_blank_lines' => true,
    'no_leading_import_slash' => true,
    'no_leading_namespace_whitespace' => true,
    'no_mixed_echo_print' => true,
    'no_singleline_whitespace_before_semicolons' => true,
    'no_spaces_around_offset' => true,
    'no_unneeded_braces' => true,
    'no_unneeded_control_parentheses' => true,
    'no_unneeded_final_method' => true,
    'no_unused_imports' => true,
    'no_useless_return' => true,
    'no_whitespace_in_blank_line' => true,
    'non_printable_character' => true,
    'ordered_imports' => [
        'imports_order' => ['class', 'function', 'const'],
        'sort_algorithm' => 'alpha',
    ],
    'phpdoc_add_missing_param_annotation' => true,
    'phpdoc_align' => true,
    'phpdoc_no_access' => true,
    'phpdoc_separation' => true,
    'php_unit_method_casing' => true,
    'pow_to_exponentiation' => true,
    'single_line_after_imports' => true,
    'standardize_not_equals' => true,
    'ternary_operator_spaces' => true,
    'ternary_to_elvis_operator' => true,
    'ternary_to_null_coalescing' => true,
    'trailing_comma_in_multiline' => true,
    'type_declaration_spaces' => true,
];

$config = new PhpCsFixer\Config();
return $config->setCacheFile('.php-cs-code.cache')
    ->setRiskyAllowed(true)
    ->setRules($rules)
    ->setParallelConfig(\PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
