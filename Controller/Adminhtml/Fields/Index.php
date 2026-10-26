<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxCustomerAddress\Controller\Adminhtml\Fields;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * The address fields form
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxCustomerAddress::fields';

    /**
     * Context, result page factory
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return Page
     */
    public function execute(): Page
    {
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('OOCS_LynxCustomerAddress::fields');
        $page->getConfig()->getTitle()->prepend(__('Customer Address Fields'));

        return $page;
    }
}
