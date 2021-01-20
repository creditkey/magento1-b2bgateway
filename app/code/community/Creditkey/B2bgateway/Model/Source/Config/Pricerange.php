<?php


class Creditkey_B2bgateway_Model_Source_Config_Pricerange extends Mage_Core_Model_Config_Data
{
    /**
     * Get possible sharing configuration options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            ['value' => "0", 'label' => 'All Prices'],
            ['value' => "1000", 'label' => '$1,000 and under'],
            ['value' => "3000", 'label' => '$3,000 and under'],
            ['value' => "5000", 'label' => '$5,000 and under'],
            ['value' => "10000", 'label' => '$10,000 and under']
        ];

        return $options;

    }

}