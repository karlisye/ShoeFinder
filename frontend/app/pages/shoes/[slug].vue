<script setup>
import {
  lowestProductPrice,
  orderedOffers,
  selectedVariant,
  validSelectedSize
} from '~/utils/productComparison'
import { listenForCatalogueRefresh } from '~/utils/catalogueRefresh'
import { breadcrumbJsonLd, localizedPath, productJsonLd } from '~/utils/seo'

const config = useRuntimeConfig()
const route = useRoute()
const router = useRouter()
const { locale, t } = useI18n()
const localePath = useLocalePath()
const catalogue = useCatalogueApi()
const { formatMoney } = useMoney()
let stopProductRefresh = () => {}

const slug = computed(() => String(route.params.slug))
const requestQuery = computed(() => ({
  locale: locale.value,
  currency: 'EUR'
}))

const {
  data: response,
  error,
  status,
  refresh
} = await useAsyncData(
  () => `shoe-${slug.value}-${locale.value}`,
  () =>
    catalogue.get(`/shoes/${encodeURIComponent(slug.value)}`, {
      query: requestQuery.value
    }),
  { watch: [slug, locale] }
)

if (error.value?.statusCode === 404) {
  throw createError({
    statusCode: 404,
    statusMessage: 'Not Found',
    fatal: true
  })
}

onMounted(() => {
  stopProductRefresh = listenForCatalogueRefresh(window, refresh)
})

onBeforeUnmount(() => {
  stopProductRefresh()
})

const shoe = computed(() => response.value?.data ?? null)
const variant = computed(() => selectedVariant(shoe.value?.variants ?? [], route.query.colour))
const size = computed(() => validSelectedSize(variant.value, route.query.size))
const offers = computed(() => orderedOffers(variant.value?.listings ?? [], size.value, 'EUR'))
const lowestPrice = computed(() =>
  lowestProductPrice(variant.value ? [variant.value] : [], size.value, 'EUR')
)
const currentPath = computed(() => route.fullPath)
const seoPath = computed(() => `/shoes/${slug.value}`)
const seoTitle = computed(() =>
  shoe.value ? `${shoe.value.name} · ShoeFinder` : t('meta.productFallbackTitle')
)
const seoDescription = computed(
  () => shoe.value?.description || t('meta.productFallbackDescription')
)
const seoImage = computed(
  () =>
    shoe.value?.variants.flatMap((item) => item.images ?? []).find((image) => image.url)?.url ??
    null
)
const seoImageAlt = computed(
  () =>
    shoe.value?.variants.flatMap((item) => item.images ?? []).find((image) => image.url)?.alt ??
    null
)

function updateSelection(colour, selectedSize = null) {
  const query = { ...route.query, colour }

  if (selectedSize) {
    query.size = selectedSize
  } else {
    delete query.size
  }

  return router.push({ path: route.path, query })
}

function selectColour(colour) {
  const nextVariant = selectedVariant(shoe.value?.variants ?? [], colour)
  const nextSize = validSelectedSize(nextVariant, size.value)

  return updateSelection(colour, nextSize)
}

function selectSize(selectedSize) {
  return updateSelection(variant.value.colour.code, selectedSize)
}

usePublicSeo({
  title: seoTitle,
  description: seoDescription,
  path: seoPath,
  type: 'product',
  image: seoImage,
  imageAlt: seoImageAlt,
  noindex: computed(() => Boolean(error.value) || !shoe.value),
  includeAlternates: computed(() => Boolean(shoe.value)),
  schemas: computed(() => {
    if (!shoe.value) {
      return []
    }

    const localizedProductPath = localizedPath(seoPath.value, locale.value)

    return [
      breadcrumbJsonLd(config.public.siteUrl, [
        {
          name: t('nav.home'),
          path: localizedPath('/', locale.value)
        },
        {
          name: t('nav.catalogue'),
          path: localizedPath('/catalogue', locale.value)
        },
        {
          name: shoe.value.name,
          path: localizedProductPath
        }
      ]),
      productJsonLd(shoe.value, config.public.siteUrl, localizedProductPath, 'EUR')
    ]
  })
})
</script>

