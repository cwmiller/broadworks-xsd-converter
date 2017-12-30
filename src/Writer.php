<?php

namespace CWM\BroadWorksXsdConverter;

use RuntimeException;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
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
            if ($type instanceof ComplexType) {
                $fullClassName = trim($this->namespace, '\\') . '\\' . trim(str_replace(':', '\\', $type->getName()), '\\');
                $fullChunks = explode('\\', $fullClassName);
                $className = array_pop($fullChunks);
                $namespace = trim(implode('\\', $fullChunks), '\\');
                $flags = 0;
                $parentClass = null;

                if ($type->isAbstract()) {
                    $flags |= ClassGenerator::FLAG_ABSTRACT;
                }

                if ($type->getParentName() !== null) {
                    $parentClass = '\\' . trim($this->namespace, '\\') . '\\' . trim(str_replace(':', '\\', $type->getParentName()), '\\');
                }

                $class = new ClassGenerator(
                    $className,
                    $namespace,
                    $flags,
                    $parentClass,
                    [],
                    []
                );

                $class->setDocBlock(DocBlockGenerator::fromArray([
                    'longDescription' => $type->getDescription(),
                ]));

                foreach ($type->getFields() as $field) {
                    $phpType = $this->determinePhpType($field, $types);
                    if ($phpType === null) {
                        throw new RuntimeException('Unable to find type ' . $field->getTypeName());
                    }

                    // Create property
                    $property = new PropertyGenerator($field->getName(), null, PropertyGenerator::FLAG_PRIVATE);
                    $property->setDocBlock(DocBlockGenerator::fromArray([
                        'tags' => [
                            ['name' => 'var', 'description' => $phpType]
                        ]
                    ]));

                    $class->addPropertyFromGenerator($property);

                    // Create getter
                    $getter = new MethodGenerator(
                         'get' . ucwords($field->getName()),
                        [],
                        null,
                         sprintf('return $this->%s;', $field->getName()),
                         new DocBlockGenerator(
                            null,
                            null,
                            [
                                new ReturnTag([
                                    'datatype' => $phpType . '|null',
                                ])
                            ]
                         )
                    );

                    $class->addMethodFromGenerator($getter);

                    // Create setter
                    $setter = new MethodGenerator(
                        'set' . ucwords($field->getName()),
                        [
                            ['name' => $field->getName()],
                        ],
                        null,
                        sprintf("\$this->%s = $%s;\nreturn \$this;", $field->getName(), $field->getName()),
                        new DocBlockGenerator(
                            null,
                            null,
                            [
                                new ParamTag(
                                    $field->getName(),
                                    [$phpType, 'null']
                                ),
                                new ReturnTag([
                                    'datatype' => '$this',
                                ]),
                            ]
                        )
                    );

                    $class->addMethodFromGenerator($setter);
                }

                $file = new FileGenerator([
                    'classes' => [$class]
                ]);

                $outputPath = $this->outDir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $fullClassName) . '.php';
                $dirPath = dirname($outputPath);

                @mkdir($dirPath, null, true);
                file_put_contents($outputPath, $file->generate());
            }
        }
    }

    /**
     * @param Field $field
     * @param Type[] $allTypes
     * @return null|string
     */
    private function determinePhpType(Field $field, $allTypes)
    {
        $phpType = null;

        // Check if referenced type is an XSD type. If so, use a native PHP type
        if ($this->isXsdType($field->getTypeName())) {
            $phpType = $this->xsdToPhpType($field->getTypeName());
        } else {
            if (array_key_exists($field->getTypeName(), $allTypes)) {
                $type = $allTypes[$field->getTypeName()];

                if ($type instanceof SimpleType) {
                    $phpType = $this->simpleTypeToPhpType($type, $allTypes);
                } else {
                    $phpType = '\\' . trim($this->namespace, '\\') . '\\' . trim(str_replace(':', '\\', $type->getName()), '\\');
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
        $restriction = $type->getRestriction();
        // Check if referenced type is an XSD type. If so, use a native PHP type
        if ($this->isXsdType($restriction)) {
            return $this->xsdToPhpType($restriction);
        } else {
            // Restriction can also be a simple type
            if (array_key_exists($restriction, $allTypes)) {
                $restrictionType = $allTypes[$restriction];
                if ($restrictionType instanceof SimpleType) {
                    return $this->simpleTypeToPhpType($restrictionType, $allTypes);
                }
            }
        }

        return null;
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
    private function xsdToPhpType($xsdType)
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