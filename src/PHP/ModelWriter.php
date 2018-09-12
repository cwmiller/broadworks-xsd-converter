<?php

namespace CWM\BroadWorksXsdConverter\PHP;

use CWM\BroadWorksXsdConverter\ComplexType;
use CWM\BroadWorksXsdConverter\EnumType;
use CWM\BroadWorksXsdConverter\Field;
use CWM\BroadWorksXsdConverter\SimpleType;
use CWM\BroadWorksXsdConverter\Tag;
use CWM\BroadWorksXsdConverter\Type;
use RuntimeException;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\PropertyGenerator;

class ModelWriter
{
    /** @var string */
    private $outDir;

    /** @var string */
    private $baseNamespace;

    /** @var bool */
    private $debug;

    /**
     * @param string $outDir The root directory to write files to.
     * @param string $baseNamespace The base namespace for the generated classes.
     * @param bool $debug
     */
    public function __construct($outDir, $baseNamespace, $debug = false)
    {
        $this->outDir = $outDir;
        $this->baseNamespace = $baseNamespace;
        $this->debug = $debug;
    }

    /**
     * Write a class file for all ComplexTypes & EnumTypes
     *
     * @param Type[] $types
     * @throws \Zend\Code\Generator\Exception\InvalidArgumentException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function write(array $types)
    {
        foreach ($types as $type) {
            $class = null;

            // Only create class files for ComplexType & EnumType
            if ($type instanceof ComplexType) {
                $class = $this->generateComplexTypeClass($type, $types);
            } else if ($type instanceof EnumType) {
                $class = $this->generateEnumTypeClass($type);
            }

            if ($class !== null) {
                $qualifiedClassName = $class->getNamespaceName() . '\\' . $class->getName();

                // Convert fully qualified class name into PSR-4 directory structure
                $outputPath =
                    $this->outDir
                    . DIRECTORY_SEPARATOR
                    . str_replace('\\', DIRECTORY_SEPARATOR, $qualifiedClassName)
                    . '.php';

                if ($this->debug) {
                    echo sprintf('Writing %s', $outputPath) . PHP_EOL;
                }

                // Ensure the destination directory exists
                $dirPath = dirname($outputPath);
                if (!is_dir($dirPath) && !mkdir($dirPath, null, true)) {
                    throw new RuntimeException('Unable to create directory ' . $dirPath);
                }

                // Write the file
                $file = new FileGenerator(['classes' => [$class]]);
                if (!file_put_contents($outputPath, $file->generate())) {
                    throw new RuntimeException('Unable to write file ' . $outputPath);
                }
            }
        }
    }

    /**
     * Create a ClassGenerator for a ComplexType
     *
     * @param ComplexType $type
     * @param Type[] $allTypes
     * @return ClassGenerator
     * @throws \Zend\Code\Generator\Exception\InvalidArgumentException
     */
    private function generateComplexTypeClass(ComplexType $type, array $allTypes)
    {
        // Construct the PHP qualified class name for this type
        $namespaceSegments = array_filter(
            explode(
                '\\',
                $this->baseNamespace . '\\' . str_replace(':', '\\', $type->getName())
            )
        );
        //$qualifiedClassName = implode('\\', $namespaceSegments);
        $unqualifiedClassName = array_pop($namespaceSegments);
        $namespace = implode('\\', $namespaceSegments);

        // Construct the fully qualified class name for the parent class if this type is a sub-type
        $qualifiedParentClassName = null;

        if ($type->getParentName() !== null) {
            $qualifiedParentClassName = TypeUtils::typeNameToQualifiedName($this->baseNamespace, $type->getParentName());
        }

        // Create class
        $class = (new ClassGenerator())
            ->setName($unqualifiedClassName)
            ->setNamespaceName($namespace)
            ->setFlags($type->isAbstract() ? ClassGenerator::FLAG_ABSTRACT : 0)
            ->setExtendedClass($qualifiedParentClassName)
            ->setDocBlock(DocBlockGenerator::fromArray([
                'shortDescription' => $unqualifiedClassName,
                'longDescription' => $type->getDescription(),
                'tags' => array_map(function($tag) {
                    /** @var Tag $tag */
                    return new GenericTag($tag->getName(), $tag->getValue());
                }, $type->getTags())
            ]));

        // Create property, getter, and setter for each field on the type
        foreach ($type->getFields() as $field) {
            list($phpType, $enumType) = $this->determinePhpType($field, $allTypes);

            if ($phpType === null) {
                throw new RuntimeException('Unable to find type ' . $field->getTypeName());
            }

            // If the field is an array, then adjust the phpdoc to indicate it is an array
            $propertyPhpType = $field->isArray()
                ? $phpType . '[]'
                : $phpType;

            // If the field is not an array, then the field can be null if it is not set.
            if (!$field->isArray()) {
                $propertyPhpType .= '|null';
            }

            // If the field is an array, then the default value is a blank array. If not, then it's null.
            $defaultValue = $field->isArray()
                ? []
                : null;

            // Create private property for field
            $property = (new PropertyGenerator())
                ->setName($field->getName())
                ->setFlags(PropertyGenerator::FLAG_PRIVATE)
                ->setDefaultValue($defaultValue)
                ->setDocBlock((new DocBlockGenerator())
                    ->setLongDescription($field->getDescription())
                    ->setTags(array_merge([
                        new GenericTag('ElementName', $field->getName()),
                        new GenericTag('var', $propertyPhpType)
                    ], $enumType !== null ? [new GenericTag('see', $enumType)] : []))
                    ->setWordWrap(false));

            $class->addPropertyFromGenerator($property);

            // Create getter for field
            $getter = (new MethodGenerator())
                ->setBody(sprintf('return $this->%s;', $field->getName()))
                ->setName(sprintf('get%s', ucwords($field->getName())))
                ->setDocBlock((new DocBlockGenerator())
                    ->setShortDescription('Getter for ' . $field->getName())
                    ->setLongDescription($field->getDescription())
                    ->setTags(array_merge([
                        new GenericTag('ElementName', $field->getName()),
                        new ReturnTag(['datatype' => $propertyPhpType])
                    ], $enumType !== null ? [new GenericTag('see', $enumType)] : []))
                    ->setWordWrap(false));

            $class->addMethodFromGenerator($getter);

            // Create setter for field
            $setter = (new MethodGenerator())
                ->setName(sprintf('set%s', ucwords($field->getName())))
                ->setBody(sprintf("\$this->%s = $%s;\nreturn \$this;", $field->getName(), $field->getName()))
                ->setParameter(['name' => $field->getName()])
                ->setDocBlock((new DocBlockGenerator())
                    ->setShortDescription('Setter for ' . $field->getName())
                    ->setLongDescription($field->getDescription())
                    ->setTags(array_merge([
                        new GenericTag('ElementName', $field->getName()),
                        new ParamTag($field->getName(), $propertyPhpType),
                        new ReturnTag(['datatype' => '$this'])
                    ], $enumType !== null ? [new GenericTag('see', $enumType)] : []))
                    ->setWordWrap(false));

            $class->addMethodFromGenerator($setter);

            // Create adder for field if array
            if ($field->isArray()) {
                $adder = (new MethodGenerator())
                    ->setName(sprintf('add%s', ucwords($field->getName())))
                    ->setBody(sprintf("\$this->%s []= $%s;\nreturn \$this;", $field->getName(), $field->getName()))
                    ->setParameter(['name' => $field->getName()])
                    ->setDocBlock((new DocBlockGenerator())
                        ->setShortDescription('Adder for ' . $field->getName())
                        ->setLongDescription($field->getDescription())
                        ->setTags([
                            new GenericTag('ElementName', $field->getName()),
                            new ParamTag($field->getName(), $phpType),
                            new ReturnTag(['datatype' => '$this'])
                        ])
                        ->setWordWrap(false));

                $class->addMethodFromGenerator($adder);
            }
        }

        return $class;
    }

