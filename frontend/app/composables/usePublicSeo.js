import { absoluteUrl, localizedPath, localizedSeoLinks, serializeJsonLd } from '~/utils/seo'

export function usePublicSeo(options) {
  const config = useRuntimeConfig()
  const { locale } = useI18n()
  const siteUrl = config.public.siteUrl
  const pagePath = computed(() => toValue(options.path))
  const links = computed(() => localizedSeoLinks(siteUrl, pagePath.value, locale.value))
  const title = computed(() => toValue(options.title))
  const description = computed(() => toValue(options.description))
  const noindex = computed(() => Boolean(toValue(options.noindex)))
  const image = computed(() => toValue(options.image))
  const imageAlt = computed(() => toValue(options.imageAlt))
  const pageType = computed(() => toValue(options.type) || 'website')
  const schemas = computed(() => (toValue(options.schemas) ?? []).filter(Boolean))
  const includeAlternates = computed(() => toValue(options.includeAlternates) !== false)

  useSeoMeta({
    title: () => title.value,
    description: () => description.value,
    robots: () => (noindex.value ? 'noindex, follow' : 'index, follow'),
    ogTitle: () => title.value,
    ogDescription: () => description.value,
    ogType: () => pageType.value,
    ogUrl: () => links.value.canonical,
    ogSiteName: 'ShoeFinder',
    ogLocale: () => (locale.value === 'en' ? 'en_GB' : 'lv_LV'),
    ogLocaleAlternate: () => (locale.value === 'en' ? 'lv_LV' : 'en_GB'),
    ogImage: () => (image.value ? absoluteUrl(siteUrl, image.value) : undefined),
    ogImageAlt: () => imageAlt.value || undefined,
    twitterCard: () => (image.value ? 'summary_large_image' : 'summary'),
    twitterTitle: () => title.value,
    twitterDescription: () => description.value,
    twitterImage: () => (image.value ? absoluteUrl(siteUrl, image.value) : undefined)
  })

  useHead(() => ({
    link: [
      {
        key: 'canonical',
        rel: 'canonical',
        href: links.value.canonical
      },
      ...(includeAlternates.value
        ? links.value.alternates.map((alternate) => ({
            key: `alternate-${alternate.hreflang}`,
            rel: 'alternate',
            hreflang: alternate.hreflang,
            href: alternate.href
          }))
        : [])
    ],
    script: schemas.value.map((schema, index) => ({
      key: `structured-data-${index}`,
      type: 'application/ld+json',
      innerHTML: serializeJsonLd(schema)
    }))
  }))

  return {
    canonicalUrl: computed(() => links.value.canonical),
    localizedCanonicalPath: computed(() => localizedPath(pagePath.value, locale.value))
  }
}
