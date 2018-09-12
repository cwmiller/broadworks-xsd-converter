<?php

namespace CWM\BroadWorksXsdConverter;

class EnumType extends SimpleType
{
    /** @var array */
    private $values;

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param array $values
     * @return $this
     */
    public function setValues($values)
    {
        $this->values = $values;
        return $this;
    }
}