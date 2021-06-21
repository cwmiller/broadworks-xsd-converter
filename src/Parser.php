<?php

namespace CWM\BroadWorksXsdConverter;

use CWM\BroadWorksXsdConverter\Schema\Choice;
use CWM\BroadWorksXsdConverter\Schema\Sequence;
use DOMDocument;
use DOMElement;
use RuntimeException;

class Parser
{
    /** @var bool */
    private $debug ;

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
                            $this->handleComplexType($childElement, $schemaElement, $fileRealPath);
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
            if ($type instanceof EnumType) {
                echo sprintf('Found Enum Type: %s', $type->getName()) . ' (' . $type->getRestriction()->getBase() . ') (' . implode(', ', $type->getOptions()) . ')' . PHP_EOL;
            } else if ($type instanceof SimpleType) {
                echo sprintf('Found Simple Type: %s', $type->getName()) . ' (' . $type->getRestriction()->getBase() . ')' . PHP_EOL;
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
     * @param string|null $ownerName
     * @return ComplexType
     * @throws \InvalidArgumentException
     */
    private function handleComplexType(DOMElement $element, DOMElement $schemaElement, $filePath, $forceName = null, $ownerName = null, $ownerNamespace = null)
    {
        if ($element->localName !== 'complexType') {
            throw new \InvalidArgumentException('Element is not a complexType');
        }

        // Some complex types are defined within another complex type. These types aren't given a name via a name attribute
        // on the complex type's definition. Instead, the name of the element is appended to the name of the parent type.
        if ($forceName !== null) {
            $name = $forceName;
            $namespace = $ownerNamespace;
        } else {
            // Get name of type
            $name = $element->getAttribute('name');
            $namespace = $element->getAttribute('xmlns');
            $name = $this->toQualifiedName($name, $namespace, $schemaElement);
        }

        if ($name === null || $name === '') {
            throw new RuntimeException('Type doesn\'t have a name');
        }

        $complexType = (new ComplexType())
            ->setFilePath($filePath)
            ->setName($name)
            ->setOwnerName($ownerName)
            ->setAbstract($element->getAttribute('abstract') === 'true');

        // Retrieve the documentation tag to get the type's description
        $annotationElements = $element->getElementsByTagName('annotation');
        if ($annotationElements->length > 0) {
            $documentationElements = $annotationElements->item(0)->getElementsByTagName('documentation');
            if ($documentationElements->length > 0) {
                $description = trim($documentationElements->item(0)->nodeValue);

                $complexType->setDescription($description);

                // Create @see tags for all Request and Response classes found in the documentation
                if (preg_match_all('/[a-zA-Z0-9]+(Response|Request)([0-9smpv]+)?/i', $description, $docTypeMatches)) {
                    if (count($docTypeMatches[0]) > 0) {
                        $references = [];
                        foreach ($docTypeMatches[0] as $docTypeMatch) {
                            $references[] = $docTypeMatch;
                        }

                        $complexType->setReferences($references);
                    }
                }

                // Find any response objects listed in the documentation
                if (preg_match('/(The response is|Returns a).*/', $description, $responseMatches)) {
                    if (preg_match_all('/[a-zA-Z0-9]+Response([0-9smpv]+)?/i', $responseMatches[0], $responseMatches)) {
                        $responseTypes = array_map(function($responseMatch) {
                            $responseMatch = trim($responseMatch);
                            if ($responseMatch === 'SuccessResponse') {
                                $responseMatch = ':C:' . $responseMatch;
                            }

                            return trim($responseMatch);
                        }, $responseMatches[0]);

                        // Remove ErrorResponse from the return types
                        $responseTypes = array_filter($responseTypes, function($type) {
                            $type = (string)$type;

                            return $type !== '' &&  $type !== 'ErrorResponse';
                        });

                        $complexType->setResponseTypes($responseTypes);
                    }
                }
            }
        }

        // Retrieve the complexContent element (if it exists) to get the base type
        $contentElements = $element->getElementsByTagName('complexContent');
        if ($contentElements->length > 0) {
            $extensionElements = $contentElements->item(0)->getElementsByTagName('extension');
            if ($extensionElements->length > 0) {
                $base = $extensionElements->item(0)->getAttribute('base');
                if ($base !== null) {
                    $complexType->setParentName($this->toQualifiedName($base, $namespace, $schemaElement));
                }
            }
        }

        // Get all fields that are part of this type
        $complexType->setFields($this->findFields($element, $schemaElement, $filePath, $name, $namespace));

        // Get the schema layout of groups
        $complexType->setGroups($this->findGroups($element, $filePath));

        $this->addType($complexType);

        return $complexType;
    }

    /**
     * @param DOMElement $parent
     * @param DOMElement $schemaElement
     * @param string $filePath
     * @param string $ownerName
     * @return Field[]
     * @throws \InvalidArgumentException
     */
    private function findFields(DOMElement $parent, DOMElement $schemaElement, $filePath, $ownerName, $ownerNamespace)
    {
        $fields = [];

        for ($i = 0; $i < $parent->childNodes->length; $i++) {
            $child = $parent->childNodes->item($i);

            if ($child instanceof DOMElement) {
                if ($child->localName === 'element') {
                    $field = $this->handleField($child, $schemaElement, $filePath, $ownerName, $ownerNamespace);
                    $fields[$field->getName()] = $field;
                } else {
                    $fields = array_merge($fields, $this->findFields($child, $schemaElement, $filePath, $ownerName, $ownerNamespace));
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
    private function handleField(DOMElement $element, DOMElement $schemaElement, $filePath, $ownerName, $ownerNamespace)
    {
        $fieldName = $element->getAttribute('name');
        $maxOccurs = $element->hasAttribute('maxOccurs');
        $isArray = $maxOccurs === 'unbounded' || (int)$maxOccurs > 0;
        $isNillable = $element->getAttribute('nillable') === 'true';
        $isOptional = $element->getAttribute('minOccurs') === '0';
        $groupId = $this->getElementId($element->parentNode, $filePath);
        $description = null;
        $sequence = null;
        $choice = null;

        // Field can specify a type via the "type" attribute
        $typeName = trim($element->getAttribute('type'));

        if ($typeName !== '') {
            $typeName = $this->handleTypedField($element, $ownerNamespace, $schemaElement);
        } else {
            // If not typed, it can be a simple type if a "simpleType" element exists
            $simpleTypeElements = $element->getElementsByTagName('simpleType');
            if ($simpleTypeElements->length > 0) {
                $typeName = $this->handleSimpleField($element, $schemaElement, $fieldName, $ownerName, $ownerNamespace);
            } else {
                // If not a simple type, it can be a complex type if a "complexType" element exists
                $complexTypeElements = $element->getElementsByTagName('complexType');
                if ($complexTypeElements->length > 0) {
                    $typeName = $this->handleComplexField($element, $schemaElement, $filePath, $fieldName, $ownerName, $ownerNamespace);
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
            ->setIsNillable($isNillable)
            ->setIsOptional($isOptional)
            ->setGroupId($groupId);
    }

    /**
     * @param DOMElement $element
     * @param DOMElement $schemaElement
     * @return string
     */
    private function handleTypedField(DOMElement $element, $ownerNamespace, DOMElement $schemaElement)
    {
        $typeName = trim($element->getAttribute('type'));

        if ($typeName === null || $typeName === '') {
            throw new RuntimeException('Expected type attribute not found');
        }

        return $this->toQualifiedName($typeName, $ownerNamespace, $schemaElement);
    }

    /**
     * @param DOMElement $element
     * @param DOMElement $schemaElement
     * @param string $fieldName
     * @param string $ownerName
     * @return string
     * @throws \InvalidArgumentException
     */
    private function handleSimpleField(DOMElement $element, DOMElement $schemaElement, $fieldName, $ownerName, $ownerNamespace)
    {
        /*
        $restrictionBase = null;
        $simpleTypeElements = $element->getElementsByTagName('simpleType');

        if ($simpleTypeElements->length > 0) {
            $restrictionElements = $element->getElementsByTagName('restriction');
            if ($restrictionElements->length > 0) {
                $restrictionElement = $restrictionElements->item(0);
                if ($restrictionElement !== null) {
                    $restrictionBase = $restrictionElement->getAttribute('base');

                    // Find value restrictions
                    for ($i = 0; $i < $restrictionElement->childNodes->length; $i++) {
                        $childNode = $restrictionElement->childNodes->item($i);
                        $nodeName = trim($childNode->localName);
                        if ($nodeName !== '') {
                            echo 'field: ' . $nodeName . PHP_EOL;

                        }
                    }
                }
            }
        }

        if ($restrictionBase === null || $restrictionBase === '') {
            throw new RuntimeException('Expected base attribute not found');
        }
        */

        $restriction = $this->findRestriction($element, $schemaElement, $ownerNamespace);

        if ($restriction === null) {
            throw new \RuntimeException('No restriction found for element.');
        }

        // Create a new simple type to represent this field
        $typeName = $ownerName . ucwords($fieldName);

        $this->addType((new SimpleType())
            ->setName($typeName)
            ->setRestriction($restriction));

        return $this->toQualifiedName($typeName, $ownerNamespace, $schemaElement);
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
    private function handleComplexField(DOMElement $element, DOMElement $schemaElement, $filePath, $fieldName, $ownerName, $ownerNamespace)
    {
        // Create a new simple type to represent this field
        $typeName = $ownerName . ucwords($fieldName);

        $complexTypeElements = $element->getElementsByTagName('complexType');
        $complexTypeElement = $complexTypeElements->item(0);
        $this->handleComplexType($complexTypeElement, $schemaElement, $filePath, $typeName, $ownerName, $ownerNamespace);

        return $this->toQualifiedName($typeName, $ownerNamespace, $schemaElement);
    }

    /**
     * @param DOMElement $element
     * @param DOMElement $schemaElement
     * @param string $filePath
     * @return EnumType|SimpleType
     * @throws \InvalidArgumentException
     */
    private function handleSimpleType(DOMElement $element, DOMElement $schemaElement, $filePath)
    {
        if ($element->localName !== 'simpleType') {
            throw new \InvalidArgumentException('Element is not a simpleType');
        }

        // Get name of type
        $name = $element->getAttribute('name');
        $namespace = $element->getAttribute('xmlns');

        if ($name === null || $name === '') {
            throw new RuntimeException('Simple type doesn\'t have a name!');
        }

        $name = $this->toQualifiedName($name, $namespace, $schemaElement);

        // Get description of type
        $description = null;
        $documentationElements = $element->getElementsByTagName('documentation');

        if ($documentationElements->length > 0) {
            $description = $documentationElements->item(0)->nodeValue;
        }

        // Get restriction of type
        $restriction = $this->findRestriction($element, $schemaElement, $namespace);

        if ($restriction === null) {
            throw new \RuntimeException('No restriction found for element.');
        }

        if (count($restriction->getEnumerations()) > 0) {
            $type = (new EnumType())
                ->setOptions($restriction->getEnumerations());
        } else {
            $type = new SimpleType();
        }

        $type
            ->setFilePath($filePath)
            ->setName($name)
            ->setRestriction($restriction)
            ->setDescription(trim($description));

        $this->addType($type);

        return $type;
    }

    /**
     * @param DOMElement $element
     * @param DOMElement $schemaElement
     * @param string $namespace
     * @return Restriction|null
     */
    private function findRestriction(DOMElement $element, DOMElement $schemaElement, $namespace)
    {
        $restriction = null;


        $restrictionElements = $element->getElementsByTagName('restriction');

        if ($restrictionElements->length > 0) {
            $restrictionElement = $restrictionElements->item(0);
            if ($restrictionElement !== null) {
                $restrictionBase = $restrictionElement->getAttribute('base');

                if ($restrictionBase === null || $restrictionBase === '') {
                    throw new RuntimeException('No restriction base found for element.');
                }

                $restriction = (new Restriction())
                    ->setBase($this->toQualifiedName($restrictionBase, $namespace, $schemaElement));

                for ($i = 0; $i < $restrictionElement->childNodes->length; $i++) {
                    $childNode = $restrictionElement->childNodes->item($i);
                    if ($childNode !== null) {
                        $nodeName = trim($childNode->localName);
                        if ($nodeName !== '') {
                            $nodeValue = $childNode->attributes->getNamedItem('value')->nodeValue;

                            switch ($nodeName) {
                                case 'enumeration':
                                    $restriction->addEnumeration($nodeValue);
                                    break;
                                case 'minLength':
                                    $restriction->setMinLength($nodeValue);
                                    break;
                                case 'maxLength':
                                    $restriction->setMaxLength($nodeValue);
                                    break;
                                case 'length':
                                    $restriction->setLength($nodeValue);
                                    break;
                                case 'minInclusive':
                                    $restriction->setMinInclusive($nodeValue);
                                    break;
                                case 'maxInclusive':
                                    $restriction->setMaxInclusive($nodeValue);
                                    break;
                                case 'pattern':
                                    $restriction->setPattern($nodeValue);
                                    break;
                                case 'whiteSpace':
                                    $restriction->setWhiteSpace($nodeValue);
                                    break;
                                default:
                                    echo 'Unknown restriction: ' . $nodeName . PHP_EOL;
                            }

                        }
                    }
                }
            }
        }

        return $restriction;
    }

    /**
     * @param DOMElement $element
     * @param string $filePath
     * @return Sequence|Choice[]
     */
    private function findGroups(DOMElement $element, $filePath)
    {
        $groups = [];

        for ($i = 0; $i < $element->childNodes->length; $i++) {
            $childElement = $element->childNodes->item($i);

            if ($childElement instanceof DOMElement) {
                switch ($childElement->localName) {
                    case 'sequence':
                        $groups[] = new Sequence(
                            $this->getElementId($childElement, $filePath),
                            $this->findGroups($childElement, $filePath)
                        );
                        break;
                    case 'choice':
                        $groups[] = new Choice(
                            $this->getElementId($childElement, $filePath),
                            $this->findGroups($childElement, $filePath),
                            $childElement->getAttribute('minOccurs') === '0'
                        );
                        break;
                    case 'complexType':
                        // Do not follow into complex sub-types
                        break;
                    default:
                        $groups = array_merge($groups, $this->findGroups($childElement, $filePath));
                }
            }
        }

        return $groups;
    }

    /**
     * @param string $name
     * @param string $namespace
     * @param DOMElement $schemaElement
     * @return string
     */
    private function toQualifiedName($name, $namespace, DOMElement $schemaElement)
    {
        // If namespace is blank, try to get it from the top element
        if ((string)$namespace === '') {
            $namespace = (string)$schemaElement->getAttribute('xmlns');
        }

        // Check if an alias is defined for the parent type
        if (strpos($name, ':') !== false) {
            list($alias, $name) = explode(':', $name, 2);
            $namespace = (string)$schemaElement->getAttribute('xmlns:' . $alias);
        }

        return $namespace . ':' . $name;
    }

    /**
     * @param DOMElement $element
     * @param string $filePath
     * @return string
     */
    private function getElementId(DOMElement $element, $filePath)
    {
        return sprintf('%s:%d', md5($filePath), $element->getLineNo());
    }
}