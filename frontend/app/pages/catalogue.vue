<script setup>
import {
  catalogueApiQuery,
  catalogueFilterCount,
  catalogueFiltersFromQuery,
  catalogueRouteQuery
} from '~/utils/catalogueQuery'
import { breadcrumbJsonLd, hasRouteQuery, localizedPath } from '~/utils/seo'

const config = useRuntimeConfig()
const route = useRoute()
const router = useRouter()
const { locale, t } = useI18n()
const catalogue = useCatalogueApi()
const filterDrawerOpen = ref(false)

const activeFilters = computed(() => catalogueFiltersFromQuery(route.query))
const draftFilters = ref({ ...activeFilters.value })
const search = ref(activeFilters.value.search)
const activeFilterCount = computed(() => catalogueFilterCount(activeFilters.value))
const requestQuery = computed(() => catalogueApiQuery(route.query, locale.value))

watch(
  () => route.query,
  () => {
    draftFilters.value = {
      ...activeFilters.value,
      brand: [...activeFilters.value.brand],
      category: [...activeFilters.value.category],
      audience: [...activeFilters.value.audience],
      colour: [...activeFilters.value.colour],
      size: [...activeFilters.value.size],
      retailer: [...activeFilters.value.retailer]
    }
    search.value = activeFilters.value.search
  },
  { deep: true }
)

watch(filterDrawerOpen, (open) => {
  if (import.meta.client) {
    document.body.style.overflow = open ? 'hidden' : ''
  }
})

onBeforeUnmount(() => {
  if (import.meta.client) {
    document.body.style.overflow = ''
  }
})

const {
  data: filterResponse,
  error: filterError,
  status: filterStatus,
  refresh: refreshFilters
} = await useAsyncData(
  'catalogue-filter-options',
  () =>
    catalogue.get('/catalog-filters', {
      query: {
        locale: locale.value,
        currency: 'EUR'
      }
    }),
  { watch: [locale] }
)

const {
  data: shoeResponse,
  error: shoeError,
  status: shoeStatus,
  refresh: refreshShoes
} = await useAsyncData(
  'catalogue-shoes',
  () =>
    catalogue.get('/shoes', {
      query: requestQuery.value
    }),
  { watch: [requestQuery] }
)

const filterOptions = computed(() => ({
  brands: filterResponse.value?.data?.brands ?? [],
  categories: filterResponse.value?.data?.categories ?? [],
  audiences: filterResponse.value?.data?.audiences ?? [],
  colours: filterResponse.value?.data?.colours ?? [],
  sizes: filterResponse.value?.data?.sizes ?? [],
  retailers: filterResponse.value?.data?.retailers ?? [],
  price_bounds: filterResponse.value?.data?.price_bounds ?? null
}))
const shoes = computed(() => shoeResponse.value?.data ?? [])
const meta = computed(
  () =>
    shoeResponse.value?.meta ?? {
      current_page: activeFilters.value.page,
      last_page: 1,
      total: 0
    }
)
const isInitialLoading = computed(() => shoeStatus.value === 'pending' && !shoeResponse.value)

function updateRoute(filters) {
  return router.push({
    path: route.path,
    query: catalogueRouteQuery(filters)
  })
}

async function applyFilters() {
  await updateRoute({
    ...draftFilters.value,
    search: activeFilters.value.search,
    sort: activeFilters.value.sort,
    page: 1
  })
  filterDrawerOpen.value = false
}

function resetFilters() {
  draftFilters.value = {
    ...catalogueFiltersFromQuery({}),
    search: activeFilters.value.search,
    sort: activeFilters.value.sort
  }
}

function clearAppliedFilters() {
  return updateRoute({
    ...catalogueFiltersFromQuery({}),
    search: activeFilters.value.search,
    sort: activeFilters.value.sort
  })
}

function submitSearch() {
  return updateRoute({
    ...activeFilters.value,
    search: search.value,
    page: 1
  })
}

function changeSort(event) {
  return updateRoute({
    ...activeFilters.value,
    sort: event.target.value,
    page: 1
  })
}

