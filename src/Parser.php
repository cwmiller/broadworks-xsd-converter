<?php

namespace CWM\BroadWorksXsdConverter;

use DOMDocument;
use DOMElement;
use RuntimeException;

class Parser
{
    /** @var bool */
    private $debug;

    /** @var string */
    private $rootFile;

    /** @var string[] */
    private $readFiles = [];

    /** @var Type[] */
    private $types = [];

    public function __construct($rootFile, $debug = false)
    {
        $this->rootFile = $rootFile;
        $this->debug = $debug;
    }

    /**
     * @return Type[]
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function parse()
    {
        $this->parseFile($this->rootFile);

        return $this->types;
    }

    /**
     * @param string $filePath
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function parseFile($filePath)
    {
        $filePath = realpath($filePath);

        if (!in_array($filePath, $this->readFiles, true) && is_file($filePath) && is_readable($filePath)) {
            if ($this->debug) {
                echo sprintf('Reading %s', $filePath) . PHP_EOL;
            }

            $this->readFiles [] = $filePath;

            /** @var DOMDocument $document */
            $document = new DOMDocument();
            @$document->load($filePath);

            $schemaElements = $document->getElementsByTagName('schema');
            if ($schemaElements->length === 1) {
                $schemaElement = $schemaElements->item(0);
                $childElements = $schemaElement->childNodes;

                for ($i = 0; $i < $childElements->length; $i++) {
                    $childElement = $childElements->item($i);
                    switch ($childElement->localName) {
                        case 'import':
                        case 'include':
                            $pathToImportFile = dirname($filePath) . DIRECTORY_SEPARATOR . $childElement->getAttribute('schemaLocation');
                            $this->parseFile($pathToImportFile);
                            break;
                        case 'complexType':
                            $this->handleComplexType($childElement, $schemaElement);

                            break;
                        case 'simpleType':
                            $this->handleSimpleType($childElement, $schemaElement);
                            break;
                    }
                }
            }
        }
    }

    /**
     * @param Type $type
     * @throws \InvalidArgumentException
     */
    private function addType(Type $type)
    {
        if (array_key_exists($type->getName(), $this->types)) {
            throw new \InvalidArgumentException('Type "' . $type->getName() . '" already exists');
        }

        $this->types[$type->getName()] = $type;

        if ($this->debug) {
            if ($type instanceof SimpleType) {
                echo sprintf('Found Simple Type: %s', $type->getName()) . ' (' . $type->getRestriction() . ')' . PHP_EOL;
            } else if ($type instanceof ComplexType) {
                echo sprintf('Found Complex Type: %s', $type->getName()) . PHP_EOL;

                foreach ($type->getFields() as $field) {
                    echo sprintf(' - %s (%s)', $field->getName(), $field->getTypeName()) . PHP_EOL;
                }
            }
        }
    }

    /**
     * @param DOMElement $element
     * @param DOMElement $schemaElement
     * @param string $forceName
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function handleComplexType(DOMElement $element, DOMElement $schemaElement, $forceName = null)
    {
        if ($element->localName !== 'complexType') {
            throw new \InvalidArgumentException('Element is not a complexType');
        }

        if ($forceName !== null) {
            $name = $forceName;
        } else {
            // Get name of type
            $name = $element->getAttribute('name');
            $name = $this->toLongName($name, $schemaElement);
        }

        if ($name === null || $name === '') {
            throw new RuntimeException('Type doesn\'t have a name');
        }

        // Check if type is abstract
        $abstract = $element->getAttribute('abstract') === 'true';

        $description = [];
        $fields = [];
        $parentType = null;

        for ($i = 0; $i < $element->childNodes->length; $i++) {
            $child = $element->childNodes->item($i);

            switch ($child->localName) {
                case 'attribute':
                    $documentationElements = $child->getElementsByTagName('documentation');
                    if ($documentationElements->length > 0) {
                        for ($j = 0; $j < $documentationElements->length; $j++) {
                            $description [] = $documentationElements->item($j)->nodeValue;
                        }
                    }
                    break;
                case 'complexContent':
                    for ($j = 0; $j < $child->childNodes->length; $j++) {
                        $grandchild = $child->childNodes->item($j);
                        if ($grandchild->localName === 'extension') {
                            $parentType = $this->toLongName($grandchild->getAttribute('base'), $schemaElement);
                        }

                        if ($grandchild instanceof DOMElement) {
                            $fields = array_merge($fields, $this->findFields($grandchild, $schemaElement, $name));
                        }
                    }
                    break;
                default:
                    if ($child instanceof DOMElement) {
                        $fields = array_merge($fields, $this->findFields($child, $schemaElement, $name));
                    }

            }
        }

        $this->addType((new ComplexType())
            ->setName($name)
            ->setAbstract($abstract)
            ->setParentName($parentType)
            ->setDescription(trim(implode(PHP_EOL, array_filter($description))))
            ->setFields($fields));
    }

    /**
     * @param DOMElement $parent
     * @param DOMElement $schemaElement
     * @param string $ownerName
     * @return Field[]
     */
    private function findFields(DOMElement $parent, DOMElement $schemaElement, $ownerName)
    {
        $fields = [];

        for ($i = 0; $i < $parent->childNodes->length; $i++) {
            $child = $parent->childNodes->item($i);

            if ($child instanceof DOMElement) {
                if ($child->localName === 'element') {
                    $field = $this->handleField($child, $schemaElement, $ownerName);
                    $fields[$field->getName()] = $field;
                } else {
                    $fields = array_merge($fields, $this->findFields($child, $schemaElement, $ownerName));
                }
            }
        }

        return $fields;
    }

    /**
     * @param DOMElement $element
     * @param DOMElement $schemaElement
     * @param string $ownerName
     * @return Field
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    private function handleField(DOMElement $element, DOMElement $schemaElement, $ownerName)
    {
        $fieldName = $element->getAttribute('name');
        $isArray = $element->hasAttribute('maxOccurs');

        // Field can specify a type via the "type attribute"
        $typeName = $element->getAttribute('type');

        if ($typeName !== '') {
            $typeName = $this->handleTypedField($element, $schemaElement);
        } else {
            // If not typed, it can be a simple type if a "simpleType" element exists
            $simpleTypeElements = $element->getElementsByTagName('simpleType');
            if ($simpleTypeElements->length > 0) {
                $typeName = $this->handleSimpleField($element, $schemaElement, $fieldName, $ownerName);
            } else {
                // If not a simple type, it can be a complex type if a "complexType" element exists
                $complexTypeElements = $element->getElementsByTagName('complexType');
                if ($complexTypeElements->length > 0) {
                    $typeName = $this->handleComplexField($element, $schemaElement, $fieldName, $ownerName);
                } else {
                    // No explicit type was put on the element. Default to a string
                    $typeName = 'http://www.w3.org/2001/XMLSchema:string';
                }
            }
        }

        if ($typeName === null || $typeName === '') {
            throw new RuntimeException('Could not determine type name for field');
        }

        return (new Field())
            ->setName($fieldName)
            ->setTypeName($typeName)
            ->setIsArray($isArray);
    }

    /**
     * @param DOMElement $element
     * @param DOMElement $schemaElement
     * @return string
     * @throws \RuntimeException
     */
    private function handleTypedField(DOMElement $element, DOMElement $schemaElement)
    {
        $typeName = $element->getAttribute('type');

        if ($typeName === null || $typeName === '') {
            throw new RuntimeException('Expected type attribute not found');
        }

        return $this->toLongName($typeName, $schemaElement);
    }

    /**
     * @param DOMElement $element
     * @param DOMElement $schemaElement
     * @param string $fieldName
     * @param string $ownerName
     * @return string
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    private function handleSimpleField(DOMElement $element, DOMElement $schemaElement, $fieldName, $ownerName)
    {
        $restriction = null;
        $simpleTypeElements = $element->getElementsByTagName('simpleType');

        if ($simpleTypeElements->length > 0) {
            $restrictionElements = $element->getElementsByTagName('restriction');
            if ($restrictionElements->length > 0) {
                $restriction = $restrictionElements->item(0)->getAttribute('base');
            }
        }

        if ($restriction === null || $restriction === '') {
            throw new RuntimeException('Expected base attribute not found');
        }

        // Create a new simple type to represent this field
        $typeName = $ownerName . ':' . ucwords($fieldName);

        $this->addType((new SimpleType())
            ->setName($typeName)
            ->setRestriction($this->toLongName($restriction, $schemaElement)));

        return $this->toLongName($typeName, $schemaElement);
    }

    /**
     * @param DOMElement $element
     * @param DOMElement $schemaElement
     * @param string $fieldName
     * @param string $ownerName
     * @return string
     * @throws \InvalidArgumentException
     */
    private function handleComplexField(DOMElement $element, DOMElement $schemaElement, $fieldName, $ownerName)
    {
        // Create a new simple type to represent this field
        $typeName = $ownerName . ':' . ucwords($fieldName);

        $complexTypeElements = $element->getElementsByTagName('complexType');
        $complexTypeElement = $complexTypeElements->item(0);
        $this->handleComplexType($complexTypeElement, $schemaElement, $typeName);

        return $this->toLongName($typeName, $schemaElement);
    }

    /**
     * @param DOMElement $element
     * @param DOMElement $schemaElement
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function handleSimpleType(DOMElement $element, DOMElement $schemaElement)
    {
        if ($element->localName !== 'simpleType') {
            throw new \InvalidArgumentException('Element is not a simpleType');
        }

        // Get name of type
        $name = $element->getAttribute('name');

        if ($name === null || $name === '') {
            throw new RuntimeException('Simple type doesn\'t have a name!');
        }

        $name = $this->toLongName($name, $schemaElement);

        // Get type restriction
        $restriction = null;
        $restrictionElements = $element->getElementsByTagName('restriction');
        if ($restrictionElements->length > 0) {
            $restriction = $restrictionElements->item(0)->getAttribute('base');
        }

        if ($restriction === null || $restriction === '') {
            throw new RuntimeException('No restriction found for element.');
        }

        $restriction = $this->toLongName($restriction, $schemaElement);

        // Get description of type
        $description = [];
        $documentationElements = $element->getElementsByTagName('documentation');

        if ($documentationElements->length > 0) {
            for ($i = 0; $i < $documentationElements->length; $i++) {
                $description [] = $documentationElements->item($i)->nodeValue;
            }
        }

        $this->addType((new SimpleType())
            ->setName($name)
            ->setRestriction($restriction)
            ->setDescription(trim(implode(PHP_EOL, array_filter($description)))));
    }

    /**
     * @param string $name
     * @param DOMElement $schemaElement
     * @return string
     */
    private function toLongName($name, DOMElement $schemaElement)
    {
        $namespace = (string)$schemaElement->getAttribute('xmlns');

        // Check if an alias is defined for the parent type
        if (($pos = strpos($name, ':')) !== false) {
            list($alias, $name) = explode(':', $name, 2);
            $namespace = (string)$schemaElement->getAttribute('xmlns:' . $alias);
        }

        return $namespace . ':' . $name;
    }
}