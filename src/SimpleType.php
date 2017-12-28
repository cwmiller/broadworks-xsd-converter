<?php

namespace CWM\BroadWorksXsdConverter;

class SimpleType extends Type
{
    /** @var string */
    private $restriction;

    /**
     * @return string
     */
    public function getRestriction()
    {
        return $this->restriction;
    }

    /**
     * @param string $restriction
     * @return $this
     */
    public function setRestriction($restriction)
    {
        $this->restriction = $restriction;
        return $this;
    }
}