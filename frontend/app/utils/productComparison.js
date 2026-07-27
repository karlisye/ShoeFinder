function amountNumber(value) {
  if (value === null || value === undefined || value === '') {
    return null
  }

  const amount = Number(value)

  return Number.isFinite(amount) ? amount : null
}

function queryValue(value) {
  return Array.isArray(value) ? value[0] : value
}

export function selectedVariant(variants, colour) {
  const selectedColour = queryValue(colour)

  return variants.find((variant) => variant.colour.code === selectedColour) ?? variants[0] ?? null
}

export function validSelectedSize(variant, size) {
  const selectedSize = queryValue(size)

  if (!selectedSize || !variant) {
    return null
  }

  return variant.available_sizes.some((item) => item.label === selectedSize) ? selectedSize : null
}

export function listingOffer(listing, size, currency = 'EUR') {
  const sizeRow = size ? listing.sizes.find((item) => item.label === size) : null
  const available = size ? Boolean(sizeRow?.in_stock) : Boolean(listing.in_stock)
  const price = size ? (available ? sizeRow.effective_price : null) : listing.lowest_price?.amount
  const deliveredTotal = size
    ? available
      ? sizeRow.delivered_total
      : null
    : listing.delivery.delivered_total

  return {
    ...listing,
    available,
    item_price: price,
    delivered_total: deliveredTotal,
    currency_matches: listing.currency === currency,
    qualifies: listing.currency === currency && listing.fresh && available && price !== null
  }
}

export function orderedOffers(listings, size, currency = 'EUR') {
  return listings
    .map((listing) => listingOffer(listing, size, currency))
    .sort((left, right) => {
      const currencyOrder = Number(right.currency_matches) - Number(left.currency_matches)

      if (currencyOrder !== 0) {
        return currencyOrder
      }

      const availabilityOrder = Number(right.available) - Number(left.available)

      if (availabilityOrder !== 0) {
        return availabilityOrder
      }

      const freshnessOrder = Number(right.fresh) - Number(left.fresh)

      if (freshnessOrder !== 0) {
        return freshnessOrder
      }

      const leftAmount = amountNumber(left.item_price)
      const rightAmount = amountNumber(right.item_price)

      if (leftAmount === null || rightAmount === null) {
        if (leftAmount !== rightAmount) {
          return leftAmount === null ? 1 : -1
        }
      } else if (leftAmount !== rightAmount) {
        return leftAmount - rightAmount
      }

      return left.retailer.name.localeCompare(right.retailer.name)
    })
}

export function lowestProductPrice(variants, size, currency = 'EUR') {
  const offers = variants.flatMap((variant) => orderedOffers(variant.listings, size, currency))
  const qualifying = offers
    .filter((offer) => offer.qualifies)
    .sort((left, right) => amountNumber(left.item_price) - amountNumber(right.item_price))[0]

  return qualifying
    ? {
        amount: qualifying.item_price,
        currency: qualifying.currency
      }
    : null
}

export function deliveryText(delivery, locale, formatMoney, currency = 'EUR') {
  const cost =
    delivery.cost === null || delivery.cost === undefined
      ? locale === 'lv'
        ? 'Piegādes cena nav norādīta'
        : 'Delivery cost not provided'
      : Number(delivery.cost) === 0
        ? locale === 'lv'
          ? 'Bezmaksas piegāde'
          : 'Free delivery'
        : locale === 'lv'
          ? `Piegāde ${formatMoney(delivery.cost, currency)}`
          : `Delivery ${formatMoney(delivery.cost, currency)}`

  let timeframe = null

  if (delivery.min_days !== null && delivery.max_days !== null) {
    timeframe =
      delivery.min_days === delivery.max_days
        ? locale === 'lv'
          ? `${delivery.min_days} dienas`
          : `${delivery.min_days} days`
        : locale === 'lv'
          ? `${delivery.min_days} līdz ${delivery.max_days} dienas`
          : `${delivery.min_days} to ${delivery.max_days} days`
  } else if (delivery.min_days !== null) {
    timeframe =
      locale === 'lv' ? `No ${delivery.min_days} dienām` : `From ${delivery.min_days} days`
  } else if (delivery.max_days !== null) {
    timeframe =
      locale === 'lv' ? `Līdz ${delivery.max_days} dienām` : `Up to ${delivery.max_days} days`
  }

  return { cost, timeframe }
}
