<?php

namespace CWM\BroadWorksXsdConverter\CS;

use CWM\BroadWorksXsdConverter\ComplexType;
use CWM\BroadWorksXsdConverter\EnumType;
use CWM\BroadWorksXsdConverter\Field;
use CWM\BroadWorksXsdConverter\Schema\Choice;
use CWM\BroadWorksXsdConverter\Schema\Sequence;
use CWM\BroadWorksXsdConverter\SimpleType;
use CWM\BroadWorksXsdConverter\Type;
use RuntimeException;

class ModelWriter
{
    /** @var string */
    private $outDir;

    /** @var string */
    private $baseNamespace;

    /** @var bool */
    private $debug;

    public function __construct($outDir, $baseNamespace, $debug = false)
    {
        $this->outDir = $outDir;
        $this->baseNamespace = $baseNamespace;
        $this->debug = $debug;
    }

    /**
     * @param Type[] $types
     */
    public function write(array $types)
    {
        foreach ($types as $type) {
            $template = null;

            // Only create class files for ComplexType & EnumType
            if ($type instanceof ComplexType) {
                if ($this->debug) {
                    echo sprintf('Generating %s', $type->getName()) . PHP_EOL;
                }

                $template = $this->generateComplexTypeClass($type, $types);
            } else if ($type instanceof EnumType) {
                if ($this->debug) {
                    echo sprintf('Generating %s', $type->getName()) . PHP_EOL;
                }

                $template = $this->generateEnumType($type);
            }

            if ($template !== null) {
                $qualifiedClassName = $template->getNamespace() . '.' . $template->getName();

                // Convert fully qualified class name into PSR-4 directory structure
                $outputPath =
                    $this->outDir
                    . DIRECTORY_SEPARATOR
                    . str_replace('.', DIRECTORY_SEPARATOR, $qualifiedClassName)
                    . '.cs';

                if ($this->debug) {
                    echo sprintf('Writing %s', $outputPath) . PHP_EOL;
                }

                // Ensure the destination directory exists
                $dirPath = dirname($outputPath);
                if (!is_dir($dirPath) && !mkdir($dirPath, null, true)) {
                    throw new RuntimeException('Unable to create directory ' . $dirPath);
                }

                // Write the file
                $contents = null;
                if ($template instanceof ComplexTypeTemplate) {
                    $contents = $this->generateComplexClassFileContents($template);
                } else if ($template instanceof EnumTypeTemplate) {
                    $contents = $this->generateEnumFileContents($template);
                }

                file_put_contents($outputPath, $contents);
            }
        }
    }

    /**
     * @param ComplexType $type
     * @param Type[] $allTypes
     * @return ComplexTypeTemplate
     */
    private function generateComplexTypeClass(ComplexType $type, array $allTypes)
    {
        // Construct the CS qualified class name for this type
        $namespaceSegments = array_filter(
            explode(
                '.',
                $this->baseNamespace . '.' . str_replace(':', '.', $type->getName())
            )
        );

        list($xmlNamespace, $unqualifiedClassName) = explode(':', $type->getName());

        array_pop($namespaceSegments);
        $namespace = implode('.', $namespaceSegments);

        $usings = ['System.Collections.Generic'];

        // Construct the fully qualified class name for the parent class if this type is a sub-type
        $qualifiedParentClassName = null;

        if ($type->getParentName() !== null) {
            $qualifiedParentClassName = TypeUtils::typeNameToQualifiedName($this->baseNamespace, $type->getParentName());
        }

        // Annotations for the complex type. Start out with @see tags for references
        $tags = array_map(function($reference) {
            return new Tag('see', $reference);
        }, $type->getReferences());

        // Add @Groups tags containing details about the sequence & choice elements
        if (count($type->getGroups()) > 0) {
            $tags[] = new Tag('Groups', self::buildGroupJson($type->getGroups()));
        }

        // Find all types that extend this class
        $childClassNames = [];

        foreach ($allTypes as $otherType) {
            if ($otherType instanceof ComplexType) {
                if ($otherType->getParentName() === $type->getName()) {
                    $childClassNames[] = TypeUtils::typeNameToQualifiedName($this->baseNamespace, $otherType->getName());
                }
            }
        }

        return (new ComplexTypeTemplate())
            ->setUsings($usings)
            ->setName(trim($unqualifiedClassName))
            ->setNamespace($namespace)
            ->setXmlNamespace(trim($xmlNamespace))
            ->setParentClass($qualifiedParentClassName)
            ->setChildClasses($childClassNames)
            ->setIsAbstract($type->isAbstract())
            ->setDocumentation($type->getDescription())
            ->setReferences($type->getReferences())
            ->setTags($tags)
            ->setProperties(array_map(function($field) use($allTypes) {
                return $this->generateProperty($field, $allTypes);
            }, $type->getFields()));
    }

