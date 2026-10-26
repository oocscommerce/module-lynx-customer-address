<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxCustomerAddress\Ui\DataProvider;

use Magento\Customer\Model\ResourceModel\Address\Attribute\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;
use OOCS\LynxCustomerAddress\Model\FieldSettings;

/**
 * The address fields form: one row per attribute, in sort order
 */
class FieldsDataProvider extends AbstractDataProvider
{
    /**
     * Data provider names, the attribute collection the base class filters, the field settings
     *
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param FieldSettings $fieldSettings
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly FieldSettings $fieldSettings,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * The rows under the form's empty id: the form edits every attribute at once
     *
     * @return array
     */
    public function getData(): array
    {
        $rows = [];
        foreach ($this->fieldSettings->getRows() as $i => $row) {
            $rows[] = $row + ['record_id' => $i, 'position' => $i];
        }

        return ['' => ['rows' => $rows]];
    }
}
