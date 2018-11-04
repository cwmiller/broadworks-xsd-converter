<?php

namespace CWM\BroadWorksXsdConverter;

use RuntimeException;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\DocBlock\Tag\PropertyTag;
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
    private $namespace;

    /** @var string */
    private $nilClassname;

    /** @var bool */
    private $debug;

    public function __construct($outDir, $namespace, $nilClassname, $debug = false)
    {
        $this->outDir = $outDir;
        $this->namespace = $namespace;
        $this->nilClassname = $nilClassname;
        $this->debug = $debug;
    }

    /**
     * @param Type[] $types
     * @throws \Zend\Code\Generator\Exception\InvalidArgumentException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function write(array $types)
    {
        foreach ($types as $type) {
            // Only complex types get generated classes. All simple types get treated as PHP primitives.
            if ($type instanceof ComplexType) {
                // Construct the fully qualified class name
                $namespaceSegments = array_filter(
                    explode(
                        '\\',
                        $this->namespace . '\\' . str_replace(':', '\\', $type->getName())
                    )
                );
                $qualifiedClassName = implode('\\', $namespaceSegments);
                $unqualifiedClassName = array_pop($namespaceSegments);
                $namespace = implode('\\', $namespaceSegments);
                $parentClass = null;

                // Construct the fully qualified class name for the parent class if this type is a sub-type
                if ($type->getParentName() !== null) {
                    $parentClass = implode(
                        '\\',
                        array_filter(
                            explode(
                                '\\',
                                $this->namespace . '\\' . str_replace(':', '\\', $type->getParentName())
                            )
                        )
                    );
                }

                $class = (new ClassGenerator())
                    ->setName($unqualifiedClassName)
                    ->setNamespaceName($namespace)
                    ->setFlags($type->isAbstract() ? ClassGenerator::FLAG_ABSTRACT : 0)
                    ->setExtendedClass($parentClass)
                    ->setDocBlock(DocBlockGenerator::fromArray([
                        'shortDescription' => $unqualifiedClassName,
                        'longDescription' => $type->getDescription(),
                        'tags' => array_map(function($tag) {
                            return new GenericTag($tag->getName(), $tag->getValue());
                        }, $type->getTags())
                    ]));

                foreach ($type->getFields() as $field) {
                    $phpType = $this->determinePhpType($field, $types);

                    $commonTags = [
                        new GenericTag('ElementName', $field->getName())
                    ];

                    if ($phpType === null) {
                        throw new RuntimeException('Unable to find type ' . $field->getTypeName());
                    }

                    $propertyPhpType = $field->isArray()
                        ? $phpType . '[]'
                        : $phpType;

                    if (!$field->isArray()) {
                        $propertyPhpType .= '|null';
                    }

                    if ($field->isNillable()) {
                        $propertyPhpType .= '|' . $this->nilClassname;
                        $commonTags[] = new GenericTag('Nillable');
                    }

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
                            ->setTags(array_merge($commonTags, [
                                new GenericTag('var', $propertyPhpType)
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
                                new ReturnTag(['datatype' => $propertyPhpType])
                            ]))
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
                            ->setTags(array_merge($commonTags, [
                                new ParamTag($field->getName(), $propertyPhpType),
                                new ReturnTag(['datatype' => '$this'])
                            ]))
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
                                ->setTags(array_merge($commonTags, [
                                    new ParamTag($field->getName(), $phpType),
                                    new ReturnTag(['datatype' => '$this'])
                                ]))
                                ->setWordWrap(false));

                        $class->addMethodFromGenerator($adder);
                    }
                }

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

                $file = new FileGenerator(['classes' => [$class]]);
                if (!file_put_contents($outputPath, $file->generate())) {
                    throw new RuntimeException('Unable to write file ' . $outputPath);
                }
            }
        }
    }

    /**
     * @param Field $field
     * @param Type[] $allTypes
     * @return null|string
     */
    private function determinePhpType(Field $field, array $allTypes)
    {
        $phpType = null;

        // Check if referenced type is an XSD type. If so, use a native PHP type
        if ($this->isXsdType($field->getTypeName())) {
            $phpType = $this->xsdToPrimitiveType($field->getTypeName());
        } else {
            if (array_key_exists($field->getTypeName(), $allTypes)) {
                $type = $allTypes[$field->getTypeName()];

                if ($type instanceof SimpleType) {
                    $phpType = $this->simpleTypeToPhpType($type, $allTypes);
                } else {
                    $phpType = '\\' . implode(
                        '\\',
                        array_filter(
                            explode(
                                '\\',
                                $this->namespace . '\\' . str_replace(':', '\\', $type->getName())
                            )
                        )
                    );

                }
            }
        }

        return $phpType;
    }

    /**
     * @param SimpleType $type
     * @param Type[] $allTypes
     * @return string|null
     */
    private function simpleTypeToPhpType(SimpleType $type, $allTypes)
    {
        $phpType = null;

        $restriction = $type->getRestriction();
        // Check if referenced type is an XSD type. If so, use a primitive type
        if ($this->isXsdType($restriction)) {
            $phpType = $this->xsdToPrimitiveType($restriction);
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

    /**
     * @param string $typeName
     * @return bool
     */
    private function isXsdType($typeName)
    {
        return strpos($typeName, 'http://www.w3.org/2001/XMLSchema') === 0;
    }

    /**
     * @param $xsdType
     * @return string
     */
    private function xsdToPrimitiveType($xsdType)
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
}