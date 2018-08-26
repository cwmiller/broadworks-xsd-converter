<?php

namespace CWM\BroadWorksXsdConverter;

class ComplexType extends Type
{
    /** @var Field[] */
    protected $fields = [];

    /** @var bool */
    protected $abstract = false;

    /** @var string */
    protected $parentName;

    /** @var string */
    protected $ownerName;

    /** @var string[] */
    protected $responseTypes = [];

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
     * @return string
     */
    public function getParentName()
    {
        return $this->parentName;
    }

    /**
     * @param string $parentName
     * @return $this
     */
    public function setParentName($parentName)
    {
        $this->parentName = $parentName;
        return $this;
    }

    /**
     * @return string
     */
    public function getOwnerName()
    {
        return $this->ownerName;
    }

    /**
     * @param string $ownerName
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
}