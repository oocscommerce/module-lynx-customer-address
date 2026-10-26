<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxCustomerAddress\Block\Adminhtml\Fields;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Save of the address fields form: submits the form component rather than following a URL
 */
class SaveButton implements ButtonProviderInterface
{
    /**
     * Get Button Data
     *
     * @return array
     */
    public function getButtonData(): array
    {
        return [
            'label'          => __('Save'),
            'class'          => 'save primary',
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [
                            [
                                'targetName' => 'lynxaddress_fields_form.lynxaddress_fields_form',
                                'actionName' => 'save',
                                'params'     => [false],
                            ],
                        ],
                    ],
                ],
            ],
            'sort_order'     => 90,
        ];
    }
}
