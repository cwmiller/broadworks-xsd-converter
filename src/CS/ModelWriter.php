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

    /** @var string */
    private $validationNamespace;

    /** @var bool */
    private $debug;

    public function __construct($outDir, $baseNamespace, $validationNamespace, $debug = false)
    {
        $this->outDir = $outDir;
        $this->baseNamespace = $baseNamespace;
        $this->validationNamespace = $validationNamespace;
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

        $usings = [
            $this->validationNamespace,
            'System.Collections.Generic'
        ];
        $annotations = [];

        // Add Groups annotation containing details about the sequence & choice elements
        if (count($type->getGroups()) > 0) {
            $annotations[] = new Annotation('Groups', self::buildGroupJson($type->getGroups()));
        }

        // Construct the fully qualified class name for the parent class if this type is a sub-type
        $qualifiedParentClassName = null;

        //try {
            $qualifiedParentClassName = $this->determineComplexParentClass($type, $allTypes);
        //} catch(RuntimeException $e) {
        //    echo $e->getMessage() . PHP_EOL;
        //}

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
            ->setAnnotations($annotations)
            ->setProperties(array_map(function($field) use($allTypes) {
                return $this->generateProperty($field, $allTypes);
            }, $type->getFields()));
    }

    private function determineComplexParentClass(ComplexType $type, array $allTypes)
    {
        $parentClass = null;

        if ($type->getParentName() !== null) {
            $parentClass = TypeUtils::typeNameToQualifiedName($this->baseNamespace, $type->getParentName());

            // OCIRequest is now OCIRequest<T> where T is the response type for the request.
            // Find the appropriate response type for T
            if ($type->getParentName() === 'C:OCIRequest') {
                $rawResponseTypes = $type->getResponseTypes();

                if (count($rawResponseTypes) === 0) {
                    throw new RuntimeException('No response types for ' . $type->getName());
                }

                if (count($rawResponseTypes) > 1) {
                    echo 'Multiple response types for ' . $type->getName()  . '. Response type will be OCIResponse.' . PHP_EOL;

                    $rawResponseTypes = [':C:OCIResponse'];
                }

                $expectedResponseTypes = [
                    ':C:OCIResponse',
                    ':C:SuccessResponse',
                    'SuccessResponse',
                    ':' . ltrim(str_replace('Request', 'Response', $type->getName()), ':'),
                    ltrim(str_replace('Request', 'Response', $type->getName()), ':')
                ];

                $rawResponseType = $rawResponseTypes[0];

                if (!in_array($rawResponseType, $expectedResponseTypes)) {
                    //throw new RuntimeException('Response ' . $rawResponseType . ' for ' . $type->getName() . ' doesn\'t look like a proper response type for this request.');

                    // If a type exists that's obviously the response, use it
                    $possibleResponseType = str_replace('Request', 'Response', $type->getName());

                    $found = false;
                    foreach ($allTypes as $otherType) {
                        if ($otherType->getName() === $possibleResponseType) {
                            $found = true;
                            echo 'Assuming ' . $possibleResponseType . ' for ' . $type->getName() . PHP_EOL;
                            break;
                        }
                    }

                    if ($found) {
                        $rawResponseType = $possibleResponseType;
                    } else {
                        throw new RuntimeException('Response ' . $rawResponseType . ' for ' . $type->getName() . ' doesn\'t look like a proper response type for this request.');

                    }
                }

                $responseType = TypeUtils::typeNameToQualifiedName($this->baseNamespace, $rawResponseType);

                //if (strpos($responseType, 'Response') === false) {
                    //throw new RuntimeException('Response ' . $responseType . ' for ' . $type->getName() . ' doesn\'t seem like a proper response type.');
                //}

                $parentClass .= '<' . $responseType . '>';

                /*
                $responseNamespace = explode('.', $responseType);
                array_pop($responseNamespace);
                */
            }
        }

        return $parentClass;
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

        if ($field->isArray()) {
            $propertyType = 'List<' . $csType . '>';
        } else {
            $propertyType = $csType;
        }

        $defaultValue = $field->isArray() ? 'new List<' . $csType . '>()' : null;

        // If nillable, the property when set to null will not be omitted. Instead, it will be sent in the response with the nil=true attribute
        if ($field->isNillable()) {
            // Primitive value types need to be wrapped in Nullable<> as do enums
            // Arrays are ignored though since they become Lists
            $isEnumType = isset($allTypes[$field->getTypeName()]) && $allTypes[$field->getTypeName()] instanceof EnumType;

            if (($isEnumType || TypeUtils::isValueType($csType)) && !$field->isArray()) {
                $propertyType .= '?';
            }
        }

        // Add Optional annotation if the field is explicitly optional
        if ($field->isOptional()) {
            $propertyAnnotations[] = new Annotation('Optional');
        }

        // Group is a unique ID representing the parent element containing the field
        if ($field->getGroupId() !== null) {
            $propertyAnnotations[] = new Annotation('Group', $field->getGroupId());
        }

        $propertyAnnotations = array_merge($propertyAnnotations, $this->buildRestrictionAnnotationsForProperty($field, $allTypes));

        return (new Property())
            ->setName(ucwords($field->getName()))
            ->setElementName($field->getName())
            ->setIsNillable($field->isNillable())
            ->setType($propertyType)
            ->setAnnotations($propertyAnnotations)
            ->setDefaultValue($defaultValue);
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
    private function buildGroupJson(array $groups)
    {
        $convert = function($group) use (&$convert) {
            /** @var Sequence|Choice $group */
            $json = [
                '__type' => ($group instanceof Sequence)
                    ? 'Sequence:#' . $this->validationNamespace
                    : 'Choice:#' . $this->validationNamespace,
                'id' => $group->getId()
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
     * Returns an array of Annotations for a property
     *
     * @param Field $field
     * @param array $allTypes
     * @return array
     */
    private function buildRestrictionAnnotationsForProperty(Field $field, array $allTypes)
    {
        $annotations = [];

        if (array_key_exists($field->getTypeName(), $allTypes) && !TypeUtils::isXsdType($field->getTypeName())) {
            $type = $allTypes[$field->getTypeName()];

            if ($type instanceof SimpleType) {
                $restriction = $type->getRestriction();
                if ($restriction !== null) {
                    if ($restriction->getLength() !== null) {
                        $annotations[] = new Annotation('Length', $restriction->getLength());
                    }

                    if ($restriction->getMinLength() !== null) {
                        $annotations[] = new Annotation('MinLength', $restriction->getMinLength());
                    }

                    if ($restriction->getMaxLength() !== null) {
                        $annotations[] = new Annotation('MaxLength', $restriction->getMaxLength());
                    }

                    if ($restriction->getMinInclusive() !== null) {
                        $annotations[] = new Annotation('MinInclusive', $restriction->getMinInclusive());
                    }

                    if ($restriction->getMaxInclusive() !== null) {
                        $annotations[] = new Annotation('MaxInclusive', $restriction->getMaxInclusive());
                    }

                    if ($restriction->getPattern() !== null) {
                        $annotations[] = new Annotation('RegularExpression', $restriction->getPattern());
                    }
                }
            }
        }

        return $annotations;
    }

}