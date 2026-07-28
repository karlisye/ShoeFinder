<script setup>
const { locale, t } = useI18n()

useHead({
  htmlAttrs: {
    lang: locale
  }
})
</script>

<template>
  <div class="app-shell">
    <NuxtRouteAnnouncer />
    <a href="#main-content" class="skip-link">{{ t('accessibility.skipToContent') }}</a>
    <AppHeader />
    <NuxtPage id="main-content" class="app-main" />
    <AppFooter />
  </div>
</template>
