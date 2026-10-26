<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxCustomerAddress\Model;

use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Customer\Model\Address\AbstractAddress;
use Magento\Framework\App\ResourceConnection;

/**
 * The fields of an address form: the attributes on that form in sort order, with width, required and length limits
 */
class AddressFields
{
    /**
     * The address book's form
     */
    public const FORM_ADDRESS_BOOK = 'customer_address_edit';

    /**
     * The address part of the registration form
     */
    public const FORM_REGISTER = 'customer_register_address';

    /**
     * The checkout asks for the registration form's fields, as core's checkout does
     */
    public const FORM_CHECKOUT = self::FORM_REGISTER;

    /**
     * The input_validation rules the client mirrors; core's `date` has no place on an address form
     */
    public const INPUT_VALIDATIONS = ['alphanumeric', 'alphanum-with-spaces', 'numeric', 'alpha', 'email', 'url'];

    /**
     * Resolved fields per form code, once read
     *
     * @var array<string, array>
     */
    private array $fields = [];

    /**
     * Attribute metadata, and the connection the widths are read with
     *
     * @param AddressMetadataInterface $metadata
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly AddressMetadataInterface $metadata,
        private readonly ResourceConnection $resourceConnection,
    ) {
    }

    /**
     * The fields of a form: name, label, type, required, width, custom_attribute, min/max length, lines for a street
     *
     * @param string $formCode
     * @return array<int, array<string, mixed>>
     */
    public function get(string $formCode = self::FORM_ADDRESS_BOOK): array
    {
        return $this->fields[$formCode] ??= $this->resolve($formCode);
    }

    /**
     * What the client says when a field fails, keyed as utils/addressFields reads them; %1 and %2 filled there
     *
     * @return array<string, string>
     */
    public function getMessages(): array
    {
        return [
            'required'         => (string) __('This is a required field.'),
            'invalidFirstname' => (string) __('First Name is not valid!'),
            'invalidLastname'  => (string) __('Last Name is not valid!'),
            'invalidStreet'    => (string) __("Invalid Street Address. Please use A-Z, a-z, 0-9, , - . ' ’ ` & spaces"),
            'invalidCity'      => (string) __("Invalid City. Please use A-Z, a-z, 0-9, -, ', spaces"),
            'invalidPhone'     => (string) __('Please specify a valid phone number'),
            'invalidPostcode'  => (string) __('Please enter a valid zip code.'),
            // The server's own wording (Eav\Model\Attribute\Data\Text and AbstractData)
            'minLength'        => (string) __('"%1" length must be equal or greater than %2 characters.', '%1', '%2'),
            'maxLength'        => (string) __('"%1" length must be equal or less than %2 characters.', '%1', '%2'),
            'notAlphanumeric'  => (string) __('"%1" contains non-alphabetic or non-numeric characters.', '%1'),
            'notNumeric'       => (string) __('"%1" contains non-numeric characters.', '%1'),
            'notAlpha'         => (string) __('"%1" contains non-alphabetic characters.', '%1'),
            'notEmail'         => (string) __('"%1" is not a valid email address.', '%1'),
            'notUrl'           => (string) __('"%1" is not a valid URL.', '%1'),
        ];
    }

    /**
     * An address as the form the client edits: street as street0..n, region as region + region_id
     *
     * @param AbstractAddress $address
     * @param string $formCode
     * @return array<string, string>
     */
    public function asForm(AbstractAddress $address, string $formCode = self::FORM_ADDRESS_BOOK): array
    {
        $form = [];
        foreach ($this->get($formCode) as $field) {
            switch ($field['type']) {
                case 'street':
                    $street = array_values(array_filter((array) $address->getStreet()));
                    for ($i = 0; $i < $field['lines']; $i++) {
                        $form['street' . $i] = (string) ($street[$i] ?? '');
                    }
                    break;
                case 'region':
                    $form['region'] = (string) $address->getRegion();
                    $form['region_id'] = (string) $address->getRegionId();
                    break;
                default:
                    $form[$field['name']] = (string) ($field['custom_attribute']
                        ? $address->getCustomAttribute($field['name'])?->getValue() ?? $address->getData($field['name'])
                        : $address->getData($field['name']));
            }
        }

        return $form;
    }

