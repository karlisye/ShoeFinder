import assert from 'node:assert/strict'
import test from 'node:test'

import {
  catalogueApiQuery,
  catalogueFilterCount,
  catalogueFiltersFromQuery,
  catalogueRouteQuery
} from '../app/utils/catalogueQuery.js'

test('catalogue query values become stable filter state', () => {
  const filters = catalogueFiltersFromQuery({
    search: 'Runner',
    brand: ['nike', 'adidas', 'nike'],
    size: '42',
    in_stock: '1',
    on_sale: '0',
    sort: 'price_asc',
    page: '3'
  })

  assert.deepEqual(filters.brand, ['nike', 'adidas'])
  assert.deepEqual(filters.size, ['42'])
  assert.equal(filters.search, 'Runner')
  assert.equal(filters.in_stock, true)
  assert.equal(filters.on_sale, false)
  assert.equal(filters.sort, 'price_asc')
  assert.equal(filters.page, 3)
})

test('route query omits empty values and default navigation state', () => {
  const query = catalogueRouteQuery({
    ...catalogueFiltersFromQuery({}),
    search: '  Air Max  ',
    category: ['running'],
    in_stock: true
  })

  assert.deepEqual(query, {
    search: 'Air Max',
    category: ['running'],
    in_stock: '1'
  })
})

test('API query uses bracketed array fields and explicit API defaults', () => {
  const query = catalogueApiQuery(
    {
      brand: ['nike', 'adidas'],
      size: '42',
      max_price: '120.00',
      on_sale: '1',
      sort: 'price_desc',
      page: '2'
    },
    'en'
  )

  assert.deepEqual(query, {
    locale: 'en',
    currency: 'EUR',
    sort: 'price_desc',
    page: 2,
    per_page: 12,
    'brand[]': ['nike', 'adidas'],
    'size[]': ['42'],
    max_price: '120.00',
    on_sale: '1'
  })
})

test('active filter count excludes search, sorting, and pagination', () => {
  const count = catalogueFilterCount({
    ...catalogueFiltersFromQuery({ search: 'Runner', sort: 'name', page: '4' }),
    brand: ['nike'],
    size: ['42', '43'],
    min_price: '50',
    on_sale: true
  })

  assert.equal(count, 5)
})
