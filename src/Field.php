<?php

namespace CWM\BroadWorksXsdConverter;

class Field
{
    /** @var string|null */
    private $name;

    /** @var string|null */
    private $typeName;

    /** @var string|null */
    private $description;

    /** @var bool */
    private $isArray = false;

    /** @var bool */
    private $isNillable = false;

    /** @var bool */
    private $isOptional = false;

    /** @var string|null */
    private $groupId;

    /**
     * @return null|string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param null|string $name
     * @return Field
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getTypeName()
    {
        return $this->typeName;
    }

    /**
     * @param null|string $typeName
     * @return Field
     */
    public function setTypeName($typeName)
    {
        $this->typeName = $typeName;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param null|string $description
     * @return Field
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return bool
     */
    public function isArray()
    {
        return $this->isArray;
    }

    /**
     * @param bool $isArray
     * @return Field
     */
    public function setIsArray($isArray)
    {
        $this->isArray = $isArray;
        return $this;
    }

    /**
     * @return bool
     */
    public function isNillable()
    {
        return $this->isNillable;
    }

    /**
     * @param bool $isNillable
     * @return Field
     */
    public function setIsNillable($isNillable)
    {
        $this->isNillable = $isNillable;
        return $this;
    }

    /**
     * @return bool
     */
    public function isOptional()
    {
        return $this->isOptional;
    }

    /**
     * @param bool $isOptional
     * @return Field
     */
    public function setIsOptional($isOptional)
    {
        $this->isOptional = $isOptional;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getGroupId()
    {
        return $this->groupId;
    }

    /**
     * @param null|string $groupId
     * @return Field
     */
    public function setGroupId($groupId)
    {
        $this->groupId = $groupId;
        return $this;
    }
}