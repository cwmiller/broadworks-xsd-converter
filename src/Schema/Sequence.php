<?php

namespace CWM\BroadWorksXsdConverter\Schema;

class Sequence
{
    /** @var string */
    private $id;

    /** @var Sequence[]|Choice[] */
    private $children;

    public function __construct($id, $children = [])
    {
        $this->id = $id;
        $this->children = $children;
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
     * @return Sequence
     */
    public function setId($id)
    {
        $this->id = $id;
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
     * @return Sequence
     */
    public function setChildren($children)
    {
        $this->children = $children;
        return $this;
    }

    /**
     * @param Choice[]|Sequence[] $child
     * @return Sequence
     */
    public function addChild($child) {
        $this->children[] = $child;
        return $this;
    }
}