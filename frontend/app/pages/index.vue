<script setup>
const { locale, t } = useI18n()
const localePath = useLocalePath()
const router = useRouter()
const catalogue = useCatalogueApi()
const search = ref('')

const { data: featured, error: featuredError } = await useAsyncData(
  'homepage-catalogue',
  () =>
    catalogue.get('/shoes', {
      query: {
        locale: locale.value,
        currency: 'EUR',
        sort: 'newest',
        per_page: 4
      }
    }),
  { watch: [locale] }
)

function searchCatalogue() {
  const query = search.value.trim() ? { search: search.value.trim() } : {}

  router.push({
    path: localePath('/catalogue'),
    query
  })
}

useHead({
  title: () => t('meta.homeTitle'),
  meta: [
    {
      name: 'description',
      content: () => t('meta.homeDescription')
    }
  ]
})
</script>

<template>
  <main>
    <section class="site-container py-8 sm:py-12 lg:py-16">
      <div class="hero-grid overflow-hidden rounded-[2rem] bg-primary-dark text-elevated">
        <div class="relative z-10 px-6 py-12 sm:px-10 sm:py-16 lg:px-14 lg:py-20">
          <p
            class="inline-flex rounded-full border border-elevated/15 bg-elevated/8 px-3 py-1.5 text-xs font-bold tracking-[0.14em] text-success-light uppercase"
          >
            {{ t('home.eyebrow') }}
          </p>
          <h1
            class="mt-6 max-w-3xl text-4xl leading-[1.05] font-bold tracking-[-0.035em] text-balance sm:text-5xl lg:text-6xl"
          >
            {{ t('home.title') }}
          </h1>
          <p class="mt-6 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">
            {{ t('home.description') }}
          </p>

          <form
            class="mt-8 flex max-w-2xl flex-col gap-3 rounded-2xl bg-elevated p-2 sm:flex-row"
            role="search"
            @submit.prevent="searchCatalogue"
          >
            <label class="sr-only" for="home-search">{{ t('home.searchLabel') }}</label>
            <div class="relative flex-1">
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
                id="home-search"
                v-model="search"
                type="search"
                class="h-12 w-full rounded-xl bg-page pr-4 pl-12 text-primary-dark placeholder:text-secondary-light"
                :placeholder="t('home.searchPlaceholder')"
              />
            </div>
            <button type="submit" class="button-primary min-h-12 sm:px-6">
              {{ t('home.searchAction') }}
            </button>
          </form>
        </div>

        <div class="relative hidden min-h-full lg:block" aria-hidden="true">
          <div
            class="absolute inset-0 bg-[radial-gradient(circle_at_center,#475569_0,transparent_68%)]"
          />
          <div
            class="absolute top-1/2 left-1/2 h-72 w-72 -translate-x-1/2 -translate-y-1/2 rounded-full border border-elevated/10"
          />
          <div
            class="absolute top-1/2 left-1/2 h-52 w-52 -translate-x-1/2 -translate-y-1/2 rounded-full bg-success/15"
          />
          <svg
            viewBox="0 0 320 220"
            class="absolute top-1/2 left-1/2 w-[82%] -translate-x-1/2 -translate-y-1/2 -rotate-6 fill-none text-elevated drop-shadow-2xl"
            stroke="currentColor"
            stroke-width="6"
          >
            <path
              d="M34 128c52-2 90-29 114-81l32 35c21 23 49 38 80 43l13 2v35c-63 15-136 16-217 3-13-2-22-13-22-26v-11Z"
            />
            <path d="m148 47-27-27m65 65 26-26M45 184h228" />
          </svg>
          <div
            class="absolute right-9 bottom-9 rounded-2xl border border-elevated/10 bg-primary/80 p-4 backdrop-blur"
          >
            <p class="text-xs font-semibold text-slate-300">{{ t('home.priceExample') }}</p>
            <p class="mt-1 text-2xl font-bold">89,99 €</p>
          </div>
        </div>
      </div>
    </section>

    <section class="site-container py-10 sm:py-14">
      <div class="max-w-2xl">
        <p class="section-eyebrow">{{ t('home.howEyebrow') }}</p>
        <h2 class="section-title">{{ t('home.howTitle') }}</h2>
      </div>

      <div class="mt-8 grid gap-4 md:grid-cols-3">
        <article
          v-for="(step, index) in ['search', 'filter', 'compare']"
          :key="step"
          class="rounded-2xl border border-secondary-light/15 bg-elevated p-6"
        >
          <span
            class="grid h-9 w-9 place-items-center rounded-xl bg-surface text-sm font-bold text-primary"
          >
            {{ index + 1 }}
          </span>
          <h3 class="mt-5 text-lg font-bold text-primary-dark">
            {{ t(`home.steps.${step}.title`) }}
          </h3>
          <p class="mt-2 text-sm leading-6 text-secondary">
            {{ t(`home.steps.${step}.description`) }}
          </p>
        </article>
      </div>
    </section>

    <section class="site-container py-10 sm:py-14">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="section-eyebrow">{{ t('home.latestEyebrow') }}</p>
          <h2 class="section-title">{{ t('home.latestTitle') }}</h2>
        </div>
        <NuxtLink :to="localePath('/catalogue')" class="button-secondary self-start">
          {{ t('home.viewCatalogue') }}
        </NuxtLink>
      </div>

      <div v-if="featured?.data?.length" class="catalogue-grid mt-8">
        <CatalogueProductCard v-for="shoe in featured.data" :key="shoe.id" :shoe="shoe" />
      </div>

      <div
        v-else
        class="mt-8 rounded-2xl border border-dashed border-secondary-light/30 bg-surface px-6 py-10 text-center"
      >
        <h3 class="font-bold text-primary-dark">
          {{ featuredError ? t('home.latestError') : t('home.latestEmpty') }}
        </h3>
        <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-secondary">
          {{ featuredError ? t('home.latestErrorHint') : t('home.latestEmptyHint') }}
        </p>
        <NuxtLink :to="localePath('/catalogue')" class="button-primary mt-5 inline-flex">
          {{ t('home.openCatalogue') }}
        </NuxtLink>
      </div>
    </section>
  </main>
</template>
