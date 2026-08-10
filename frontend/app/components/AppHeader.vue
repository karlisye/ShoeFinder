<script setup>
const props = defineProps({
  localeTargets: {
    type: Object,
    default: null
  }
})

const route = useRoute()
const { locale, t } = useI18n()
const localePath = useLocalePath()
const switchLocalePath = useSwitchLocalePath()
const headerElement = ref(null)
const mobileMenuButton = ref(null)
const mobileMenuOpen = ref(false)
let desktopMediaQuery = null
const localeCookie = useCookie('shoe_finder_locale', {
  maxAge: 60 * 60 * 24 * 365,
  sameSite: 'lax'
})

const navigation = computed(() => [
  { label: t('nav.home'), to: localePath('/') },
  { label: t('nav.catalogue'), to: localePath('/catalogue') }
])

function localeTarget(code) {
  if (props.localeTargets?.[code]) {
    return props.localeTargets[code]
  }

  return {
    path: switchLocalePath(code),
    query: route.query
  }
}

function rememberLocale(code) {
  localeCookie.value = code
}

function closeMobileMenu(restoreFocus = false) {
  if (!mobileMenuOpen.value) return

  mobileMenuOpen.value = false

  if (restoreFocus) {
    nextTick(() => mobileMenuButton.value?.focus())
  }
}

function toggleMobileMenu() {
  mobileMenuOpen.value = !mobileMenuOpen.value
}

function chooseLocale(code) {
  rememberLocale(code)
  closeMobileMenu()
}

function handleDocumentKeydown(event) {
  if (event.key === 'Escape') {
    closeMobileMenu(true)
  }
}

function handleDocumentPointerdown(event) {
  if (mobileMenuOpen.value && !headerElement.value?.contains(event.target)) {
    closeMobileMenu()
  }
}

function handleDesktopChange(event) {
  if (event.matches) {
    closeMobileMenu()
  }
}

watch(
  () => route.fullPath,
  () => closeMobileMenu()
)

onMounted(() => {
  desktopMediaQuery = window.matchMedia('(min-width: 640px)')
  desktopMediaQuery.addEventListener('change', handleDesktopChange)
  document.addEventListener('keydown', handleDocumentKeydown)
  document.addEventListener('pointerdown', handleDocumentPointerdown)
})

onBeforeUnmount(() => {
  desktopMediaQuery?.removeEventListener('change', handleDesktopChange)
  document.removeEventListener('keydown', handleDocumentKeydown)
  document.removeEventListener('pointerdown', handleDocumentPointerdown)
})
</script>

<template>
  <header ref="headerElement" class="site-header">
    <div class="header-inner container">
      <NuxtLink :to="localePath('/')" class="brand-link" :aria-label="t('nav.homeLabel')">
        <span class="brand-mark" aria-hidden="true" />
        <span class="brand-name">ShoeFinder</span>
      </NuxtLink>

      <div class="header-actions">
        <nav :aria-label="t('nav.primary')" class="primary-nav">
          <NuxtLink
            v-for="(item, index) in navigation"
            :key="item.to"
            :to="item.to"
            class="primary-nav-link"
            :class="{ 'primary-nav-home': index === 0 }"
            active-class="primary-nav-link-active"
          >
            {{ item.label }}
          </NuxtLink>
        </nav>

        <nav :aria-label="t('language.label')" class="language-switcher">
          <NuxtLink
            v-for="code in ['lv', 'en']"
            :key="code"
            :to="localeTarget(code)"
            class="language-link"
            :class="locale === code ? 'language-link-active' : 'language-link-inactive'"
            :lang="code"
            @click="rememberLocale(code)"
          >
            {{ code.toUpperCase() }}
          </NuxtLink>
        </nav>
      </div>

      <button
        ref="mobileMenuButton"
        type="button"
        class="mobile-menu-toggle"
        :aria-expanded="mobileMenuOpen"
        aria-controls="mobile-navigation"
        :aria-label="mobileMenuOpen ? t('nav.closeMenu') : t('nav.openMenu')"
        @click="toggleMobileMenu"
      >
        <svg
          v-if="mobileMenuOpen"
          viewBox="0 0 24 24"
          class="mobile-menu-icon"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          aria-hidden="true"
        >
          <path d="M6 6l12 12M18 6 6 18" />
        </svg>
        <svg
          v-else
          viewBox="0 0 24 24"
          class="mobile-menu-icon"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          aria-hidden="true"
        >
          <path d="M4 7h16M4 12h16M4 17h16" />
        </svg>
      </button>
    </div>

    <div v-if="mobileMenuOpen" id="mobile-navigation" class="mobile-menu">
      <div class="mobile-menu-inner container">
        <nav :aria-label="t('nav.primary')" class="mobile-primary-nav">
          <NuxtLink
            v-for="item in navigation"
            :key="item.to"
            :to="item.to"
            class="mobile-primary-nav-link"
            active-class="mobile-primary-nav-link-active"
            @click="closeMobileMenu()"
          >
            {{ item.label }}
          </NuxtLink>
        </nav>

        <nav :aria-label="t('language.label')" class="language-switcher mobile-language-switcher">
          <NuxtLink
            v-for="code in ['lv', 'en']"
            :key="code"
            :to="localeTarget(code)"
            class="language-link"
            :class="locale === code ? 'language-link-active' : 'language-link-inactive'"
            :lang="code"
            @click="chooseLocale(code)"
          >
            {{ code.toUpperCase() }}
          </NuxtLink>
        </nav>
      </div>
    </div>
  </header>
</template>