    /**
     * Create a ClassGenerator for an EnumType
     *
     * @param EnumType $type
     * @return ClassGenerator
     * @throws \Zend\Code\Generator\Exception\InvalidArgumentException
     */
    private function generateEnumTypeClass(EnumType $type)
    {
        // Construct the PHP qualified class name for this type
        $namespaceSegments = array_filter(
            explode(
                '\\',
                $this->baseNamespace . '\\' . str_replace(':', '\\', $type->getName())
            )
        );
        $unqualifiedClassName = array_pop($namespaceSegments);
        $namespace = implode('\\', $namespaceSegments);

        // Create class
        $class = (new ClassGenerator())
            ->setName($unqualifiedClassName)
            ->setNamespaceName($namespace)
            ->setFlags(ClassGenerator::FLAG_ABSTRACT)
            ->setDocBlock(DocBlockGenerator::fromArray([
                'shortDescription' => $unqualifiedClassName,
                'longDescription' => $type->getDescription(),
                'tags' => array_map(function($tag) {
                    /** @var Tag $tag */
                    return new GenericTag($tag->getName(), $tag->getValue());
                }, $type->getTags())
            ]));

        // Create a const for each possible value
        foreach ($type->getValues() as $value) {
            $class->addConstant(TypeUtils::constantIdentifierForValue($value), $value);
        }

        return $class;
    }



