export const arrayFilterKeys = ['brand', 'category', 'audience', 'colour', 'size', 'retailer']

function firstValue(value) {
  return Array.isArray(value) ? value[0] : value
}

function stringValue(value, fallback = '') {
  const selected = firstValue(value)

  return typeof selected === 'string' ? selected : fallback
}

function arrayValue(value) {
  if (Array.isArray(value)) {
    return [...new Set(value.filter((item) => typeof item === 'string' && item !== ''))]
  }

  return typeof value === 'string' && value !== '' ? [value] : []
}

function positiveInteger(value, fallback) {
  const parsed = Number.parseInt(stringValue(value), 10)

  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback
}

export function catalogueFiltersFromQuery(query = {}) {
  const filters = {
    search: stringValue(query.search),
    min_price: stringValue(query.min_price),
    max_price: stringValue(query.max_price),
    in_stock: stringValue(query.in_stock) === '1',
    on_sale: stringValue(query.on_sale) === '1',
    sort: stringValue(query.sort, 'newest'),
    page: positiveInteger(query.page, 1)
  }

  for (const key of arrayFilterKeys) {
    filters[key] = arrayValue(query[key])
  }

  return filters
}

export function catalogueRouteQuery(filters) {
  const query = {}

  if (filters.search?.trim()) {
    query.search = filters.search.trim()
  }

  for (const key of arrayFilterKeys) {
    if (filters[key]?.length) {
      query[key] = filters[key]
    }
  }

  if (filters.min_price !== '') {
    query.min_price = filters.min_price
  }

  if (filters.max_price !== '') {
    query.max_price = filters.max_price
  }

  if (filters.in_stock) {
    query.in_stock = '1'
  }

  if (filters.on_sale) {
    query.on_sale = '1'
  }

  if (filters.sort && filters.sort !== 'newest') {
    query.sort = filters.sort
  }

  if (filters.page > 1) {
    query.page = String(filters.page)
  }

  return query
}

export function catalogueApiQuery(routeQuery, locale, perPage = 12) {
  const filters = catalogueFiltersFromQuery(routeQuery)
  const query = {
    locale,
    currency: 'EUR',
    sort: filters.sort,
    page: filters.page,
    per_page: perPage
  }

  if (filters.search) {
    query.search = filters.search
  }

  for (const key of arrayFilterKeys) {
    if (filters[key].length) {
      query[`${key}[]`] = filters[key]
    }
  }

  if (filters.min_price !== '') {
    query.min_price = filters.min_price
  }

  if (filters.max_price !== '') {
    query.max_price = filters.max_price
  }

  if (filters.in_stock) {
    query.in_stock = true
  }

  if (filters.on_sale) {
    query.on_sale = true
  }

  return query
}

export function catalogueFilterCount(filters) {
  let count = arrayFilterKeys.reduce((total, key) => total + filters[key].length, 0)

  if (filters.min_price !== '') count += 1
  if (filters.max_price !== '') count += 1
  if (filters.in_stock) count += 1
  if (filters.on_sale) count += 1

  return count
}