    /**
     * The same form from an address as the web API takes it (street as an array)
     *
     * @param array $payload
     * @param string $formCode
     * @return array<string, string>
     */
    public function formOfPayload(array $payload, string $formCode = self::FORM_ADDRESS_BOOK): array
    {
        $form = [];
        foreach ($this->get($formCode) as $field) {
            switch ($field['type']) {
                case 'street':
                    $street = array_values((array) ($payload['street'] ?? []));
                    for ($i = 0; $i < $field['lines']; $i++) {
                        $form['street' . $i] = (string) ($street[$i] ?? '');
                    }
                    break;
                case 'region':
                    $form['region'] = (string) ($payload['region'] ?? '');
                    $form['region_id'] = (string) ($payload['region_id'] ?? '');
                    break;
                default:
                    $form[$field['name']] = (string) ($field['custom_attribute']
                        ? $this->getCustomAttributeValue($payload, $field['name'])
                        : $payload[$field['name']] ?? '');
            }
        }

        return $form;
    }

    /**
     * The visible attributes of a form in sort order
     *
     * @param string $formCode
     * @return array<int, array<string, mixed>>
     */
    private function resolve(string $formCode): array
    {
        // region_id is rendered with region
        $attributes = array_filter(
            $this->metadata->getAttributes($formCode),
            static fn ($field) => $field->isVisible() && $field->getAttributeCode() !== 'region_id'
        );
        usort($attributes, static fn ($a, $b) => (int) $a->getSortOrder() <=> (int) $b->getSortOrder());
        $widths = $this->getWidths();

        $fields = [];
        foreach ($attributes as $attribute) {
            $code = $attribute->getAttributeCode();
            $field = [
                'name'     => $code,
                'label'    => (string) $attribute->getFrontendLabel() ?: $code,
                'type'     => $this->typeOf($code),
                'required' => (bool) $attribute->isRequired(),
                'width'    => ($widths[$code] ?? '') === 'full' ? 'full' : 'half',
                // The web API takes merchant-created attributes under custom_attributes only
                'custom_attribute' => (bool) $attribute->isUserDefined(),
            ];
            $field += $this->rulesOf($attribute->getValidationRules());
            if ($code === 'street') {
                $field['lines'] = max(1, (int) $attribute->getMultilineCount());
            }
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * The validate rules the client checks: length limits and the input validation
     *
     * @param \Magento\Customer\Api\Data\ValidationRuleInterface[] $rules
     * @return array<string, int|string>
     */
    private function rulesOf(array $rules): array
    {
        $limits = ['min_text_length' => 'min_length', 'max_text_length' => 'max_length'];
        $checks = [];
        foreach ($rules as $rule) {
            $name = $rule->getName();
            $value = (string) $rule->getValue();
            if (isset($limits[$name]) && (int) $value > 0) {
                $checks[$limits[$name]] = (int) $value;
            } elseif ($name === 'input_validation' && \in_array($value, self::INPUT_VALIDATIONS, true)) {
                $checks['input_validation'] = $value;
            }
        }

        return $checks;
    }

    /**
     * Width of every address attribute by code, set in Stores > Attributes > Customer Address Fields
     *
     * @return array<string, string>
     */
    private function getWidths(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(['ea' => $this->resourceConnection->getTableName('eav_attribute')], ['attribute_code'])
            ->join(
                ['cea' => $this->resourceConnection->getTableName('customer_eav_attribute')],
                'cea.attribute_id = ea.attribute_id',
                ['form_width']
            )
            ->join(
                ['et' => $this->resourceConnection->getTableName('eav_entity_type')],
                'et.entity_type_id = ea.entity_type_id',
                []
            )
            ->where('et.entity_type_code = ?', AddressMetadataInterface::ENTITY_TYPE_ADDRESS);

        return $connection->fetchPairs($select);
    }

    /**
     * Value of a custom attribute in a web API address
     *
     * @param array $payload
     * @param string $code
     * @return string
     */
    private function getCustomAttributeValue(array $payload, string $code): string
    {
        foreach ((array) ($payload['custom_attributes'] ?? []) as $attribute) {
            if (($attribute['attribute_code'] ?? '') === $code) {
                return (string) ($attribute['value'] ?? '');
            }
        }

        return '';
    }

    /**
     * How the client renders the attribute: its own control, a phone input, or plain text
     *
     * @param string $code
     * @return string
     */
    private function typeOf(string $code): string
    {
        return match ($code) {
            'country_id'       => 'country',
            'region'           => 'region',
            'street'           => 'street',
            'telephone', 'fax' => 'tel',
            default            => 'text',
        };
    }
}
