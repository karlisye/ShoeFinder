const FALLBACK_SITE_URL = 'http://localhost:8080'

export function normalizeSiteUrl(value) {
  try {
    const url = new URL(value || FALLBACK_SITE_URL)

    if (!['http:', 'https:'].includes(url.protocol)) {
      return FALLBACK_SITE_URL
    }

    return url.origin
  } catch {
    return FALLBACK_SITE_URL
  }
}

export function absoluteUrl(siteUrl, path) {
  if (/^https?:\/\//i.test(path ?? '')) {
    return path
  }

  const normalizedPath = String(path || '/')

  return new URL(
    normalizedPath.startsWith('/') ? normalizedPath : `/${normalizedPath}`,
    `${normalizeSiteUrl(siteUrl)}/`
  ).toString()
}

export function localizedPath(path, locale) {
  const cleanPath = String(path || '/').split(/[?#]/, 1)[0]
  const normalizedPath = cleanPath.startsWith('/') ? cleanPath : `/${cleanPath}`

  if (locale !== 'en') {
    return normalizedPath
  }

  return normalizedPath === '/' ? '/en/' : `/en${normalizedPath}`
}

export function localizedSeoLinks(siteUrl, path, activeLocale) {
  const lv = absoluteUrl(siteUrl, localizedPath(path, 'lv'))
  const en = absoluteUrl(siteUrl, localizedPath(path, 'en'))

  return {
    canonical: activeLocale === 'en' ? en : lv,
    alternates: [
      { hreflang: 'lv', href: lv },
      { hreflang: 'en', href: en },
      { hreflang: 'x-default', href: lv }
    ]
  }
}

export function hasRouteQuery(query) {
  return Object.keys(query ?? {}).some((key) => {
    const value = query[key]

    return Array.isArray(value) ? value.length > 0 : value !== undefined && value !== null
  })
}

export function organizationJsonLd(siteUrl) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: 'ShoeFinder',
    url: absoluteUrl(siteUrl, '/')
  }
}

export function websiteJsonLd(siteUrl, description, locale) {
  return {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: 'ShoeFinder',
    url: absoluteUrl(siteUrl, localizedPath('/', locale)),
    description,
    inLanguage: locale === 'en' ? 'en-GB' : 'lv-LV'
  }
}

export function breadcrumbJsonLd(siteUrl, items) {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      name: item.name,
      item: absoluteUrl(siteUrl, item.path)
    }))
  }
}

function qualifyingOffers(shoe, siteUrl, canonicalPath, currency) {
  return (shoe?.variants ?? [])
    .flatMap((variant) => variant.listings ?? [])
    .filter(
      (listing) =>
        listing.fresh &&
        listing.in_stock &&
        listing.currency === currency &&
        listing.lowest_price?.amount !== null &&
        listing.lowest_price?.amount !== undefined
    )
    .map((listing) => ({
      '@type': 'Offer',
      price: listing.lowest_price.amount,
      priceCurrency: listing.currency,
      availability: 'https://schema.org/InStock',
      url: absoluteUrl(siteUrl, canonicalPath),
      seller: {
        '@type': 'Organization',
        name: listing.retailer.name
      }
    }))
}

export function productJsonLd(shoe, siteUrl, canonicalPath, currency = 'EUR') {
  if (!shoe) {
    return null
  }

  const offers = qualifyingOffers(shoe, siteUrl, canonicalPath, currency)
  const prices = offers.map((offer) => Number(offer.price)).filter(Number.isFinite)
  const images = [
    ...new Set(
      shoe.variants
        .flatMap((variant) => variant.images ?? [])
        .map((image) => image.url)
        .filter(Boolean)
        .map((url) => absoluteUrl(siteUrl, url))
    )
  ]
  const colours = [...new Set(shoe.variants.map((variant) => variant.colour?.name).filter(Boolean))]
  const product = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: shoe.name,
    url: absoluteUrl(siteUrl, canonicalPath),
    brand: {
      '@type': 'Brand',
      name: shoe.brand.name
    },
    category: shoe.category.name
  }

  if (shoe.description) {
    product.description = shoe.description
  }

  if (shoe.manufacturer_style_code) {
    product.sku = shoe.manufacturer_style_code
  }

  if (images.length) {
    product.image = images
  }

  if (colours.length) {
    product.color = colours.join(', ')
  }

  if (offers.length) {
    product.offers = {
      '@type': 'AggregateOffer',
      priceCurrency: currency,
      lowPrice: Math.min(...prices).toFixed(2),
      highPrice: Math.max(...prices).toFixed(2),
      offerCount: offers.length,
      offers
    }
  }

  return product
}

export function serializeJsonLd(value) {
  return JSON.stringify(value).replaceAll('<', '\\u003C')
}

function escapeXml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&apos;')
}

function sitemapUrl(siteUrl, path, locale) {
  const links = localizedSeoLinks(siteUrl, path, locale)

  return [
    '  <url>',
    `    <loc>${escapeXml(links.canonical)}</loc>`,
    ...links.alternates.map(
      (alternate) =>
        `    <xhtml:link rel="alternate" hreflang="${alternate.hreflang}" href="${escapeXml(alternate.href)}" />`
    ),
    '  </url>'
  ].join('\n')
}

export function sitemapXml(siteUrl, slugs) {
  const paths = ['/', '/catalogue']

  for (const slug of [...new Set(slugs)].sort()) {
    paths.push(`/shoes/${slug}`)
  }

  const entries = paths.flatMap((path) => [
    sitemapUrl(siteUrl, path, 'lv'),
    sitemapUrl(siteUrl, path, 'en')
  ])

  return [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">',
    ...entries,
    '</urlset>',
    ''
  ].join('\n')
}

export function robotsText(siteUrl) {
  return [
    'User-agent: *',
    'Allow: /',
    'Disallow: /admin',
    'Disallow: /api',
    'Disallow: /go',
    'Disallow: /livewire',
    '',
    `Sitemap: ${absoluteUrl(siteUrl, '/sitemap.xml')}`,
    ''
  ].join('\n')
}
