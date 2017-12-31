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

class Writer
{
    /** @var string */
    private $outDir;

    /** @var string */
    private $namespace;

    public function __construct($outDir, $namespace)
    {
        $this->outDir = $outDir;
        $this->namespace = $namespace;
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
                    ]));

                foreach ($type->getFields() as $field) {
                    $phpType = $this->determinePhpType($field, $types);

                    if ($phpType === null) {
                        throw new RuntimeException('Unable to find type ' . $field->getTypeName());
                    }

                    // Create private property for field
                    $property = (new PropertyGenerator())
                        ->setName($field->getName())
                        ->setFlags(PropertyGenerator::FLAG_PRIVATE)
                        ->setDocBlock((new DocBlockGenerator())
                            ->setTags([new GenericTag('var', $phpType . '|null')])
                            ->setWordWrap(false));

                    $class->addPropertyFromGenerator($property);

                    // Create getter for field
                    $getter = (new MethodGenerator())
                        ->setBody(sprintf('return $this->%s;', $field->getName()))
                        ->setName(sprintf('get%s', ucwords($field->getName())))
                        ->setDocBlock((new DocBlockGenerator())
                            ->setShortDescription('Getter for ' . $field->getName())
                            ->setTags([new ReturnTag(['datatype' => $phpType . '|null'])])
                            ->setWordWrap(false));

                    $class->addMethodFromGenerator($getter);

                    // Create getter for field
                    $setter = (new MethodGenerator())
                        ->setName(sprintf('set%s', ucwords($field->getName())))
                        ->setBody(sprintf("\$this->%s = $%s;\nreturn \$this;", $field->getName(), $field->getName()))
                        ->setParameter(['name' => $field->getName()])
                        ->setDocBlock((new DocBlockGenerator())
                            ->setShortDescription('Setter for ' . $field->getName())
                            ->setTags([
                                new ParamTag($field->getName(), [$phpType, 'null']),
                                new ReturnTag(['datatype' => '$this'])
                            ])
                            ->setWordWrap(false));

                    $class->addMethodFromGenerator($setter);
                }

                // Convert fully qualified class name into PSR-4 directory structure
                $outputPath =
                    $this->outDir
                    . DIRECTORY_SEPARATOR
                    . str_replace('\\', DIRECTORY_SEPARATOR, $qualifiedClassName)
                    . '.php';

                // Ensure the destination directory exists
                $dirPath = dirname($outputPath);
                if (!is_dir($dirPath) && !mkdir($dirPath, null, true)) {
                    echo 'Unable to create directory ' . $dirPath . PHP_EOL;
                    exit(-1);
                }

                $file = new FileGenerator(['classes' => [$class]]);
                if (!file_put_contents($outputPath, $file->generate())) {
                    echo 'Unable to write file ' . $outputPath . PHP_EOL;
                    exit(-1);
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

        if ($field !== null && $field->isArray()) {
            $phpType .= '[]';
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