async function changePage(page) {
  await updateRoute({
    ...activeFilters.value,
    page
  })

  if (import.meta.client) {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

function retry() {
  refreshFilters()
  refreshShoes()
}

usePublicSeo({
  title: computed(() => t('meta.catalogueTitle')),
  description: computed(() => t('meta.catalogueDescription')),
  path: '/catalogue',
  noindex: computed(() => hasRouteQuery(route.query)),
  schemas: computed(() => [
    breadcrumbJsonLd(config.public.siteUrl, [
      {
        name: t('nav.home'),
        path: localizedPath('/', locale.value)
      },
      {
        name: t('nav.catalogue'),
        path: localizedPath('/catalogue', locale.value)
      }
    ])
  ])
})
</script>

<template>
  <main class="catalogue-page">
    <section class="catalogue-hero">
      <div class="catalogue-hero-inner container">
        <p class="section-eyebrow">{{ t('catalogue.eyebrow') }}</p>
        <h1 class="catalogue-title">
          {{ t('catalogue.title') }}
        </h1>
        <p class="catalogue-description">
          {{ t('catalogue.description') }}
        </p>

        <form class="catalogue-search" role="search" @submit.prevent="submitSearch">
          <label class="visually-hidden" for="catalogue-search">
            {{ t('catalogue.searchLabel') }}
          </label>
          <div class="catalogue-search-field">
            <svg
              viewBox="0 0 24 24"
              class="catalogue-search-icon"
              stroke="currentColor"
              stroke-width="2"
              aria-hidden="true"
            >
              <circle cx="11" cy="11" r="6.5" />
              <path d="m16 16 4 4" />
            </svg>
            <input
              id="catalogue-search"
              v-model="search"
              type="search"
              class="catalogue-search-input"
              :placeholder="t('catalogue.searchPlaceholder')"
            />
          </div>
          <button type="submit" class="button-primary catalogue-search-button">
            {{ t('catalogue.searchAction') }}
          </button>
        </form>
      </div>
    </section>

    <section class="catalogue-body container">
      <div class="catalogue-toolbar">
        <div class="catalogue-toolbar-main">
          <button
            type="button"
            class="button-secondary mobile-filter-button"
            :aria-expanded="filterDrawerOpen"
            aria-controls="mobile-filter-drawer"
            @click="filterDrawerOpen = true"
          >
            <svg
              viewBox="0 0 20 20"
              class="filter-button-icon"
              stroke="currentColor"
              stroke-width="1.8"
              aria-hidden="true"
            >
              <path d="M3 5h14M5.5 10h9M8 15h4" />
            </svg>
            {{ t('filters.title') }}
            <span v-if="activeFilterCount" class="filter-count-badge">
              {{ activeFilterCount }}
            </span>
          </button>

          <p class="catalogue-result-count" aria-live="polite">
            {{ t('catalogue.resultCount', { count: meta.total }) }}
          </p>

          <button
            v-if="activeFilterCount"
            type="button"
            class="clear-filters-link"
            @click="clearAppliedFilters"
          >
            {{ t('filters.clearApplied') }}
          </button>
        </div>

        <label class="sort-control">
          <span class="sort-label">{{ t('catalogue.sortLabel') }}</span>
          <select
            class="sort-select"
            :value="activeFilters.sort"
            :aria-label="t('catalogue.sortLabel')"
            @change="changeSort"
          >
            <option value="newest">{{ t('catalogue.sort.newest') }}</option>
            <option value="price_asc">{{ t('catalogue.sort.priceAsc') }}</option>
            <option value="price_desc">{{ t('catalogue.sort.priceDesc') }}</option>
            <option value="name">{{ t('catalogue.sort.name') }}</option>
          </select>
        </label>
      </div>

      <div class="catalogue-layout">
        <aside class="desktop-filters" :aria-label="t('filters.title')">
          <div class="filter-panel">
            <div class="filter-panel-header">
              <h2 class="filter-title">{{ t('filters.title') }}</h2>
              <span v-if="activeFilterCount" class="filter-total">
                {{ activeFilterCount }}
              </span>
            </div>

            <div v-if="filterStatus === 'pending'" class="filter-loading" aria-hidden="true">
              <div v-for="index in 5" :key="index" class="skeleton filter-loading-item" />
            </div>
            <CatalogueFilterPanel
              v-else
              v-model:filters="draftFilters"
              id-prefix="desktop"
              :options="filterOptions"
              @apply="applyFilters"
              @reset="resetFilters"
            />
          </div>
        </aside>

        <div class="catalogue-results">
          <CatalogueLoadingGrid v-if="isInitialLoading" />

          <div v-else-if="shoeError || filterError" class="catalogue-error" role="alert">
            <h2 class="catalogue-error-title">{{ t('catalogue.errorTitle') }}</h2>
            <p class="catalogue-error-description">
              {{ shoeError?.data?.error?.message || t('catalogue.errorDescription') }}
            </p>
            <button type="button" class="button-primary catalogue-state-action" @click="retry">
              {{ t('catalogue.retry') }}
            </button>
          </div>

          <div
            v-else-if="shoes.length"
            class="catalogue-results-content"
            :class="{ 'catalogue-results-updating': shoeStatus === 'pending' }"
          >
            <div class="catalogue-grid">
              <CatalogueProductCard v-for="shoe in shoes" :key="shoe.id" :shoe="shoe" />
            </div>

            <CataloguePaginationNav
              :current-page="meta.current_page"
              :last-page="meta.last_page"
              @change="changePage"
            />
          </div>

          <div v-else class="catalogue-empty">
            <svg
              viewBox="0 0 48 48"
              class="catalogue-empty-icon"
              stroke="currentColor"
              stroke-width="1.8"
              aria-hidden="true"
            >
              <circle cx="21" cy="21" r="12" />
              <path d="m30 30 8 8M16 18h10M16 24h7" />
            </svg>
            <h2 class="catalogue-empty-title">{{ t('catalogue.emptyTitle') }}</h2>
            <p class="catalogue-empty-description">
              {{ t('catalogue.emptyDescription') }}
            </p>
            <button
              v-if="activeFilterCount"
              type="button"
              class="button-primary catalogue-state-action"
              @click="clearAppliedFilters"
            >
              {{ t('filters.clearApplied') }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <Teleport to="body">
      <div
        v-if="filterDrawerOpen"
        id="mobile-filter-drawer"
        class="filter-drawer"
        role="dialog"
        aria-modal="true"
        :aria-label="t('filters.title')"
        @keydown.esc="filterDrawerOpen = false"
      >
        <div class="filter-drawer-backdrop" aria-hidden="true" @click="filterDrawerOpen = false" />
        <div class="filter-drawer-panel">
          <div class="filter-drawer-header">
            <h2 class="filter-title">{{ t('filters.title') }}</h2>
            <button
              type="button"
              class="filter-drawer-close"
              :aria-label="t('filters.close')"
              autofocus
              @click="filterDrawerOpen = false"
            >
              <svg
                viewBox="0 0 20 20"
                class="filter-drawer-close-icon"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
              >
                <path d="m4 4 12 12M16 4 4 16" />
              </svg>
            </button>
          </div>
          <div class="filter-drawer-body">
            <CatalogueFilterPanel
              v-model:filters="draftFilters"
              id-prefix="mobile"
              :options="filterOptions"
              @apply="applyFilters"
              @reset="resetFilters"
            />
          </div>
        </div>
      </div>
    </Teleport>
  </main>
</template>
