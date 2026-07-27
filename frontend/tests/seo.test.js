import assert from 'node:assert/strict'
import test from 'node:test'

import {
  absoluteUrl,
  breadcrumbJsonLd,
  hasRouteQuery,
  localizedPath,
  localizedSeoLinks,
  productJsonLd,
  robotsText,
  serializeJsonLd,
  sitemapXml
} from '../app/utils/seo.js'

test('localized canonical and alternate links use the configured origin', () => {
  const links = localizedSeoLinks('https://shoes.example/path', '/shoes/pace-runner?size=42', 'en')

  assert.equal(links.canonical, 'https://shoes.example/en/shoes/pace-runner')
  assert.deepEqual(links.alternates, [
    {
      hreflang: 'lv',
      href: 'https://shoes.example/shoes/pace-runner'
    },
    {
      hreflang: 'en',
      href: 'https://shoes.example/en/shoes/pace-runner'
    },
    {
      hreflang: 'x-default',
      href: 'https://shoes.example/shoes/pace-runner'
    }
  ])
  assert.equal(localizedPath('/', 'en'), '/en/')
  assert.equal(
    absoluteUrl('https://shoes.example/', '/catalogue'),
    'https://shoes.example/catalogue'
  )
})

test('catalogue query detection distinguishes clean and filtered routes', () => {
  assert.equal(hasRouteQuery({}), false)
  assert.equal(hasRouteQuery({ search: 'Runner' }), true)
  assert.equal(hasRouteQuery({ brand: ['nike'] }), true)
})

test('product structured data excludes stale and unavailable offers', () => {
  const shoe = {
    name: 'Pace Runner 2',
    description: 'Running shoe.',
    manufacturer_style_code: 'PR2',
    brand: { name: 'Nordstep' },
    category: { name: 'Running shoes' },
    variants: [
      {
        colour: { name: 'Black' },
        images: [{ url: '/storage/pace.jpg' }],
        listings: [
          {
            fresh: true,
            in_stock: true,
            currency: 'EUR',
            lowest_price: { amount: '89.00' },
            retailer: { name: 'Shop A' }
          },
          {
            fresh: false,
            in_stock: true,
            currency: 'EUR',
            lowest_price: { amount: '60.00' },
            retailer: { name: 'Stale Shop' }
          },
          {
            fresh: true,
            in_stock: false,
            currency: 'EUR',
            lowest_price: { amount: '70.00' },
            retailer: { name: 'Empty Shop' }
          },
          {
            fresh: true,
            in_stock: true,
            currency: 'USD',
            lowest_price: { amount: '50.00' },
            retailer: { name: 'USD Shop' }
          }
        ]
      },
      {
        colour: { name: 'White' },
        images: [],
        listings: [
          {
            fresh: true,
            in_stock: true,
            currency: 'EUR',
            lowest_price: { amount: '99.00' },
            retailer: { name: 'Shop B' }
          }
        ]
      }
    ]
  }

  const data = productJsonLd(shoe, 'https://shoes.example', '/en/shoes/pace-runner', 'EUR')

  assert.equal(data['@type'], 'Product')
  assert.equal(data.url, 'https://shoes.example/en/shoes/pace-runner')
  assert.deepEqual(data.image, ['https://shoes.example/storage/pace.jpg'])
  assert.equal(data.color, 'Black, White')
  assert.equal(data.offers.lowPrice, '89.00')
  assert.equal(data.offers.highPrice, '99.00')
  assert.equal(data.offers.offerCount, 2)
  assert.deepEqual(
    data.offers.offers.map((offer) => offer.seller.name),
    ['Shop A', 'Shop B']
  )
})

test('breadcrumb and JSON-LD serialization keep public values safe', () => {
  const breadcrumb = breadcrumbJsonLd('https://shoes.example', [
    { name: 'Home', path: '/en/' },
    { name: 'Shoe', path: '/en/shoes/shoe' }
  ])

  assert.equal(breadcrumb.itemListElement[1].position, 2)
  assert.equal(breadcrumb.itemListElement[1].item, 'https://shoes.example/en/shoes/shoe')
  assert.equal(serializeJsonLd({ name: '</script><script>' }).includes('</script>'), false)
})

test('sitemap contains only clean bilingual public routes', () => {
  const xml = sitemapXml('https://shoes.example', ['z-shoe', 'a-shoe', 'a-shoe'])

  assert.match(xml, /<loc>https:\/\/shoes\.example\/catalogue<\/loc>/)
  assert.match(xml, /<loc>https:\/\/shoes\.example\/en\/catalogue<\/loc>/)
  assert.match(xml, /<loc>https:\/\/shoes\.example\/shoes\/a-shoe<\/loc>/)
  assert.match(xml, /hreflang="x-default"/)
  assert.doesNotMatch(xml, /\/en\/en\//)
  assert.doesNotMatch(xml, /<loc>[^<]*\?/)
  assert.equal((xml.match(/<url>/g) ?? []).length, 8)
})

test('robots allows public pages and identifies private route groups', () => {
  const text = robotsText('https://shoes.example')

  assert.match(text, /^User-agent: \*/m)
  assert.match(text, /^Allow: \/$/m)
  assert.match(text, /^Disallow: \/admin$/m)
  assert.match(text, /^Disallow: \/api$/m)
  assert.match(text, /^Disallow: \/go$/m)
  assert.match(text, /^Sitemap: https:\/\/shoes\.example\/sitemap\.xml$/m)
})
