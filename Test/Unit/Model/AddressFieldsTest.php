<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxCustomerAddress\Test\Unit\Model;

use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Customer\Api\Data\AttributeMetadataInterface;
use Magento\Customer\Api\Data\ValidationRuleInterface;
use Magento\Framework\Api\AttributeValue;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Quote\Model\Quote\Address;
use OOCS\LynxCustomerAddress\Model\AddressFields;
use PHPUnit\Framework\TestCase;

/**
 * The fields of an address form from the attribute metadata and the widths, and the same form read from an address
 * and from a posted payload
 */
class AddressFieldsTest extends TestCase
{
    /**
     * @var AddressFields
     */
    private AddressFields $fields;

    /**
     * Address attributes out of sort order: core ones, a merchant one (tax_code), an invisible one and region_id
     *
     * @return void
     */
    protected function setUp(): void
    {
        $attributes = [
            $this->attribute('telephone', 'Phone Number', 50, true, rules: ['input_validation' => 'date']),
            $this->attribute(
                'firstname',
                'First Name',
                10,
                true,
                rules: ['min_text_length' => 2, 'max_text_length' => 40, 'input_validation' => 'alpha']
            ),
            $this->attribute('street', 'Street Address', 20, true, multiline: 3),
            $this->attribute('country_id', 'Country', 30, true),
            $this->attribute('region', 'State/Province', 40),
            $this->attribute('region_id', 'State/Province', 40),
            $this->attribute('tax_code', '', 60, userDefined: true),
            $this->attribute('hidden_one', 'Hidden', 70, visible: false),
        ];

        $this->fields = new AddressFields(
            $this->metadata($attributes),
            $this->resource(['street' => 'full', 'country_id' => 'wide'])
        );
    }

    /**
     * Metadata answering the given attributes for the address book form
     *
     * @param array $attributes
     * @return AddressMetadataInterface
     */
    private function metadata(array $attributes): AddressMetadataInterface
    {
        $metadata = $this->createMock(AddressMetadataInterface::class);
        $metadata->method('getAttributes')->with('customer_address_edit')->willReturn($attributes);

        return $metadata;
    }

