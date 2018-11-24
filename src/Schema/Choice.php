<?php

namespace CWM\BroadWorksXsdConverter\Schema;

class Choice
{
    /** @var string */
    private $id;

    /** @var bool */
    private $isOptional = false;

    /** @var Sequence[]|Choice[] */
    private $children;

    public function __construct($id, $children = [], $isOptional = false)
    {
        $this->id = $id;
        $this->children = $children;
        $this->isOptional = $isOptional;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return Choice
     */
    public function setId($id)
    {
        $this->id = $id;
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
     * @return Choice
     */
    public function setIsOptional($isOptional)
    {
        $this->isOptional = $isOptional;
        return $this;
    }

    /**
     * @return Choice[]|Sequence[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param Choice[]|Sequence[] $children
     * @return Choice
     */
    public function setChildren($children)
    {
        $this->children = $children;
        return $this;
    }

    /**
     * @param Choice[]|Sequence[] $child
     * @return Choice
     */
    public function addChild($child) {
        $this->children[] = $child;
        return $this;
    }
}