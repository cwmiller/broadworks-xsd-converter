<?php

namespace CWM\BroadWorksXsdConverter\CS;

abstract class TypeUtils
{
    /**
     * Converts the given type name to a fully qualified CS class name
     *
     * @param string $baseNamespace
     * @param string $typeName
     * @return string
     */
    public static function typeNameToQualifiedName($baseNamespace, $typeName)
    {
        return implode(
                '.',
                array_filter(
                    explode(
                        '.',
                        $baseNamespace . '.' . str_replace(':', '.', $typeName)
                    )
                )
            );
    }

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
     * Get the C# primitive type for the given XSD type
     *
     * @param string $xsdType
     * @return string
     */
    public static function xsdToPrimitiveType($xsdType)
    {
        $xsdType = substr($xsdType, strlen('http://www.w3.org/2001/XMLSchema') + 1);

        switch ($xsdType) {
            case 'float':
                return 'decimal';
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
        // The identifier for the constant can't just be the value.
        // Identifiers can't begin with numbers or contain non-alphanumeric characters.

        // Start by replacing pound & star characters
        $constName = str_replace(['*', '#'], ['Star', 'Pound'], $value);

        // Remove any non-alphanumeric characters
        $constName = preg_replace('/[^a-z0-9]/i', '', $constName);

        // Prepend an underscore if the identifier begins with a number
        if (preg_match('/^\d/', $constName)) {
            $constName = '_' . $constName;
        }

        // Finally, CamelCase the identifier
        return ucwords($constName);
    }

    public static function isPrimitiveType($type) {
        switch ($type) {
            case 'bool':
            case 'byte':
            case 'sbyte':
            case 'char':
            case 'decimal':
            case 'double':
            case 'float':
            case 'int':
            case 'uint':
            case 'long':
            case 'ulong':
            case 'object':
            case 'short':
            case 'ushort':
            case 'string':
                return true;
            default:
                return false;
        }
    }

    public static function isValueType($type) {
        switch ($type) {
            case 'bool':
            case 'byte':
            case 'char':
            case 'decimal':
            case 'double':
            case 'enum':
            case 'float':
            case 'int':
            case 'long':
            case 'sbyte':
            case 'short':
            case 'struct':
            case 'uint':
            case 'ulong':
            case 'ushort':
                return true;
            default:
                return false;
        }
    }

}