    /**
     * Retrieve the PHP type for the given field as a tuple (phpType, enumType)
     *
     * The PHP type can be a qualified class name for a ComplexType or a PHP primitive for a SimpleType.
     * If the result is an EnumType (derived from SimpleType), then a qualified class name is also returned which
     * contains constants for the valid values for the field.
     *
     * @param Field $field
     * @param Type[] $allTypes
     * @return array
     */
    private function determinePhpType(Field $field, array $allTypes)
    {
        $phpType = null;
        $enumType = null;

        // Check if referenced type is an XSD type. If so, use a primitive PHP type
        if (TypeUtils::isXsdType($field->getTypeName())) {
            $phpType = TypeUtils::xsdToPrimitiveType($field->getTypeName());
        } else if (array_key_exists($field->getTypeName(), $allTypes)) {
            $type = $allTypes[$field->getTypeName()];

            // Handle SimpleType fields. SimpleTypes always get turned into primitive PHP types
            if ($type instanceof SimpleType) {
                $phpType = $this->simpleTypeToPhpType($type, $allTypes);

                // If also an EnumType, get the PHP class that represents the enum
                if ($type instanceof EnumType) {
                    $enumType = TypeUtils::typeNameToQualifiedName($this->baseNamespace, $type->getName());
                }
            // Handle ComplexType fields. ComplexTypes get turned into PHP classes
            } else if ($type instanceof ComplexType) {
                $phpType = TypeUtils::typeNameToQualifiedName($this->baseNamespace, $type->getName());
            }
        }

        return [
            $phpType,
            $enumType
        ];
    }

    /**
     * Retrieve the primitive PHP type for a SimpleType
     *
     * @param SimpleType $type
     * @param Type[] $allTypes
     * @return string|null
     */
    private function simpleTypeToPhpType(SimpleType $type, $allTypes)
    {
        $phpType = null;

        $restriction = $type->getRestriction();
        // Check if referenced type is an XSD type. If so, use a primitive type
        if (TypeUtils::isXsdType($restriction)) {
            $phpType = TypeUtils::xsdToPrimitiveType($restriction);
        } else {
            // Restriction can also be a simple type
            if (array_key_exists($restriction, $allTypes)) {
                $restrictionType = $allTypes[$restriction];
                if ($restrictionType instanceof SimpleType) {
                    $phpType = $this->simpleTypeToPhpType($restrictionType, $allTypes);
                }
            }
        }

        return $phpType;
    }
}