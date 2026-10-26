<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxCustomerAddress\Model;

use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Customer\Model\Attribute;
use Magento\Eav\Model\Config as EavConfig;

/**
 * The address attributes as the admin edits them: order, width, required, length limits and the forms they are on
 */
class FieldSettings
{
    /**
     * The storefront forms the admin picks from; the checkout reads the registration one
     */
    private const FORMS = [AddressFields::FORM_ADDRESS_BOOK, AddressFields::FORM_REGISTER];

    /**
     * EAV config, which loads the address attributes as customer attribute models
     *
     * @param EavConfig $eavConfig
     */
    public function __construct(
        private readonly EavConfig $eavConfig,
    ) {
    }

    /**
     * Fields locked in the admin: always required and on every storefront form
     *
     * Core's address validators ask for them by name (Validator\General, Country); a module whose validator asks for
     * more adds them with an after-plugin.
     *
     * @return string[]
     */
    public function getAlwaysRequired(): array
    {
        return ['firstname', 'lastname', 'street', 'city', 'country_id'];
    }

    /**
     * One row per visible attribute in sort order; region_id follows region
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        $locked = $this->getAlwaysRequired();
        $rows = [];
        foreach ($this->getAttributes() as $code => $attribute) {
            if ($code === 'region_id') {
                continue;
            }
            $rules = $attribute->getValidateRules();
            $forms = $attribute->getUsedInForms();
            $row = [
                'attribute_code'   => $code,
                'label'            => (string) $attribute->getFrontendLabel() ?: $code,
                'width'            => $attribute->getData('form_width') === 'full' ? 'full' : 'half',
                // Strings: the toggles' value map arrives as "0"/"1" and is compared strictly
                'is_required'      => $attribute->getIsRequired() ? '1' : '0',
                // Required and Forms locked in the admin; Input Validation adds to core's validators
                'locked'           => \in_array($code, $locked, true),
                'min_length'       => (string) ($rules['min_text_length'] ?? ''),
                'max_length'       => (string) ($rules['max_text_length'] ?? ''),
                'input_validation' => (string) ($rules['input_validation'] ?? ''),
            ];
            $row['forms'] = array_values(array_intersect(self::FORMS, $forms));
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Save the rows: their order becomes the sort order
     *
     * @param array $rows
     * @return void
     */
    public function save(array $rows): void
    {
        $attributes = $this->getAttributes();
        $locked = $this->getAlwaysRequired();
        $position = 0;
        foreach ($rows as $row) {
            $code = (string) ($row['attribute_code'] ?? '');
            $attribute = $attributes[$code] ?? null;
            if ($attribute === null) {
                continue;
            }
            $position += 10;
            $attribute->setSortOrder($position)
                ->setIsRequired((int) (!empty($row['is_required']) || \in_array($code, $locked, true)))
                ->setData('form_width', ($row['width'] ?? '') === 'full' ? 'full' : 'half')
                ->setValidateRules($this->rules($attribute, $row))
                ->setData('used_in_forms', $this->forms($attribute, $row, \in_array($code, $locked, true)));
            $attribute->save();

            // The select half of the region field keeps its place
            if ($code === 'region' && isset($attributes['region_id'])) {
                $attributes['region_id']->setSortOrder($position)->save();
            }
        }
    }

    /**
     * The attribute's rules with the row's length limits and input validation; an empty one is dropped
     *
     * @param Attribute $attribute
     * @param array $row
     * @return array
     */
    private function rules(Attribute $attribute, array $row): array
    {
        $rules = $attribute->getValidateRules();
        foreach (['min_text_length' => 'min_length', 'max_text_length' => 'max_length'] as $rule => $key) {
            $value = (int) ($row[$key] ?? 0);
            if ($value > 0) {
                $rules[$rule] = $value;
            } else {
                unset($rules[$rule]);
            }
        }
        // date stays when set elsewhere: the admin offers only what an address form can check
        $validation = (string) ($row['input_validation'] ?? '');
        if (\in_array($validation, AddressFields::INPUT_VALIDATIONS, true)) {
            $rules['input_validation'] = $validation;
        } elseif (\in_array($rules['input_validation'] ?? '', AddressFields::INPUT_VALIDATIONS, true)) {
            unset($rules['input_validation']);
        }

        return $rules;
    }

    /**
     * The attribute's forms with the storefront ones picked in the row; the others (admin's) are kept
     *
     * @param Attribute $attribute
     * @param array $row
     * @param bool $locked on every storefront form whatever the row says
     * @return string[]
     */
    private function forms(Attribute $attribute, array $row, bool $locked): array
    {
        // An empty multiselect is posted as an empty string
        $picked = $locked ? self::FORMS : array_intersect(self::FORMS, (array) ($row['forms'] ?? []));

        return array_values(array_unique([...array_diff($attribute->getUsedInForms(), self::FORMS), ...$picked]));
    }

    /**
     * The visible address attributes by code, in sort order
     *
     * @return array<string, Attribute>
     */
    private function getAttributes(): array
    {
        $attributes = array_filter(
            $this->eavConfig->getEntityAttributes(AddressMetadataInterface::ENTITY_TYPE_ADDRESS),
            static fn (Attribute $attribute) => (bool) $attribute->getIsVisible()
        );
        uasort($attributes, static fn ($a, $b) => (int) $a->getSortOrder() <=> (int) $b->getSortOrder());

        return $attributes;
    }
}