    /**
     * @param Field $field
     * @param array $allTypes
     * @return Property
     */
    private function generateProperty(Field $field, array $allTypes)
    {
        $csType = $this->determineCsType($field, $allTypes);

        if ($csType === null) {
            throw new RuntimeException('Unable to find type ' . $field->getTypeName());
        }

        // Contains the type annotation for the this field's property
        $propertyType = null;

        // Custom annotations placed on this field's property
        $propertyTags = [
            new Tag('ElementName', $field->getName()),
            new Tag('Type', $csType)
        ];

        if ($field->isArray()) {
            $propertyType = 'List<' . $csType . '>';
        } else {
            $propertyType = $csType;
        }

        // If nillable, the property when set to null will not be omitted. Instead, it will be sent in the response with the nil=true attribute
        if ($field->isNillable()) {
            $propertyTags[] = new Tag('Nillable');

            // Primitive value types need to be wrapped in Nullable<> as do enums
            // Arrays are ignored though since they become Lists
            $isEnumType = isset($allTypes[$field->getTypeName()]) && $allTypes[$field->getTypeName()] instanceof EnumType;

            if (($isEnumType || TypeUtils::isValueType($csType)) && !$field->isArray()) {
                $propertyType .= '?';
            }
        }

        // Add Optional annotation if the field is explicitly optional
        if ($field->isOptional()) {
            $propertyTags[] = new Tag('Optional');
        }

        // Group is a unique ID representing the parent element containing the field
        if ($field->getGroupId() !== null) {
            $propertyTags[] = new Tag('Group', $field->getGroupId());
        }

        $propertyTags = array_merge($propertyTags, $this->buildRestrictionTagsForProperty($field, $allTypes));

        return (new Property())
            ->setName(ucwords($field->getName()))
            ->setElementName($field->getName())
            ->setIsNillable($field->isNillable())
            ->setType($propertyType)
            ->setTags($propertyTags);
    }

    /**
     * @param EnumType $type
     * @return EnumTypeTemplate
     */
    private function generateEnumType(EnumType $type)
    {
        // Construct the CS qualified class name for this type
        $namespaceSegments = array_filter(
            explode(
                '.',
                $this->baseNamespace . '.' . str_replace(':', '.', $type->getName())
            )
        );

        list($xmlNamespace, $unqualifiedName) = explode(':', $type->getName());

        array_pop($namespaceSegments);
        $namespace = implode('.', $namespaceSegments);

        $usings = [];

        $enumOptions = array_map(function ($value) {
            return new EnumOption(TypeUtils::constantIdentifierForValue($value), $value);
        }, $type->getOptions());

        return (new EnumTypeTemplate())
            ->setUsings($usings)
            ->setName(trim($unqualifiedName))
            ->setNamespace($namespace)
            ->setXmlNamespace(trim($xmlNamespace))
            ->setOptions($enumOptions)
            ->setDocumentation($type->getDescription());
    }

    /**
     * @param ComplexTypeTemplate $template
     * @return string
     */
    private function generateComplexClassFileContents(ComplexTypeTemplate $template)
    {
        ob_start();
        require __DIR__ . '/templates/complexType.cs.php';
        return ob_get_clean();
    }

    /**
     * @param EnumTypeTemplate $template
     * @return string
     */
    private function generateEnumFileContents(EnumTypeTemplate $template)
    {
        ob_start();
        require __DIR__ . '/templates/enumType.cs.php';
        return ob_get_clean();
    }

