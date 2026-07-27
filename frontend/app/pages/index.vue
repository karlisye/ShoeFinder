<script setup>
const { t } = useI18n()
const localePath = useLocalePath()
const router = useRouter()
const search = ref('')
const popularSearches = ['New Balance 530', 'Adidas Samba', 'Nike Air Force 1']

function catalogueLocation(searchValue) {
  return {
    path: localePath('/catalogue'),
    query: { search: searchValue }
  }
}

function searchCatalogue() {
  const searchValue = search.value.trim()

  router.push(
    searchValue
      ? catalogueLocation(searchValue)
      : {
          path: localePath('/catalogue')
        }
  )
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
  <main class="home-page">
    <section class="home-hero container">
      <div class="home-content">
        <p class="home-eyebrow">
          {{ t('home.eyebrow') }}
        </p>

        <h1 class="home-title">
          <span class="home-title-line">{{ t('home.titleFirst') }}</span>
          <span class="home-title-line">{{ t('home.titleSecond') }}</span>
        </h1>

        <p class="home-description">
          {{ t('home.description') }}
        </p>

        <form class="home-search" role="search" @submit.prevent="searchCatalogue">
          <label class="visually-hidden" for="home-search">{{ t('home.searchLabel') }}</label>
          <div class="home-search-field">
            <input
              id="home-search"
              v-model="search"
              type="search"
              class="home-search-input"
              :placeholder="t('home.searchPlaceholder')"
            />
            <svg
              viewBox="0 0 24 24"
              class="home-search-icon"
              stroke="currentColor"
              stroke-width="2"
              aria-hidden="true"
            >
              <circle cx="11" cy="11" r="6.5" />
              <path d="m16 16 4 4" />
            </svg>
          </div>
          <button type="submit" class="button-primary home-search-button">
            {{ t('home.searchAction') }}
          </button>
        </form>

        <div class="popular-searches">
          <span>{{ t('home.popular') }}</span>
          <template v-for="(item, index) in popularSearches" :key="item">
            <NuxtLink :to="catalogueLocation(item)" class="popular-search-link">
              {{ item }}
            </NuxtLink>
            <span v-if="index < popularSearches.length - 1" aria-hidden="true">·</span>
          </template>
        </div>
      </div>
    </section>
  </main>
</template>
