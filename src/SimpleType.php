<?php

namespace CWM\BroadWorksXsdConverter;

class SimpleType extends Type
{
    /** @var Restriction|null */
    private $restriction;

    /**
     * @return Restriction|null
     */
    public function getRestriction()
    {
        return $this->restriction;
    }

    /**
     * @param Restriction|null $restriction|
     * @return $this
     */
    public function setRestriction(Restriction $restriction = null)
    {
        $this->restriction = $restriction;
        return $this;
    }
}