<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxCustomerAddress\Setup\Patch\Data;

use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Company and street take the whole row of the address form; every other field half of it (the column default)
 */
class InstallFormWidths implements DataPatchInterface
{
    /**
     * Module data setup
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
    ) {
    }

    /**
     * Apply
     *
     * @return $this
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $attributeIds = $connection->fetchCol(
            $connection->select()
                ->from(['ea' => $this->moduleDataSetup->getTable('eav_attribute')], ['attribute_id'])
                ->join(
                    ['et' => $this->moduleDataSetup->getTable('eav_entity_type')],
                    'et.entity_type_id = ea.entity_type_id',
                    []
                )
                ->where('et.entity_type_code = ?', AddressMetadataInterface::ENTITY_TYPE_ADDRESS)
                ->where('ea.attribute_code IN (?)', ['company', 'street'])
        );
        if ($attributeIds) {
            $connection->update(
                $this->moduleDataSetup->getTable('customer_eav_attribute'),
                ['form_width' => 'full'],
                ['attribute_id IN (?)' => $attributeIds]
            );
        }

        return $this;
    }

    /**
     * Get Dependencies
     *
     * @return array
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Get Aliases
     *
     * @return array
     */
    public function getAliases(): array
    {
        return [];
    }
}
