<?php

namespace CWM\BroadWorksXsdConverter;

class EnumType extends SimpleType
{
    /** @var array */
    private $options;

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions($options)
    {
        $this->options = $options;
        return $this;
    }
}