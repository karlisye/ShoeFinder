import { sitemapXml } from '~~/app/utils/seo'

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event)
  const slugs = []
  let page = 1

  try {
    while (true) {
      const response = await $fetch('/shoes', {
        baseURL: config.backendApiUrl || 'http://backend-web/api/v1',
        query: {
          locale: 'lv',
          currency: 'EUR',
          sort: 'name',
          page,
          per_page: 48
        }
      })

      slugs.push(...response.data.map((shoe) => shoe.slug))

      if (page >= response.meta.last_page) {
        break
      }

      page += 1
    }
  } catch {
    throw createError({
      statusCode: 503,
      statusMessage: 'Sitemap data is unavailable.'
    })
  }

  setResponseHeaders(event, {
    'Content-Type': 'application/xml; charset=utf-8',
    'Cache-Control': 'public, max-age=0, s-maxage=3600, stale-while-revalidate=86400'
  })

  return sitemapXml(config.public.siteUrl, slugs)
})
