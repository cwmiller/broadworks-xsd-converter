<?php

namespace CWM\BroadWorksXsdConverter\CS;

use CWM\BroadWorksXsdConverter\ComplexType;
use CWM\BroadWorksXsdConverter\Field;
use CWM\BroadWorksXsdConverter\SimpleType;
use CWM\BroadWorksXsdConverter\Type;
use RuntimeException;

class ModelWriter
{
    /** @var string */
    private $outDir;

    /** @var string */
    private $namespace;

    /** @var bool */
    private $debug;

    public function __construct($outDir, $namespace, $debug = false)
    {
        $this->outDir = $outDir;
        $this->namespace = $namespace;
        $this->debug = $debug;
    }

    /**
     * @param Type[] $types
     */
    public function write(array $types)
    {
        foreach ($types as $type) {
            // Only complex types get generated classes. All simple types get treated as primitives.
            if ($type instanceof ComplexType) {
                $typeName = $this->complexTypeName($type);
                $qualifiedType = $this->typeNameToQualifiedType($typeName);

                $unqualifiedClassName = array_pop($qualifiedType);
                $namespace = implode('.', $qualifiedType);

                $parentUnqualifiedClassName = null;
                $parentNamespace = null;

                $modifiers = ['public'];
                if ($type->isAbstract()) {
                    $modifiers[] = 'abstract';
                }

                if ($type->getParentName() !== null) {
                    $parentType = $this->typeNameToQualifiedType($type->getParentName());
                    $parentUnqualifiedClassName = array_pop($parentType);
                    $parentNamespace = implode('.', $parentType);
                }

                $template = (new ClassTemplate())
                    ->setNamespace($namespace)
                    ->setModifiers($modifiers)
                    ->setClassName($unqualifiedClassName)
                    ->setParentClassName($parentUnqualifiedClassName);

                if ($parentNamespace !== null) {
                    $template->addUsing($parentNamespace);
                }

                foreach ($type->getFields() as $field) {
                    $qualifiedType = $this->determineCsType($field, $types);

                    if ($qualifiedType === null) {
                        throw new RuntimeException('Unable to find type ' . $field->getTypeName());
                    }

                    $typeSegments = explode('.', $qualifiedType);
                    $type = array_pop($typeSegments);
                    $typeNamespace = implode('.', $typeSegments);

                    $template->addUsing('System.Xml.Serialization');

                    if ($typeNamespace !== $namespace && $typeNamespace !== '') {
                        $template->addUsing($typeNamespace);
                    }

                    if ($field->isArray()) {
                        $type = 'ICollection<' . $type . '>';

                        $template->addUsing('System.Collections.Generic');
                    }

                    $property = (new PropertyTemplate())
                        ->setType($type)
                        ->setName(ucfirst($field->getName()))
                        ->setXmlProperty($field->getName());

                    $template->addProperty($property);
                }

                $this->writeTemplate($template);
            }
        }
    }

    /**
     * @param $typeName
     * @return array
     */
    private function typeNameToQualifiedType($typeName)
    {
        return array_filter(
            explode(
                '.',
                $this->namespace . '.' . str_replace(':', '.', $typeName)
            )
        );
    }

    /**
     * @param ClassTemplate $template
     */
    private function writeTemplate(ClassTemplate $template)
    {
        ob_start();
        require __DIR__ . '/../../templates/class.cs.php';
        $contents = ob_get_clean();

        $outputPath =
            $this->outDir
            . DIRECTORY_SEPARATOR
            . str_replace('.', DIRECTORY_SEPARATOR, $template->getNamespace())
            . DIRECTORY_SEPARATOR
            . $template->getClassName()
            . '.cs';

        $dirPath = dirname($outputPath);
        if (!is_dir($dirPath) && !mkdir($dirPath, null, true)) {
            throw new RuntimeException('Unable to create directory ' . $dirPath);
        }

        if (!file_put_contents($outputPath, $contents)) {
            throw new RuntimeException('Unable to write file ' . $outputPath);
        }
    }

    /**
     * @param Field $field
     * @param Type[] $allTypes
     * @return null|string
     */
    private function determineCsType(Field $field, array $allTypes)
    {
        $csType = null;

        // Check if referenced type is an XSD type. If so, use a native PHP type
        if ($this->isXsdType($field->getTypeName())) {
            $csType = $this->xsdToPrimitiveType($field->getTypeName());
        } else {
            if (array_key_exists($field->getTypeName(), $allTypes)) {
                $type = $allTypes[$field->getTypeName()];

                if ($type instanceof SimpleType) {
                    $csType = $this->simpleTypeToCsType($type, $allTypes);
                } else {
                    $typeName = $this->complexTypeName($type);

                    $csType = implode(
                            '.',
                            array_filter(
                                explode(
                                    '.',
                                    $this->namespace . '.' . str_replace(':', '.', $typeName)
                                )
                            )
                        );
                }
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

        $restriction = $type->getRestriction();
        // Check if referenced type is an XSD type. If so, use a primitive type
        if ($this->isXsdType($restriction)) {
            $csType = $this->xsdToPrimitiveType($restriction);
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
     * @param ComplexType $type
     * @return string
     */
    private function complexTypeName(ComplexType $type)
    {
        $typeName = $type->getName();

        // With C# we cannot have a namespace match a class name.
        // For anonymous types (complex fields defined inside another complex type), we would create a new namespace
        // named after the owner of the field. Since we cannot do this, we just append the field name onto the owner's name
        // and put it in the same namespace.
        if ($type->getOwnerName() !== null) {
            $typeName = $type->getOwnerName() . $type->getUnqualifiedName();
        }

        return $typeName;
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
                return 'decimal';
            case 'int':
                return 'int';
            case 'boolean':
                return 'bool';
            default:
                return 'string';
        }
    }
}