<template>
  <main class="product-page">
    <div class="product-page-inner container">
      <NuxtLink :to="localePath('/catalogue')" class="product-back-link">
        <svg
          viewBox="0 0 20 20"
          class="product-back-icon"
          stroke="currentColor"
          stroke-width="2"
          aria-hidden="true"
        >
          <path d="m12.5 4.5-5.5 5.5 5.5 5.5" />
        </svg>
        {{ t('productDetail.backToCatalogue') }}
      </NuxtLink>

      <div v-if="status === 'pending' && !shoe" class="product-detail-loading">
        <div class="skeleton product-detail-loading-image" />
        <div class="product-detail-loading-copy">
          <div class="skeleton product-detail-loading-line" />
          <div class="skeleton product-detail-loading-title" />
          <div class="skeleton product-detail-loading-block" />
        </div>
      </div>

      <section v-else-if="error || !shoe" class="product-detail-error" role="alert">
        <h1 class="product-detail-error-title">{{ t('productDetail.errorTitle') }}</h1>
        <p class="product-detail-error-description">{{ t('productDetail.errorDescription') }}</p>
        <button type="button" class="button-primary product-detail-error-action" @click="refresh">
          {{ t('catalogue.retry') }}
        </button>
      </section>

      <template v-else>
        <section class="product-overview">
          <ProductGallery :images="variant?.images ?? []" />

          <div class="product-summary">
            <p class="product-summary-brand">{{ shoe.brand.name }}</p>
            <h1 class="product-summary-title">{{ shoe.name }}</h1>
            <p class="product-summary-category">{{ shoe.category.name }}</p>

            <div class="product-summary-price">
              <p class="product-summary-price-label">
                {{ size ? t('productDetail.lowestForSize', { size }) : t('product.from') }}
              </p>
              <p v-if="lowestPrice" class="product-summary-price-value">
                {{ formatMoney(lowestPrice.amount, lowestPrice.currency) }}
              </p>
              <p v-else class="product-summary-price-missing">
                {{ t('product.priceUnavailable') }}
              </p>
            </div>

            <div v-if="shoe.variants.length > 1" class="product-option-group">
              <p class="product-option-label">
                {{ t('productDetail.colour') }}:
                <strong>{{ variant.colour.name }}</strong>
              </p>
              <div class="product-option-list">
                <button
                  v-for="item in shoe.variants"
                  :key="item.id"
                  type="button"
                  class="product-option-button"
                  :class="{ 'product-option-button-active': item.id === variant.id }"
                  :aria-pressed="item.id === variant.id"
                  @click="selectColour(item.colour.code)"
                >
                  {{ item.colour.name }}
                </button>
              </div>
            </div>

            <div class="product-option-group">
              <div class="product-size-heading">
                <p class="product-option-label">{{ t('productDetail.size') }}</p>
                <button
                  v-if="size"
                  type="button"
                  class="product-size-clear"
                  @click="selectSize(null)"
                >
                  {{ t('productDetail.clearSize') }}
                </button>
              </div>
              <div v-if="variant.available_sizes.length" class="product-size-list">
                <button
                  v-for="item in variant.available_sizes"
                  :key="item.label"
                  type="button"
                  class="product-size-button"
                  :class="{ 'product-size-button-active': item.label === size }"
                  :aria-pressed="item.label === size"
                  @click="selectSize(item.label)"
                >
                  {{ item.label }}
                </button>
              </div>
              <p v-else class="product-size-empty">{{ t('product.noSizes') }}</p>
            </div>

            <dl class="product-facts">
              <div v-if="shoe.manufacturer_style_code" class="product-fact">
                <dt>{{ t('productDetail.productCode') }}</dt>
                <dd>{{ shoe.manufacturer_style_code }}</dd>
              </div>
              <div class="product-fact">
                <dt>{{ t('productDetail.audience') }}</dt>
                <dd>{{ t(`audience.${shoe.audience}`) }}</dd>
              </div>
            </dl>
          </div>
        </section>

        <section class="product-comparison">
          <div class="product-comparison-heading">
            <div>
              <p class="section-eyebrow">{{ t('productDetail.offersEyebrow') }}</p>
              <h2 class="product-comparison-title">{{ t('productDetail.offersTitle') }}</h2>
            </div>
            <p class="product-comparison-count">
              {{ t('productDetail.offerCount', { count: offers.length }) }}
            </p>
          </div>

          <p v-if="!size" class="product-comparison-hint">
            {{ t('productDetail.selectSizeHint') }}
          </p>

          <div v-if="offers.length" class="retailer-offer-list">
            <ProductRetailerOffer
              v-for="offer in offers"
              :key="offer.id"
              :offer="offer"
              :selected-size="size"
              :referrer-path="currentPath"
            />
          </div>
          <div v-else class="product-comparison-empty">
            {{ t('productDetail.noOffers') }}
          </div>
        </section>

        <section v-if="shoe.description" class="product-description">
          <h2 class="product-description-title">{{ t('productDetail.description') }}</h2>
          <p class="product-description-copy">{{ shoe.description }}</p>
        </section>
      </template>
    </div>
  </main>
</template>
