<?php


class Creditkey_B2bgateway_Model_Source_Config_Displaysize extends Mage_Core_Model_Config_Data
{
    /**
     * Get possible sharing configuration options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            ['value' => "small", 'label' => 'Small'],
            ['value' => "medium", 'label' => 'Medium'],
            ['value' => "large", 'label' => 'Large']
        ];

        return $options;

    }

}