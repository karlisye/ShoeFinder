<script setup>
const route = useRoute()
const { locale, t } = useI18n()
const localePath = useLocalePath()
const switchLocalePath = useSwitchLocalePath()
const localeCookie = useCookie('shoe_finder_locale', {
  maxAge: 60 * 60 * 24 * 365,
  sameSite: 'lax'
})

const navigation = computed(() => [
  { label: t('nav.home'), to: localePath('/') },
  { label: t('nav.catalogue'), to: localePath('/catalogue') }
])

function localeTarget(code) {
  return {
    path: switchLocalePath(code),
    query: route.query
  }
}

function rememberLocale(code) {
  localeCookie.value = code
}
</script>

<template>
  <header class="site-header">
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
    </div>
  </header>
</template>
