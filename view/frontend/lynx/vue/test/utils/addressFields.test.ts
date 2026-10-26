import { addressErrors, isRequired, streetLines } from 'OOCS_LynxCustomerAddress/utils/addressFields'
import { describe, expect, it } from 'vitest'

const field = (name: string, extra: Partial<Lynx.AddressField> = {}): Lynx.AddressField => ({
    name,
    label: name,
    type: 'text',
    required: false,
    width: 'half',
    custom_attribute: false,
    ...extra,
})

const fields = [
    field('firstname', { required: true }),
    field('street', { type: 'street', required: true, lines: 2 }),
    field('country_id', { type: 'country', required: true }),
    field('region', { type: 'region' }),
    field('postcode'),
    field('telephone', { type: 'tel', required: true }),
    field('company', { max_length: 5 }),
]

const rules = (extra: Record<string, unknown> = {}) => ({
    regionRequiredCountries: ['US'],
    optionalZipCountries: ['HK'],
    hasRegions: true,
    message: (key: string) =>
        ({ minLength: '%1 min %2', maxLength: '%1 max %2' } as Record<string, string>)[key] ?? key,
    ...extra,
})

const valid = { firstname: 'Ann', street0: '1 Main St', country_id: 'US', region_id: '12', postcode: '90001', telephone: '555 0100' }

describe('addressFields', () => {
    it('numbers the street lines', () => {
        expect(streetLines(fields[1])).toEqual([0, 1])
        expect(streetLines(field('street', { type: 'street' }))).toEqual([0])
    })

    it('requires the state and the ZIP code by country', () => {
        expect(isRequired(fields[3], { country_id: 'US' }, rules())).toBe(true)
        expect(isRequired(fields[3], { country_id: 'GB' }, rules())).toBe(false)
        expect(isRequired(fields[4], { country_id: 'GB' }, rules())).toBe(true)
        expect(isRequired(fields[4], { country_id: 'HK' }, rules())).toBe(false)
    })

    it('passes a complete address', () => {
        expect(addressErrors(fields, valid, rules())).toEqual({})
    })

    it('reports each empty required field, the region under one key', () => {
        expect(addressErrors(fields, { country_id: 'US' }, rules())).toEqual({
            firstname: 'required',
            street0: 'required',
            region: 'required',
            postcode: 'required',
            telephone: 'required',
        })
        // A country without a region list is asked for the typed name
        expect(addressErrors(fields, { ...valid, region_id: '' }, rules({ hasRegions: false })).region).toBe('required')
    })

    it("turns down what the server's validators would", () => {
        expect(addressErrors(fields, { ...valid, firstname: 'Ann<' }, rules())).toEqual({ firstname: 'invalidFirstname' })
        expect(addressErrors(fields, { ...valid, telephone: '12345' }, rules())).toEqual({ telephone: 'invalidPhone' })
        expect(addressErrors(fields, { ...valid, street1: 'Flat <2>' }, rules())).toEqual({ street1: 'invalidStreet' })
    })

    it('holds filled fields to their length limits, required or not', () => {
        expect(addressErrors(fields, { ...valid, company: 'Acme Inc' }, rules())).toEqual({ company: 'company max 5' })
        const short = [field('city', { required: true, min_length: 3 })]
        expect(addressErrors(short, { city: 'LA' }, rules())).toEqual({ city: 'city min 3' })
        expect(addressErrors(short, { city: '' }, rules())).toEqual({ city: 'required' })
    })

    it("checks the attribute's input validation, in the server's words", () => {
        const message = (key: string) => ({ notNumeric: '%1 digits', notEmail: '%1 email', notUrl: '%1 url' })[key] ?? key
        const check = (input_validation: string, value: string) =>
            addressErrors([field('code', { input_validation })], { code: value }, rules({ message }))
        expect(check('numeric', '12a')).toEqual({ code: 'code digits' })
        expect(check('numeric', '123')).toEqual({})
        expect(check('alphanum-with-spaces', 'Unit 4B')).toEqual({})
        expect(check('alphanumeric', 'Unit 4B')).toEqual({ code: 'notAlphanumeric' })
        expect(check('alpha', 'Zoë')).toEqual({})
        expect(check('email', 'ann@example')).toEqual({ code: 'code email' })
        expect(check('url', 'example.com')).toEqual({ code: 'code url' })
        expect(check('url', 'https://example.com')).toEqual({})
    })
})
