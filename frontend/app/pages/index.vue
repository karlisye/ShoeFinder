<script setup>
import { organizationJsonLd, websiteJsonLd } from '~/utils/seo'

const config = useRuntimeConfig()
const { locale, t } = useI18n()
const localePath = useLocalePath()
const router = useRouter()
const search = ref('')
const popularSearches = ['New Balance 530', 'Adidas Samba', 'Nike Air Force 1']
const HOME_SCROLL_DURATION_MS = 500

let scrollAnimationFrame = null
let wheelScrollActive = false
let reducedMotionQuery = null

useHead({
  htmlAttrs: {
    class: 'home-scroll-snap'
  }
})

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

function stopWheelScroll() {
  if (scrollAnimationFrame !== null) {
    cancelAnimationFrame(scrollAnimationFrame)
    scrollAnimationFrame = null
  }

  wheelScrollActive = false
  document.documentElement.classList.remove('home-scroll-animating')
}

function animateToPanel(destination) {
  const start = window.scrollY
  const distance = destination - start

  if (Math.abs(distance) < 1) return

  const startedAt = performance.now()
  wheelScrollActive = true
  document.documentElement.classList.add('home-scroll-animating')

  function moveFrame(now) {
    const progress = Math.min((now - startedAt) / HOME_SCROLL_DURATION_MS, 1)
    const easedProgress = (1 - Math.cos(Math.PI * progress)) / 2

    window.scrollTo(0, start + distance * easedProgress)

    if (progress < 1) {
      scrollAnimationFrame = requestAnimationFrame(moveFrame)
      return
    }

    scrollAnimationFrame = null
    wheelScrollActive = false
    document.documentElement.classList.remove('home-scroll-animating')
  }

  scrollAnimationFrame = requestAnimationFrame(moveFrame)
}

function handleHomeWheel(event) {
  if (
    event.ctrlKey ||
    event.deltaY === 0 ||
    Math.abs(event.deltaX) > Math.abs(event.deltaY) ||
    reducedMotionQuery?.matches
  ) {
    return
  }

  if (wheelScrollActive) {
    event.preventDefault()
    return
  }

  const headerHeight = document.querySelector('.site-header')?.offsetHeight ?? 0
  const currentPosition = window.scrollY
  const direction = Math.sign(event.deltaY)
  const tolerance = 16
  const panelPositions = Array.from(document.querySelectorAll('.home-panel')).map(
    (panel) => panel.getBoundingClientRect().top + currentPosition - headerHeight
  )
  const destination =
    direction > 0
      ? panelPositions.find((position) => position > currentPosition + tolerance)
      : panelPositions
          .slice()
          .reverse()
          .find((position) => position < currentPosition - tolerance)

  if (destination === undefined) return

  event.preventDefault()
  animateToPanel(Math.max(0, destination))
}

onMounted(() => {
  reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
  window.addEventListener('wheel', handleHomeWheel, { passive: false })
})

onBeforeUnmount(() => {
  window.removeEventListener('wheel', handleHomeWheel)
  stopWheelScroll()
})

usePublicSeo({
  title: computed(() => t('meta.homeTitle')),
  description: computed(() => t('meta.homeDescription')),
  path: '/',
  schemas: computed(() => [
    organizationJsonLd(config.public.siteUrl),
    websiteJsonLd(config.public.siteUrl, t('meta.homeDescription'), locale.value)
  ])
})
</script>

<template>
  <main class="home-page">
    <section class="home-hero home-panel container">
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

    <section class="home-about home-panel" aria-labelledby="home-about-title">
      <div class="home-about-content container">
        <div class="home-about-copy">
          <p class="home-about-eyebrow">
            {{ t('home.aboutEyebrow') }}
          </p>

          <h2 id="home-about-title" class="home-about-title">
            {{ t('home.aboutTitle') }}
          </h2>

          <figure class="home-about-media home-about-media-mobile" aria-hidden="true">
            <img
              src="/images/home-comparison-shoes.webp"
              alt=""
              width="1536"
              height="1024"
              loading="lazy"
              decoding="async"
              class="home-about-image"
            />
          </figure>

          <p class="home-about-description">
            {{ t('home.aboutDescription') }}
          </p>

          <NuxtLink :to="localePath('/catalogue')" class="button-primary home-about-action">
            {{ t('home.visitCatalogue') }}
          </NuxtLink>
        </div>

        <figure class="home-about-media home-about-media-desktop" aria-hidden="true">
          <img
            src="/images/home-comparison-shoes.webp"
            alt=""
            width="1536"
            height="1024"
            loading="lazy"
            decoding="async"
            class="home-about-image"
          />
        </figure>
      </div>
    </section>
  </main>
</template>
