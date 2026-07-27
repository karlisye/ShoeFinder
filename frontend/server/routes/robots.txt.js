import { robotsText } from '~~/app/utils/seo'

export default defineEventHandler((event) => {
  const config = useRuntimeConfig(event)

  setResponseHeaders(event, {
    'Content-Type': 'text/plain; charset=utf-8',
    'Cache-Control': 'public, max-age=0, s-maxage=3600'
  })

  return robotsText(config.public.siteUrl)
})
