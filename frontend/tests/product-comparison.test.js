import assert from 'node:assert/strict'
import test from 'node:test'

import {
  deliveryText,
  lowestProductPrice,
  offerPreviousPrice,
  orderedOffers,
  selectedVariant,
  validSelectedSize
} from '../app/utils/productComparison.js'

function listing(overrides = {}) {
  return {
    id: overrides.id ?? 1,
    retailer: {
      name: overrides.name ?? 'Shop A'
    },
    lowest_price: overrides.lowest_price ?? { amount: '90.00', currency: 'EUR' },
    currency: overrides.currency ?? 'EUR',
    in_stock: overrides.in_stock ?? true,
    fresh: overrides.fresh ?? true,
    stale: overrides.stale ?? false,
    delivery: {
      cost: overrides.delivery_cost ?? '5.00',
      min_days: 2,
      max_days: 4,
      delivered_total: overrides.delivered_total ?? '95.00'
    },
    sizes: overrides.sizes ?? [
      {
        label: '42',
        in_stock: true,
        effective_price: '90.00',
        delivered_total: '95.00'
      }
    ]
  }
}

test('variant and size selection use stable colour codes and labels', () => {
  const variants = [
    {
      colour: { code: 'black' },
      available_sizes: [{ label: '42' }]
    },
    {
      colour: { code: 'red' },
      available_sizes: [{ label: '43' }]
    }
  ]

  assert.equal(selectedVariant(variants, 'red').colour.code, 'red')
  assert.equal(selectedVariant(variants, 'missing').colour.code, 'black')
  assert.equal(validSelectedSize(variants[0], '42'), '42')
  assert.equal(validSelectedSize(variants[0], '43'), null)
})

test('selected-size offers use override-effective prices and availability', () => {
  const offers = orderedOffers(
    [
      listing({
        id: 1,
        name: 'Unavailable',
        sizes: [
          {
            label: '42',
            in_stock: false,
            effective_price: '70.00',
            delivered_total: '75.00'
          }
        ]
      }),
      listing({
        id: 2,
        name: 'Fresh',
        sizes: [
          {
            label: '42',
            in_stock: true,
            effective_price: '100.00',
            delivered_total: '105.00'
          }
        ]
      }),
      listing({
        id: 3,
        name: 'Stale',
        fresh: false,
        stale: true,
        sizes: [
          {
            label: '42',
            in_stock: true,
            effective_price: '80.00',
            delivered_total: '85.00'
          }
        ]
      })
    ],
    '42'
  )

  assert.deepEqual(
    offers.map((offer) => offer.id),
    [2, 3, 1]
  )
  assert.equal(offers[0].item_price, '100.00')
  assert.equal(offers[0].qualifies, true)
  assert.equal(offers[1].qualifies, false)
  assert.equal(offers[2].item_price, null)
})

test('lowest product price excludes stale and unavailable offers', () => {
  const variants = [
    {
      listings: [
        listing({ id: 1, fresh: false, stale: true, lowest_price: { amount: '50.00' } }),
        listing({ id: 2, lowest_price: { amount: '90.00' } })
      ]
    },
    {
      listings: [listing({ id: 3, name: 'Shop B', lowest_price: { amount: '80.00' } })]
    }
  ]

  assert.deepEqual(lowestProductPrice(variants, null), {
    amount: '80.00',
    currency: 'EUR'
  })
})

test('previous price is shown only for a real offer discount', () => {
  assert.equal(
    offerPreviousPrice({
      item_price: '89.99',
      original_price: '109.99'
    }),
    '109.99'
  )
  assert.equal(
    offerPreviousPrice({
      item_price: '109.99',
      original_price: '109.99'
    }),
    null
  )
  assert.equal(
    offerPreviousPrice({
      item_price: '119.99',
      original_price: '109.99'
    }),
    null
  )
  assert.equal(
    offerPreviousPrice({
      item_price: null,
      original_price: '109.99'
    }),
    null
  )
})

test('delivery copy never treats an unknown cost as free', () => {
  const formatMoney = (amount) => `€${amount}`

  assert.equal(
    deliveryText({ cost: null, min_days: 2, max_days: 4 }, 'lv', formatMoney).cost,
    'Piegādes cena nav norādīta'
  )
  assert.equal(
    deliveryText({ cost: '0.00', min_days: 2, max_days: 4 }, 'en', formatMoney).cost,
    'Free delivery'
  )
  assert.equal(
    deliveryText({ cost: '5.00', min_days: 2, max_days: 4 }, 'en', formatMoney).timeframe,
    '2 to 4 days'
  )
})
