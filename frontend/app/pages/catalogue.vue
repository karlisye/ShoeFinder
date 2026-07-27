<script setup>
import {
  catalogueApiQuery,
  catalogueFilterCount,
  catalogueFiltersFromQuery,
  catalogueRouteQuery
} from '~/utils/catalogueQuery'

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

useHead({
  title: () => t('meta.catalogueTitle'),
  meta: [
    {
      name: 'description',
      content: () => t('meta.catalogueDescription')
    }
  ]
})
</script>

<template>
  <main>
    <section class="border-b border-secondary-light/15 bg-surface">
      <div class="site-container py-9 sm:py-12">
        <p class="section-eyebrow">{{ t('catalogue.eyebrow') }}</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-primary-dark sm:text-4xl">
          {{ t('catalogue.title') }}
        </h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-secondary sm:text-base">
          {{ t('catalogue.description') }}
        </p>

        <form class="mt-6 flex max-w-2xl gap-2" role="search" @submit.prevent="submitSearch">
          <label class="sr-only" for="catalogue-search">
            {{ t('catalogue.searchLabel') }}
          </label>
          <div class="relative min-w-0 flex-1">
            <svg
              viewBox="0 0 24 24"
              class="pointer-events-none absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 fill-none text-secondary"
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
              class="form-input h-12 bg-elevated pr-4 pl-12"
              :placeholder="t('catalogue.searchPlaceholder')"
            />
          </div>
          <button type="submit" class="button-primary min-h-12 px-5">
            {{ t('catalogue.searchAction') }}
          </button>
        </form>
      </div>
    </section>

    <section class="site-container py-8 sm:py-10">
      <div
        class="flex flex-col items-stretch gap-4 border-b border-secondary-light/15 pb-5 min-[360px]:flex-row min-[360px]:items-center min-[360px]:justify-between"
      >
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="button-secondary lg:hidden"
            :aria-expanded="filterDrawerOpen"
            aria-controls="mobile-filter-drawer"
            @click="filterDrawerOpen = true"
          >
            <svg
              viewBox="0 0 20 20"
              class="h-4 w-4 fill-none"
              stroke="currentColor"
              stroke-width="1.8"
              aria-hidden="true"
            >
              <path d="M3 5h14M5.5 10h9M8 15h4" />
            </svg>
            {{ t('filters.title') }}
            <span
              v-if="activeFilterCount"
              class="grid h-5 min-w-5 place-items-center rounded-full bg-primary px-1 text-[11px] text-elevated"
            >
              {{ activeFilterCount }}
            </span>
          </button>

          <p class="text-sm font-semibold text-secondary" aria-live="polite">
            {{ t('catalogue.resultCount', { count: meta.total }) }}
          </p>

          <button
            v-if="activeFilterCount"
            type="button"
            class="hidden text-sm font-semibold text-info-dark underline-offset-4 hover:underline sm:block"
            @click="clearAppliedFilters"
          >
            {{ t('filters.clearApplied') }}
          </button>
        </div>

        <label
          class="flex w-full items-center gap-2 text-sm font-semibold text-secondary min-[360px]:w-auto"
        >
          <span class="hidden sm:inline">{{ t('catalogue.sortLabel') }}</span>
          <select
            class="form-select flex-1 min-[360px]:flex-none"
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

      <div class="mt-7 grid gap-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside class="hidden lg:block" :aria-label="t('filters.title')">
          <div class="sticky top-24 rounded-2xl border border-secondary-light/15 bg-elevated p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
              <h2 class="text-lg font-bold text-primary-dark">{{ t('filters.title') }}</h2>
              <span v-if="activeFilterCount" class="text-xs font-bold text-secondary">
                {{ activeFilterCount }}
              </span>
            </div>

            <div v-if="filterStatus === 'pending'" class="space-y-3" aria-hidden="true">
              <div v-for="index in 5" :key="index" class="skeleton h-10 rounded-lg" />
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

        <div class="min-w-0">
          <CatalogueLoadingGrid v-if="isInitialLoading" />

          <div
            v-else-if="shoeError || filterError"
            class="rounded-2xl border border-danger/20 bg-danger-light px-6 py-10 text-center"
            role="alert"
          >
            <h2 class="font-bold text-danger-dark">{{ t('catalogue.errorTitle') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-danger-dark">
              {{ shoeError?.data?.error?.message || t('catalogue.errorDescription') }}
            </p>
            <button type="button" class="button-primary mt-5" @click="retry">
              {{ t('catalogue.retry') }}
            </button>
          </div>

          <div v-else-if="shoes.length" :class="{ 'opacity-60': shoeStatus === 'pending' }">
            <div class="catalogue-grid">
              <CatalogueProductCard v-for="shoe in shoes" :key="shoe.id" :shoe="shoe" />
            </div>

            <CataloguePaginationNav
              :current-page="meta.current_page"
              :last-page="meta.last_page"
              @change="changePage"
            />
          </div>

          <div
            v-else
            class="rounded-2xl border border-dashed border-secondary-light/35 bg-surface px-6 py-14 text-center"
          >
            <svg
              viewBox="0 0 48 48"
              class="mx-auto h-12 w-12 fill-none text-secondary-light"
              stroke="currentColor"
              stroke-width="1.8"
              aria-hidden="true"
            >
              <circle cx="21" cy="21" r="12" />
              <path d="m30 30 8 8M16 18h10M16 24h7" />
            </svg>
            <h2 class="mt-5 font-bold text-primary-dark">{{ t('catalogue.emptyTitle') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-secondary">
              {{ t('catalogue.emptyDescription') }}
            </p>
            <button
              v-if="activeFilterCount"
              type="button"
              class="button-primary mt-5"
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
        class="fixed inset-0 z-50 lg:hidden"
        role="dialog"
        aria-modal="true"
        :aria-label="t('filters.title')"
        @keydown.esc="filterDrawerOpen = false"
      >
        <div
          class="absolute inset-0 bg-primary-dark/55"
          aria-hidden="true"
          @click="filterDrawerOpen = false"
        />
        <div
          class="absolute inset-y-0 right-0 flex w-full max-w-sm flex-col bg-elevated shadow-2xl"
        >
          <div
            class="flex items-center justify-between border-b border-secondary-light/15 px-5 py-4"
          >
            <h2 class="text-lg font-bold text-primary-dark">{{ t('filters.title') }}</h2>
            <button
              type="button"
              class="grid h-10 w-10 place-items-center rounded-xl text-secondary hover:bg-surface hover:text-primary-dark"
              :aria-label="t('filters.close')"
              autofocus
              @click="filterDrawerOpen = false"
            >
              <svg
                viewBox="0 0 20 20"
                class="h-5 w-5 fill-none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
              >
                <path d="m4 4 12 12M16 4 4 16" />
              </svg>
            </button>
          </div>
          <div class="flex-1 overflow-y-auto p-5">
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
