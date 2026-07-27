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
  <header class="sticky top-0 z-40 border-b border-secondary-light/15 bg-page/95 backdrop-blur">
    <div class="site-container flex h-17 items-center justify-between gap-5">
      <NuxtLink
        :to="localePath('/')"
        class="group flex items-center gap-2.5 rounded-md font-bold tracking-tight text-primary-dark"
        :aria-label="t('nav.homeLabel')"
      >
        <span
          class="grid h-9 w-9 place-items-center rounded-xl bg-primary text-elevated transition-transform group-hover:-rotate-3"
          aria-hidden="true"
        >
          <svg viewBox="0 0 32 32" class="h-5 w-5 fill-none" stroke="currentColor" stroke-width="2">
            <path
              d="M5 19.5c4.8-.2 8.3-2.7 10.5-7.5l3 3.3c1.9 2.1 4.5 3.5 7.3 4l1.2.2v3.2c-5.8 1.4-12.5 1.5-20 .3a2.4 2.4 0 0 1-2-2.4v-1.1Z"
            />
            <path d="M15.5 12 13 9.5M19 15.7l2.4-2.4" />
          </svg>
        </span>
        <span class="hidden text-lg min-[360px]:inline">ShoeFinder</span>
      </NuxtLink>

      <div class="flex items-center gap-2 sm:gap-5">
        <nav :aria-label="t('nav.primary')" class="flex items-center gap-1">
          <NuxtLink
            v-for="(item, index) in navigation"
            :key="item.to"
            :to="item.to"
            class="rounded-lg px-3 py-2 text-sm font-semibold text-secondary transition-colors hover:bg-surface hover:text-primary-dark"
            :class="{ 'hidden sm:block': index === 0 }"
            active-class="bg-surface text-primary-dark"
          >
            {{ item.label }}
          </NuxtLink>
        </nav>

        <nav
          :aria-label="t('language.label')"
          class="flex rounded-xl border border-secondary-light/20 bg-elevated p-1"
        >
          <NuxtLink
            v-for="code in ['lv', 'en']"
            :key="code"
            :to="localeTarget(code)"
            class="rounded-lg px-2.5 py-1.5 text-xs font-bold tracking-wide transition-colors"
            :class="
              locale === code
                ? 'bg-primary text-elevated'
                : 'text-secondary hover:bg-surface hover:text-primary-dark'
            "
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
