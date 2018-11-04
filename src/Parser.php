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

    /**
     * @param string $rootFile
     * @param bool $debug
     */
    public function __construct($rootFile, $debug = false)
    {
        $this->rootFile = $rootFile;
        $this->debug = $debug;
    }

    /**
     * @return Type[]
     * @throws \InvalidArgumentException
     */
    public function parse()
    {
        $this->parseFile($this->rootFile);

        return $this->types;
    }

    /**
     * @param string $filePath
     * @throws \InvalidArgumentException
     */
    private function parseFile($filePath)
    {
        $fileRealPath = realpath($filePath);

        if (($fileRealPath === false) || !is_file($fileRealPath)) {
            echo sprintf('File %s does not exist.', $filePath) . PHP_EOL;
            exit(-1);
        }

        if (!is_readable($fileRealPath)) {
            echo sprintf('File %s is not readable.', $filePath) . PHP_EOL;
            exit(-1);
        }

        if (!in_array($fileRealPath, $this->readFiles, true)) {
            if ($this->debug) {
                echo sprintf('Reading %s', $filePath) . PHP_EOL;
            }

            $this->readFiles [] = $fileRealPath;

            /** @var DOMDocument $document */
            $document = new DOMDocument();
            @$document->load($fileRealPath);

            $schemaElements = $document->getElementsByTagName('schema');
            if ($schemaElements->length === 1) {
                $schemaElement = $schemaElements->item(0);
                $childElements = $schemaElement->childNodes;

                for ($i = 0; $i < $childElements->length; $i++) {
                    $childElement = $childElements->item($i);
                    switch ($childElement->localName) {
                        case 'import':
                        case 'include':
                            $pathToImportFile = dirname($fileRealPath) . DIRECTORY_SEPARATOR . $childElement->getAttribute('schemaLocation');
                            $this->parseFile($pathToImportFile);
                            break;
                        case 'complexType':
                            $this->handleComplexType($childElement, $schemaElement, $fileRealPath, null);

                            break;
                        case 'simpleType':
                            $this->handleSimpleType($childElement, $schemaElement, $fileRealPath);
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
     * @param $filePath
     * @param string|null $forceName
     * @throws \InvalidArgumentException
     */
    private function handleComplexType(DOMElement $element, DOMElement $schemaElement, $filePath, $forceName = null)
    {
        if ($element->localName !== 'complexType') {
            throw new \InvalidArgumentException('Element is not a complexType');
        }

        // Some complex types are defined within another complex type. These types aren't given a name via a name attribute
        // on the complex type's definition. Instead, the name of the element is appended to the name of the parent type.
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

        $description = '';
        $tags = [];
        $fields = [];
        $parentType = null;
        $responseTypes = [];

        for ($i = 0; $i < $element->childNodes->length; $i++) {
            $child = $element->childNodes->item($i);

            switch ($child->localName) {
                case 'annotation':
                    $documentationElements = $child->getElementsByTagName('documentation');
                    if ($documentationElements->length > 0) {
                        $description = $documentationElements->item(0)->nodeValue;

                        // Create @see tags for all Request and Response classes found in the documentation
                        if (preg_match_all('/[a-zA-Z0-9]+(Response|Request)([0-9smpv]+)?/i', $description, $docTypeMatches)) {
                            if (count($docTypeMatches[0]) > 0) {
                                foreach ($docTypeMatches[0] as $docTypeMatch) {
                                    $tags[] = new Tag('see', $docTypeMatch);
                                }
                            }
                        }

                        // Find any response objects listed in the documentation
                        if (preg_match('/The response is.*/', $description, $responseMatches)) {
                            if (preg_match_all('/[a-zA-Z0-9]+Response([0-9smpv]+)?/i', $responseMatches[0], $responseMatches)) {
                                $responseTypes = array_map(function($responseMatch) {
                                    if ($responseMatch === 'SuccessResponse') {
                                        $responseMatch = ':C:' . $responseMatch;
                                    }

                                    return $responseMatch;
                                }, $responseMatches[0]);

                                // Remove ErrorResponse from the return types
                                $responseTypes = array_filter($responseTypes, function($type) {
                                    return $type !== 'ErrorResponse';
                                });
                            }
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
                            $fields = array_merge($fields, $this->findFields($grandchild, $schemaElement, $filePath, $name));
                        }
                    }
                    break;
                default:
                    if ($child instanceof DOMElement) {
                        $fields = array_merge($fields, $this->findFields($child, $schemaElement, $filePath, $name));
                    }
            }
        }

        $this->addType((new ComplexType())
            ->setFilePath($filePath)
            ->setName($name)
            ->setAbstract($abstract)
            ->setParentName($parentType)
            ->setDescription(trim($description))
            ->setTags($tags)
            ->setResponseTypes($responseTypes)
            ->setFields($fields));
    }

    /**
     * @param DOMElement $parent
     * @param DOMElement $schemaElement
     * @param string $filePath
     * @param string $ownerName
     * @return Field[]
     * @throws \InvalidArgumentException
     */
    private function findFields(DOMElement $parent, DOMElement $schemaElement, $filePath, $ownerName)
    {
        $fields = [];

        for ($i = 0; $i < $parent->childNodes->length; $i++) {
            $child = $parent->childNodes->item($i);

            if ($child instanceof DOMElement) {
                if ($child->localName === 'element') {
                    $field = $this->handleField($child, $schemaElement, $filePath, $ownerName);
                    $fields[$field->getName()] = $field;
                } else {
                    $fields = array_merge($fields, $this->findFields($child, $schemaElement, $filePath, $ownerName));
                }
            }
        }

        return $fields;
    }

    /**
     * @param DOMElement $element
     * @param DOMElement $schemaElement
     * @param string $filePath
     * @param string $ownerName
     * @return Field
     * @throws \InvalidArgumentException
     */
    private function handleField(DOMElement $element, DOMElement $schemaElement, $filePath, $ownerName)
    {
        $fieldName = $element->getAttribute('name');
        $maxOccurs = $element->hasAttribute('maxOccurs');
        $isArray = $maxOccurs === 'unbounded' || (int)$maxOccurs > 0;
        $isNillable = $element->hasAttribute('nillable') && $element->getAttribute('nillable') === 'true';
        $description = null;

        // Field can specify a type via the "type" attribute
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
                    $typeName = $this->handleComplexField($element, $schemaElement, $filePath, $fieldName, $ownerName);
                } else {
                    // No explicit type was put on the element. Default to a string
                    $typeName = 'http://www.w3.org/2001/XMLSchema:string';
                }
            }
        }

        if ($typeName === null || $typeName === '') {
            throw new RuntimeException('Could not determine type name for field');
        }

        // Find if there's an annotation field containing a description
        for ($i = 0; $i < $element->childNodes->length; $i++) {
            $child = $element->childNodes->item($i);
            if ($child instanceof DOMElement && $child->localName === 'annotation') {
                $documentationElements = $child->getElementsByTagName('documentation');
                if ($documentationElements->length > 0) {
                    $description = $documentationElements->item(0)->nodeValue;
                }
            }
        }

        return (new Field())
            ->setName($fieldName)
            ->setTypeName($typeName)
            ->setDescription($description)
            ->setIsArray($isArray)
            ->setIsNillable($isNillable);
    }

    /**
     * @param DOMElement $element
     * @param DOMElement $schemaElement
     * @return string
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
     * @param string $filePath
     * @param string $fieldName
     * @param string $ownerName
     * @return string
     * @throws \InvalidArgumentException
     */
    private function handleComplexField(DOMElement $element, DOMElement $schemaElement, $filePath, $fieldName, $ownerName)
    {
        // Create a new simple type to represent this field
        $typeName = $ownerName . ':' . ucwords($fieldName);

        $complexTypeElements = $element->getElementsByTagName('complexType');
        $complexTypeElement = $complexTypeElements->item(0);
        $this->handleComplexType($complexTypeElement, $schemaElement, $filePath, $typeName);

        return $this->toLongName($typeName, $schemaElement);
    }

    /**
     * @param DOMElement $element
     * @param DOMElement $schemaElement
     * @param string $filePath
     * @throws \InvalidArgumentException
     */
    private function handleSimpleType(DOMElement $element, DOMElement $schemaElement, $filePath)
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
        $description = null;
        $documentationElements = $element->getElementsByTagName('documentation');

        if ($documentationElements->length > 0) {
            $description = $documentationElements->item(0)->nodeValue;
        }

        $this->addType((new SimpleType())
            ->setFilePath($filePath)
            ->setName($name)
            ->setRestriction($restriction)
            ->setDescription(trim($description)));
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