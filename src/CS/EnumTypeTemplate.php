<?php
namespace CWM\BroadWorksXsdConverter\CS;

class EnumTypeTemplate extends Template
{
    /** @var EnumOption[] */
    private $options;

    /**
     * @return EnumOption[]
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param EnumOption[] $options
     * @return $this
     */
    public function setOptions($options)
    {
        $this->options = $options;
        return $this;
    }
}