    /**
     * @param Field $field
     * @param Type[] $allTypes
     * @return null|string
     */
    private function determineCsType(Field $field, array $allTypes)
    {
        $csType = null;

        // Check if referenced type is an XSD type. If so, use a primitive C# type
        if (TypeUtils::isXsdType($field->getTypeName())) {
            $csType = TypeUtils::xsdToPrimitiveType($field->getTypeName());
        } else if (array_key_exists($field->getTypeName(), $allTypes)) {
            $type = $allTypes[$field->getTypeName()];

            if ($type instanceof EnumType) {
                // Handle EnumType, which will return a fully qualified class name
                $csType = TypeUtils::typeNameToQualifiedName($this->baseNamespace, $type->getName());
            } else if ($type instanceof SimpleType) {
                // Handle SimpleType, which will return a primitive type
                $csType = $this->simpleTypeToCsType($type, $allTypes);
            } else if ($type instanceof ComplexType) {
                // Handle ComplexType, which will return a fully qualified class name
                $csType = TypeUtils::typeNameToQualifiedName($this->baseNamespace, $type->getName());
            }
        }

        return $csType;
    }

    /**
     * @param SimpleType $type
     * @param Type[] $allTypes
     * @return string|null
     */
    private function simpleTypeToCsType(SimpleType $type, $allTypes)
    {
        $csType = null;

        $restriction = $type->getRestriction()->getBase();
        // Check if referenced type is an XSD type. If so, use a primitive type
        if (TypeUtils::isXsdType($restriction)) {
            $csType = TypeUtils::xsdToPrimitiveType($restriction);
        } else {
            // Restriction can also be a simple type
            if (array_key_exists($restriction, $allTypes)) {
                $restrictionType = $allTypes[$restriction];
                if ($restrictionType instanceof SimpleType) {
                    $csType = $this->simpleTypeToCsType($restrictionType, $allTypes);
                }
            }
        }


        return $csType;
    }

    /**
     * @param Sequence[]|Choice[] $groups
     * @return string
     */
    private static function buildGroupJson(array $groups)
    {
        $convert = function($group) use (&$convert) {
            /** @var Sequence|Choice $group */
            $json = [
                'id' => $group->getId(),
                'type' => ($group instanceof Sequence)
                    ? 'sequence'
                    : 'choice'
            ];

            if (($group instanceof Choice) && $group->isOptional()) {
                $json['optional'] = true;
            }

            if (count($group->getChildren()) > 0) {
                $json['children'] = array_map($convert, $group->getChildren());
            }

            return $json;
        };

        return trim(json_encode(array_map($convert, $groups)));
    }

    /**
     * Returns an array of Tag for a property
     *
     * @param Field $field
     * @param array $allTypes
     * @return array
     */
    private function buildRestrictionTagsForProperty(Field $field, array $allTypes)
    {
        $tags = [];

        if (array_key_exists($field->getTypeName(), $allTypes) && !TypeUtils::isXsdType($field->getTypeName())) {
            $type = $allTypes[$field->getTypeName()];

            if ($type instanceof SimpleType) {
                $restriction = $type->getRestriction();
                if ($restriction !== null) {
                    if ($restriction->getLength() !== null) {
                        $tags[] = new Tag('Length', $restriction->getLength());
                    }

                    if ($restriction->getMinLength() !== null) {
                        $tags[] = new Tag('MinLength', $restriction->getMinLength());
                    }

                    if ($restriction->getMaxLength() !== null) {
                        $tags[] = new Tag('MaxLength', $restriction->getMaxLength());
                    }

                    if ($restriction->getMinInclusive() !== null) {
                        $tags[] = new Tag('MinInclusive', $restriction->getMinInclusive());
                    }

                    if ($restriction->getMaxInclusive() !== null) {
                        $tags[] = new Tag('MaxInclusive', $restriction->getMaxInclusive());
                    }

                    if ($restriction->getPattern() !== null) {
                        $tags[] = new Tag('Pattern', $restriction->getPattern());
                    }

                    if ($restriction->getWhiteSpace() !== null) {
                        $tags[] = new Tag('Whitespace', $restriction->getWhiteSpace());
                    }
                }
            }
        }

        return $tags;
    }

}