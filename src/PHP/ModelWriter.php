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
use Zend\Code\Generator\DocBlock\Tag\MethodTag;
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

    /** @var string */
    private $nilClassname;

    /** @var bool */
    private $debug;

    const ENUM_BASE_TYPE = '\MyCLabs\Enum\Enum';

    /**
     * @param string $outDir The root directory to write files to.
     * @param string $baseNamespace The base namespace for the generated classes.
     * @param string $nilClassname The class name of the Nil class for nillable elements.
     * @param bool $debug
     */
    public function __construct($outDir, $baseNamespace, $nilClassname, $debug = false)
    {
        $this->outDir = $outDir;
        $this->baseNamespace = $baseNamespace;
        $this->nilClassname = $nilClassname;
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
                'tags' => array_map(function($reference) {
                    return new GenericTag('see', $reference);
                }, $type->getReferences())
            ]));

        // Create property, getter, and setter for each field on the type
        foreach ($type->getFields() as $field) {
            $this->generateProperty($class, $field, $allTypes);
        }

        return $class;
    }

    /**
     * @param ClassGenerator $class
     * @param Field $field
     * @param array $allTypes
     * @throws \Zend\Code\Generator\Exception\InvalidArgumentException
     */
    private function generateProperty(ClassGenerator $class, Field $field, array $allTypes)
    {
        $phpType = $this->determinePhpType($field, $allTypes);

        $commonTags = [
            new GenericTag('ElementName', $field->getName())
        ];

        if ($phpType === null) {
            throw new RuntimeException('Unable to find type ' . $field->getTypeName());
        }

        $propertyPhpDoc = $phpType;

        // If the field is an array, then adjust the phpdoc to indicate it is an array
        if ($field->isArray()) {
            $propertyPhpDoc .= '[]';
        } else {
            // If the field is not an array, then the field can be null if it is not set.
            $propertyPhpDoc .= '|null';
        }

        // If the field is an array, then the default value is a blank array. If not, then it's null.
        $defaultValue = $field->isArray()
            ? []
            : null;

        if ($field->isNillable()) {
            $propertyPhpDoc .= '|' . $this->nilClassname;
            $commonTags[] = new GenericTag('Nillable');
        }

        // Create private property for field
        $property = (new PropertyGenerator())
            ->setName($field->getName())
            ->setFlags(PropertyGenerator::FLAG_PRIVATE)
            ->setDefaultValue($defaultValue)
            ->setDocBlock((new DocBlockGenerator())
                ->setLongDescription($field->getDescription())
                ->setTags(array_merge($commonTags, [
                    new GenericTag('var', $propertyPhpDoc)
                ]))
                ->setWordWrap(false));

        $class->addPropertyFromGenerator($property);

        // Create getter for field
        $getter = (new MethodGenerator())
            ->setBody(sprintf('return $this->%s;', $field->getName()))
            ->setName(sprintf('get%s', ucwords($field->getName())))
            ->setDocBlock((new DocBlockGenerator())
                ->setShortDescription('Getter for ' . $field->getName())
                ->setLongDescription($field->getDescription())
                ->setTags(array_merge($commonTags, [
                    new ReturnTag(['datatype' => $propertyPhpDoc])
                ]))
                ->setWordWrap(false));

        $class->addMethodFromGenerator($getter);

        // Create setter for field
        $setterTypeHint = null;

        if ($field->isArray()) {
            $setterTypeHint = 'array';
        } else if (!self::isScalar($phpType)) {
            $setterTypeHint = $phpType;
        }

        $setter = (new MethodGenerator())
            ->setName(sprintf('set%s', ucwords($field->getName())))
            ->setBody(sprintf("\$this->%s = $%s;\nreturn \$this;", $field->getName(), $field->getName()))
            ->setParameter(array_merge([
                'name' => $field->getName(),
            ], $setterTypeHint !== null ? ['type' => $setterTypeHint] : []))
            ->setDocBlock((new DocBlockGenerator())
                ->setShortDescription('Setter for ' . $field->getName())
                ->setLongDescription($field->getDescription())
                ->setTags(array_merge($commonTags, [
                    new ParamTag($field->getName(), $propertyPhpDoc),
                    new ReturnTag(['datatype' => '$this'])
                ]))
                ->setWordWrap(false));

        $class->addMethodFromGenerator($setter);

        // Create adder for field if array
        if ($field->isArray()) {
            $adder = (new MethodGenerator())
                ->setName(sprintf('add%s', ucwords($field->getName())))
                ->setBody(sprintf("\$this->%s []= $%s;\nreturn \$this;", $field->getName(), $field->getName()))
                ->setParameter(array_merge([
                    'name' => $field->getName(),
                ], self::isScalar($phpType) ? ['type' => $phpType] : []))
                ->setDocBlock((new DocBlockGenerator())
                    ->setShortDescription('Adder for ' . $field->getName())
                    ->setLongDescription($field->getDescription())
                    ->setTags(array_merge($commonTags, [
                        new ParamTag($field->getName(), $phpType),
                        new ReturnTag(['datatype' => '$this'])
                    ]))
                    ->setWordWrap(false));

            $class->addMethodFromGenerator($adder);
        }
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

        $valueType = TypeUtils::xsdToPrimitiveType($type->getRestriction());

        // Create class
        $class = (new ClassGenerator())
            ->setName($unqualifiedClassName)
            ->setNamespaceName($namespace)
            ->setExtendedClass(self::ENUM_BASE_TYPE)
            ->setDocBlock(DocBlockGenerator::fromArray([
                'shortDescription' => $unqualifiedClassName,
                'longDescription' => $type->getDescription(),
                // Create PHPDoc tags for the following:
                // * @see references for related classes
                // * @method tags for each option
                // * @ValueType contains the primitive type (int, string) of the enum
                'tags' => array_merge(array_map(function($reference) {
                    return new GenericTag('see', $reference);
                }, $type->getReferences()), array_map(function($option) use($unqualifiedClassName) {
                    return new MethodTag(TypeUtils::constantIdentifierForValue($option), $unqualifiedClassName, null, true);
                }, $type->getOptions()), [
                    new GenericTag('ValueType', $valueType)
                ])
            ]));

        // Create a const for each possible value
        foreach ($type->getOptions() as $option) {
            // $option will be a string coming from the parser. It needs to be cast to int/float for the const.
            switch ($valueType) {
                case 'int':
                    $option = (int)$option;
                    break;
                case 'float':
                    $option = (float)$option;
                    break;
            }

            $class->addConstant(TypeUtils::constantIdentifierForValue($option), $option);
        }

        return $class;
    }



    /**
     * Retrieve the PHP type(s) for the given field
     *
     * The PHP type can be a qualified class name for a ComplexType, EnumType, or a PHP primitive for a SimpleType.
     *
     * @param Field $field
     * @param Type[] $allTypes
     * @return string
     */
    private function determinePhpType(Field $field, array $allTypes)
    {
        $phpType = null;

        // Check if referenced type is an XSD type. If so, use a primitive PHP type
        if (TypeUtils::isXsdType($field->getTypeName())) {
            $phpType = TypeUtils::xsdToPrimitiveType($field->getTypeName());
        } else if (array_key_exists($field->getTypeName(), $allTypes)) {
            $type = $allTypes[$field->getTypeName()];

            if ($type instanceof EnumType) {
                // Handle EnumType, which will return a fully qualified class name
                $phpType = TypeUtils::typeNameToQualifiedName($this->baseNamespace, $type->getName());
            } else if ($type instanceof SimpleType) {
                // Handle SimpleType, which will return a primitive type
                $phpType = $this->simpleTypeToPhpType($type, $allTypes);
            } else if ($type instanceof ComplexType) {
                // Handle ComplexType, which will return a fully qualified class name
                $phpType = TypeUtils::typeNameToQualifiedName($this->baseNamespace, $type->getName());
            }
        }

        return $phpType;
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

    private static function isScalar($typeName)
    {
        return in_array($typeName, [
            'string',
            'int',
            'bool',
            'float'
        ], true);
    }
}