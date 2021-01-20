<?php


class Creditkey_B2bgateway_Model_Source_Config_Displaytype extends Mage_Core_Model_Config_Data
{
    /**
     * Get possible sharing configuration options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            ['value' => "text", 'label' => 'Text'],
            ['value' => "button", 'label' => 'Button']
        ];

        return $options;
    }

}