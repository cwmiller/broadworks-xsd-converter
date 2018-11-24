<?php

namespace CWM\BroadWorksXsdConverter;

class EnumType extends SimpleType
{
    /** @var string[] */
    private $options = [];

    /**
     * @return string[]
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param string[] $options
     * @return $this
     */
    public function setOptions($options)
    {
        $this->options = $options;
        return $this;
    }
}