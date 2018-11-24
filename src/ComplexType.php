<?php

namespace CWM\BroadWorksXsdConverter;

use CWM\BroadWorksXsdConverter\Schema\Choice;
use CWM\BroadWorksXsdConverter\Schema\Sequence;

class ComplexType extends Type
{
    /** @var Field[] */
    private $fields = [];

    /** @var bool */
    private $abstract = false;

    /** @var string|null */
    private $parentName;

    /** @var string|null */
    private $ownerName;

    /** @var string[] */
    private $responseTypes = [];

    /** @var Choice[]|Sequence[] */
    private $groups;

    /**
     * @return Field[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param Field[] $fields
     * @return ComplexType
     */
    public function setFields($fields)
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * @param Field $field
     * @return ComplexType
     */
    public function addField(Field $field)
    {
        $this->fields[$field->getName()] = $field;
        return $this;
    }

    /**
     * @return bool
     */
    public function isAbstract()
    {
        return $this->abstract;
    }

    /**
     * @param bool $abstract
     * @return ComplexType
     */
    public function setAbstract($abstract)
    {
        $this->abstract = $abstract;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getParentName()
    {
        return $this->parentName;
    }

    /**
     * @param string|null $parentName
     * @return $this
     */
    public function setParentName($parentName)
    {
        $this->parentName = $parentName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getOwnerName()
    {
        return $this->ownerName;
    }

    /**
     * @param string|null $ownerName
     * @return $this
     */
    public function setOwnerName($ownerName)
    {
        $this->ownerName = $ownerName;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getResponseTypes()
    {
        return $this->responseTypes;
    }

    /**
     * @param string[] $responseTypes
     * @return $this
     */
    public function setResponseTypes($responseTypes)
    {
        $this->responseTypes = $responseTypes;
        return $this;
    }

    /**
     * @return Choice[]|Sequence[]
     */
    public function getGroups()
    {
        return $this->groups;
    }

    /**
     * @param Choice[]|Sequence[] $groups
     * @return ComplexType
     */
    public function setGroups($groups)
    {
        $this->groups = $groups;
        return $this;
    }

}