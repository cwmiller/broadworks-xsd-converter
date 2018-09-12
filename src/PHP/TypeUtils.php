<?php

namespace CWM\BroadWorksXsdConverter\PHP;

abstract class TypeUtils
{
    public static $keywords = [
        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'eval',
        'exit',
        'extends',
        'final',
        'finally',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'namespace',
        'new',
        'or',
        'print',
        'private',
        'protected',
        'public',
        'require',
        'require_once',
        'return',
        'static',
        'switch',
        'throw',
        'trait',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
        'yield'
    ];

    /**
     * Returns if the given type is an XSD type
     *
     * @param string $typeName
     * @return bool
     */
    public static function isXsdType($typeName)
    {
        return strpos($typeName, 'http://www.w3.org/2001/XMLSchema') === 0;
    }

    /**
     * Get the PHP primitive type for the given XSD type
     *
     * @param string $xsdType
     * @return string
     */
    public static function xsdToPrimitiveType($xsdType)
    {
        $xsdType = substr($xsdType, strlen('http://www.w3.org/2001/XMLSchema') + 1);

        switch ($xsdType) {
            case 'float':
                return 'float';
            case 'int':
                return 'int';
            case 'boolean':
                return 'bool';
            default:
                return 'string';
        }
    }

    /**
     * Converts the value from an enum into a legal identifier
     *
     * @param $value
     * @return string
     */
    public static function constantIdentifierForValue($value)
    {
        // The identifier for the constant can't just be an all-caps representation of the value.
        // Identifiers can't begin with numbers or contain non-alphanumeric characters.

        // Start by replacing hash & star characters
        $constName = str_replace(['*', '#'], ['STAR', 'HASH'], $value);

        // Replace any non-alphanumeric characters with underscores
        $constName = preg_replace('/[^a-z0-9]/i', '_', $constName);

        // Replace sequences of underscores with just a single one
        $constName = preg_replace('/_+/', '_', $constName);

        // Prepend an underscore if the identifier begins with a number
        if (preg_match('/^\d/', $constName)) {
            $constName = '_' . $constName;
        }

        // Prepend an underscore if the identifier is a reserved keyword
        if (in_array(strtolower($constName), self::$keywords, true)) {
            $constName = '_' . $constName;
        }

        // Finally, capitalize the identifier
        return strtoupper($constName);
    }

    /**
     * Converts the given type name to a fully qualified PHP class name
     *
     * @param string $baseNamespace
     * @param string $typeName
     * @return string
     */
    public static function typeNameToQualifiedName($baseNamespace, $typeName)
    {
        return '\\' . implode(
                '\\',
                array_filter(
                    explode(
                        '\\',
                        $baseNamespace . '\\' . str_replace(':', '\\', $typeName)
                    )
                )
            );
    }
}