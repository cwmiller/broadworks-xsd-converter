<?php

namespace CWM\BroadWorksXsdConverter;

class SimpleType extends Type
{
    /** @var string|null */
    private $restriction;

    /**
     * @return string|null
     */
    public function getRestriction()
    {
        return $this->restriction;
    }

    /**
     * @param string|null $restriction
     * @return $this
     */
    public function setRestriction($restriction)
    {
        $this->restriction = $restriction;
        return $this;
    }
}