<script setup>
import { errorPageStatusCode, localizedErrorTargets, localizedHomePath } from '~/utils/errorPage'

const props = defineProps({
  error: {
    type: Object,
    required: true
  }
})

const { locale, t } = useI18n()

const statusCode = computed(() => errorPageStatusCode(props.error))
const isNotFound = computed(() => statusCode.value === 404)
const homePath = computed(() => localizedHomePath(locale.value))
const localeTargets = computed(() => localizedErrorTargets(props.error?.url))
const title = computed(() =>
  t(isNotFound.value ? 'errorPage.notFoundMetaTitle' : 'errorPage.serverMetaTitle')
)

useHead(() => ({
  title: title.value,
  htmlAttrs: {
    lang: locale.value
  },
  meta: [
    {
      name: 'robots',
      content: 'noindex, follow'
    }
  ]
}))

function returnHome() {
  return clearError({ redirect: homePath.value })
}
</script>

<template>
  <div class="app-shell">
    <NuxtRouteAnnouncer />
    <AppHeader :locale-targets="localeTargets" />

    <main class="error-page app-main">
      <div class="error-page-content container">
        <p class="error-page-code" aria-hidden="true">{{ statusCode }}</p>
        <p class="error-page-eyebrow">
          {{ t(isNotFound ? 'errorPage.notFoundEyebrow' : 'errorPage.serverEyebrow') }}
        </p>
        <h1 class="error-page-title">
          {{ t(isNotFound ? 'errorPage.notFoundTitle' : 'errorPage.serverTitle') }}
        </h1>
        <p class="error-page-description">
          {{ t(isNotFound ? 'errorPage.notFoundDescription' : 'errorPage.serverDescription') }}
        </p>
        <button type="button" class="button-primary error-page-action" @click="returnHome">
          {{ t('errorPage.homeAction') }}
        </button>
      </div>
    </main>

    <AppFooter />
  </div>
</template>
