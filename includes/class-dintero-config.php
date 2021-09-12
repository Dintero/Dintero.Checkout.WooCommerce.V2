<?php

class Dintero_Config extends WC_Settings_API
{
    /**
     * @var array
     */
    protected $options;

    /**
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->options = (array) $options;
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function is($key)
    {
        return $this->get($key) == 'yes';
    }
}
