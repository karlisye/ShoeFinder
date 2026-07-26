<script setup>
const { locale, t } = useI18n()
const switchLocalePath = useSwitchLocalePath()
const runtimeConfig = useRuntimeConfig()
const localeCookie = useCookie('shoe_finder_locale', {
  maxAge: 60 * 60 * 24 * 365,
  sameSite: 'lax'
})

const {
  data: health,
  error,
  status,
  refresh
} = await useAsyncData('stack-health', () =>
  $fetch(runtimeConfig.backendHealthUrl, { timeout: 5000 })
)

const backendReady = computed(
  () => health.value?.status === 'ok' && health.value?.database === 'connected'
)

function rememberLocale(value) {
  localeCookie.value = value
}

useHead({
  title: () => t('meta.title'),
  meta: [
    {
      name: 'description',
      content: () => t('meta.description')
    }
  ],
  htmlAttrs: {
    lang: locale
  }
})
</script>

<template>
  <main class="mx-auto flex min-h-screen max-w-5xl items-center px-5 py-12 sm:px-8">
    <section
      class="w-full overflow-hidden rounded-3xl border border-secondary-light/20 bg-elevated shadow-xl shadow-primary-dark/5"
    >
      <div class="border-b border-secondary-light/15 bg-surface px-6 py-5 sm:px-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <p class="text-sm font-semibold tracking-[0.18em] text-secondary uppercase">ShoeFinder</p>

          <nav :aria-label="t('language.label')" class="flex rounded-xl bg-page p-1">
            <NuxtLink
              v-for="code in ['lv', 'en']"
              :key="code"
              :to="switchLocalePath(code)"
              class="rounded-lg px-3 py-2 text-sm font-semibold transition-colors"
              :class="
                locale === code ? 'bg-primary text-elevated' : 'text-secondary hover:bg-surface'
              "
              :lang="code"
              @click="rememberLocale(code)"
            >
              {{ code.toUpperCase() }}
            </NuxtLink>
          </nav>
        </div>
      </div>

      <div class="px-6 py-10 sm:px-10 sm:py-14">
        <p class="mb-3 font-semibold text-info-dark">{{ t('eyebrow') }}</p>
        <h1 class="max-w-3xl text-3xl font-bold tracking-tight text-primary-dark sm:text-5xl">
          {{ t('title') }}
        </h1>
        <p class="mt-5 max-w-2xl text-base leading-7 text-secondary sm:text-lg">
          {{ t('description') }}
        </p>

        <div class="mt-10 grid gap-4 sm:grid-cols-3">
          <article class="rounded-2xl border border-secondary-light/20 bg-page p-5">
            <p class="text-sm font-medium text-secondary">{{ t('status.frontend') }}</p>
            <p class="mt-2 flex items-center gap-2 font-semibold text-success-dark">
              <span class="h-2.5 w-2.5 rounded-full bg-success" aria-hidden="true" />
              {{ t('status.ready') }}
            </p>
          </article>

          <article class="rounded-2xl border border-secondary-light/20 bg-page p-5">
            <p class="text-sm font-medium text-secondary">{{ t('status.locale') }}</p>
            <p class="mt-2 font-semibold text-primary-dark">
              {{ locale === 'lv' ? 'Latviešu' : 'English' }}
            </p>
          </article>

          <article class="rounded-2xl border border-secondary-light/20 bg-page p-5">
            <p class="text-sm font-medium text-secondary">{{ t('status.backend') }}</p>
            <p
              v-if="backendReady"
              class="mt-2 flex items-center gap-2 font-semibold text-success-dark"
            >
              <span class="h-2.5 w-2.5 rounded-full bg-success" aria-hidden="true" />
              {{ t('status.connected') }}
            </p>
            <p v-else-if="status === 'pending'" class="mt-2 font-semibold text-info-dark">
              {{ t('status.checking') }}
            </p>
            <div v-else>
              <p class="mt-2 font-semibold text-danger-dark">{{ t('status.unavailable') }}</p>
              <button
                type="button"
                class="mt-3 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-elevated hover:bg-primary-dark"
                @click="refresh"
              >
                {{ t('status.retry') }}
              </button>
            </div>
          </article>
        </div>

        <p v-if="error" class="mt-5 text-sm text-danger-dark">
          {{ t('status.backendHint') }}
        </p>
      </div>
    </section>
  </main>
</template>