    /**
     * A connection whose width query answers the given pairs
     *
     * @param array $widths
     * @return ResourceConnection
     */
    private function resource(array $widths): ResourceConnection
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchPairs')->willReturn($widths);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return $resource;
    }

    /**
     * Attribute metadata as the customer address form gives it
     *
     * @param string $code
     * @param string $label
     * @param int $sortOrder
     * @param bool $required
     * @param bool $visible
     * @param bool $userDefined
     * @param int $multiline
     * @param array $rules
     * @return AttributeMetadataInterface
     */
    private function attribute(
        string $code,
        string $label,
        int $sortOrder,
        bool $required = false,
        bool $visible = true,
        bool $userDefined = false,
        int $multiline = 0,
        array $rules = []
    ): AttributeMetadataInterface {
        $validationRules = [];
        foreach ($rules as $name => $value) {
            $rule = $this->createMock(ValidationRuleInterface::class);
            $rule->method('getName')->willReturn($name);
            $rule->method('getValue')->willReturn((string) $value);
            $validationRules[] = $rule;
        }
        $attribute = $this->createMock(AttributeMetadataInterface::class);
        $attribute->method('getAttributeCode')->willReturn($code);
        $attribute->method('getFrontendLabel')->willReturn($label);
        $attribute->method('getSortOrder')->willReturn($sortOrder);
        $attribute->method('isRequired')->willReturn($required);
        $attribute->method('isVisible')->willReturn($visible);
        $attribute->method('isUserDefined')->willReturn($userDefined);
        $attribute->method('getMultilineCount')->willReturn($multiline);
        $attribute->method('getValidationRules')->willReturn($validationRules);

        return $attribute;
    }

    /**
     * Fields follow the sort order; invisible attributes and region_id are skipped
     *
     * @return void
     */
    public function testFields(): void
    {
        $fields = $this->fields->get();

        $this->assertSame(
            ['firstname', 'street', 'country_id', 'region', 'telephone', 'tax_code'],
            array_column($fields, 'name')
        );
        $this->assertSame(['text', 'street', 'country', 'region', 'tel', 'text'], array_column($fields, 'type'));
    }

    /**
     * Width is full or half; street has its line count; length limits come from the rules; label falls back to the code
     *
     * @return void
     */
    public function testFieldDetails(): void
    {
        $fields = array_column($this->fields->get(), null, 'name');

        $this->assertSame('full', $fields['street']['width']);
        $this->assertSame('half', $fields['country_id']['width']);
        $this->assertSame(3, $fields['street']['lines']);
        $this->assertArrayNotHasKey('lines', $fields['firstname']);
        $this->assertSame(2, $fields['firstname']['min_length']);
        $this->assertSame(40, $fields['firstname']['max_length']);
        $this->assertArrayNotHasKey('max_length', $fields['telephone']);
        $this->assertSame('alpha', $fields['firstname']['input_validation']);
        // A rule the client cannot check on an address form is left to the server
        $this->assertArrayNotHasKey('input_validation', $fields['telephone']);
        $this->assertSame('tax_code', $fields['tax_code']['label']);
        $this->assertTrue($fields['tax_code']['custom_attribute']);
        $this->assertFalse($fields['firstname']['custom_attribute']);
        $this->assertTrue($fields['firstname']['required']);
    }

    /**
     * A street with no line count gets one line; a form is resolved once
     *
     * @return void
     */
    public function testSingleLineStreetAndCachedForm(): void
    {
        $metadata = $this->createMock(AddressMetadataInterface::class);
        $metadata->expects($this->once())->method('getAttributes')
            ->willReturn([$this->attribute('street', 'Street', 10)]);
        $fields = new AddressFields($metadata, $this->resource([]));

        $this->assertSame(1, $fields->get()[0]['lines']);
        $this->assertSame(1, $fields->get()[0]['lines']);
    }

    /**
     * An address and the payload posting the same address give the same form
     *
     * @return void
     */
    public function testAddressAndPayloadGiveTheSameForm(): void
    {
        $address = $this->getMockBuilder(Address::class)->disableOriginalConstructor()
            ->onlyMethods(['getRegion', 'getRegionId', 'getCustomAttribute'])->getMock();
        $address->setData([
            'firstname'  => 'Jane',
            'street'     => "1 Main St\nFloor 2",
            'country_id' => 'US',
            'telephone'  => '555',
        ]);
        $address->method('getRegion')->willReturn('Texas');
        $address->method('getRegionId')->willReturn(57);
        $address->method('getCustomAttribute')->with('tax_code')
            ->willReturn(new AttributeValue(['attribute_code' => 'tax_code', 'value' => 'T1']));

        $payload = [
            'firstname'         => 'Jane',
            'street'            => ['1 Main St', 'Floor 2'],
            'country_id'        => 'US',
            'region'            => 'Texas',
            'region_id'         => 57,
            'telephone'         => '555',
            'custom_attributes' => [['attribute_code' => 'tax_code', 'value' => 'T1']],
        ];
        $expected = [
            'firstname'  => 'Jane',
            'street0'    => '1 Main St',
            'street1'    => 'Floor 2',
            'street2'    => '',
            'country_id' => 'US',
            'region'     => 'Texas',
            'region_id'  => '57',
            'telephone'  => '555',
            'tax_code'   => 'T1',
        ];

        $this->assertSame($expected, $this->fields->asForm($address));
        $this->assertSame($expected, $this->fields->formOfPayload($payload));
    }

    /**
     * Missing payload values read as empty strings
     *
     * @return void
     */
    public function testEmptyPayload(): void
    {
        $form = $this->fields->formOfPayload([]);

        $this->assertSame('', $form['street0']);
        $this->assertSame('', $form['region_id']);
        $this->assertSame('', $form['tax_code']);
    }
}
