// What an address form checks before it posts: the checkout, the address book and registration ask the same

type Field = Lynx.AddressField
type Form = Record<string, string | undefined>

/** Message keys the caller's labels hold; the length ones take the field label (%1) and the limit (%2). */
export type AddressMessage =
    | 'required'
    | 'invalidFirstname'
    | 'invalidLastname'
    | 'invalidStreet'
    | 'invalidCity'
    | 'invalidPhone'
    | 'invalidPostcode'
    | 'minLength'
    | 'maxLength'
    | 'notAlphanumeric'
    | 'notNumeric'
    | 'notAlpha'
    | 'notEmail'
    | 'notUrl'

/** Stores > Configuration > General: countries whose state may not be left empty, and whose ZIP code may. */
export type CountryRules = { regionRequiredCountries: string[]; optionalZipCountries: string[] }

/** Indexes of a street field's lines: street0..n. */
export const streetLines = (field: Field) => Array.from({ length: field.lines ?? 1 }, (_, i) => i)

/** Whether a field is required on a form: the state and the ZIP code also by the form's country. */
export function isRequired(field: Field, form: Form, rules: CountryRules): boolean {
    const country = form.country_id ?? ''
    if (field.type === 'region') return field.required || rules.regionRequiredCountries.includes(country)
    if (field.name === 'postcode') return field.required || !rules.optionalZipCountries.includes(country)
    return field.required
}

// Mirrors of the server's field validators (Magento\Customer\Model\Validator\*)
const NAME = /^[\p{L}\p{M},\-_.'’`&\s\d]{1,255}$/u
const PATTERNS: Record<string, [RegExp, AddressMessage]> = {
    firstname: [NAME, 'invalidFirstname'],
    lastname: [NAME, 'invalidLastname'],
    street: [/^[\p{L}\p{M}"[\],\-.'’`&\s\d]{1,255}$/u, 'invalidStreet'],
    city: [/^[\p{L}\p{M}\s\-']{1,100}$/u, 'invalidCity'],
    // Digits with + - ( ) and spaces, at most 20 characters, 7–15 of them digits
    telephone: [/^[\d\s+\-()]{1,20}$/, 'invalidPhone'],
    // Letters, digits, spaces and hyphens, 2–12 characters: a cheap screen against obvious typos
    postcode: [/^[A-Za-z0-9\s-]{2,12}$/, 'invalidPostcode'],
}

/** The attribute's input validation, as the server's validators read it (Eav\Model\Attribute\Data\AbstractData) */
const INPUT: Record<string, [(value: string) => boolean, AddressMessage]> = {
    alphanumeric: [value => /^[\p{L}\p{N}]+$/u.test(value), 'notAlphanumeric'],
    'alphanum-with-spaces': [value => /^[\p{L}\p{N}\s]+$/u.test(value), 'notAlphanumeric'],
    numeric: [value => /^[0-9]+$/.test(value), 'notNumeric'],
    alpha: [value => /^\p{L}+$/u.test(value), 'notAlpha'],
    email: [value => /^[^@\s]+@[^@\s]+\.[^@\s.]+$/.test(value), 'notEmail'],
    // A scheme and a host, as parse_url() asks
    url: [value => URL.canParse(value) && new URL(value).hostname !== '', 'notUrl'],
}

/** What is wrong with one value, or null: empty when required, the server's pattern, input validation, length. */
function problem(field: Field, raw: string | undefined, required: boolean, message: (key: AddressMessage) => string) {
    const value = (raw ?? '').trim()
    if (!value) return required ? message('required') : null
    const pattern = PATTERNS[field.name]
    if (pattern && !pattern[0].test(value)) return message(pattern[1])
    const digits = value.replace(/\D/g, '').length
    if (field.name === 'telephone' && (digits < 7 || digits > 15)) return message('invalidPhone')
    const length = (key: AddressMessage, limit: number) =>
        message(key).replace('%1', field.label).replace('%2', String(limit))
    const input = INPUT[field.input_validation ?? '']
    if (input && !input[0](value)) return message(input[1]).replace('%1', field.label)
    if (field.min_length && value.length < field.min_length) return length('minLength', field.min_length)
    if (field.max_length && value.length > field.max_length) return length('maxLength', field.max_length)
    return null
}

/**
 * The form's errors by input key, the region's two inputs reported as `region`; empty when it can be posted
 *
 * hasRegions: the form's country has a region list, so the region is picked as region_id.
 */
export function addressErrors(
    fields: Field[],
    form: Form,
    rules: CountryRules & { hasRegions: boolean; message: (key: AddressMessage) => string }
): Record<string, string> {
    const errors: Record<string, string> = {}
    const check = (key: string, field: Field, required: boolean) => {
        const found = problem(field, form[key], required, rules.message)
        if (found) errors[key] = found
    }
    for (const field of fields) {
        const required = isRequired(field, form, rules)
        if (field.type === 'street') {
            for (const i of streetLines(field)) check(`street${i}`, field, required && !i)
        } else if (field.type === 'region') {
            // Required or nothing: the select's value is an id, not text to measure
            if (required && !(rules.hasRegions ? form.region_id : form.region)?.trim())
                errors.region = rules.message('required')
        } else if (field.type === 'country') {
            if (required && !form.country_id) errors.country_id = rules.message('required')
        } else {
            check(field.name, field, required)
        }
    }
    return errors
}
