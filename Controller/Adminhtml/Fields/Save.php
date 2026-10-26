<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxCustomerAddress\Controller\Adminhtml\Fields;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Controller\Result\Redirect;
use OOCS\LynxCustomerAddress\Model\FieldSettings;

/**
 * Saves the address fields form, rows in their dragged order
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxCustomerAddress::fields';

    /**
     * Context, field settings, cache type list
     *
     * @param Context $context
     * @param FieldSettings $fieldSettings
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        Context $context,
        private readonly FieldSettings $fieldSettings,
        private readonly TypeListInterface $cacheTypeList,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $rows = array_values((array) $this->getRequest()->getPost('rows', []));
        usort($rows, static fn (array $a, array $b) => (int) ($a['position'] ?? 0) <=> (int) ($b['position'] ?? 0));

        try {
            $this->fieldSettings->save($rows);
            // The registration page is cached with its form
            $this->cacheTypeList->invalidate('full_page');
            $this->messageManager->addSuccessMessage(__('You saved the address fields.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
