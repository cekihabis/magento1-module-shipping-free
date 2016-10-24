<?php
/**
 * Copyright © 2008-2016 Owebia. All rights reserved.
 * See COPYING.txt for license details.
 */

class Owebia_Shipping2_Model_Os2_Data
{
    protected $_data;

    public function __construct($data = null)
    {
        $this->_data = (array)$data;
    }

    public function __sleep()
    {
        return array_keys($this->_data);
    }

    public function __get($name)
    {
        return $this->getData($name);
    }

    public function getData($name)
    {
        return isset($this->_data[$name]) ? $this->_data[$name] : null;
    }

    public function set($name, $value)
    {
        $this->_data[$name] = $value;
    }
}
