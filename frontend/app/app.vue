<script setup>
const { locale } = useI18n()

useHead({
  htmlAttrs: {
    lang: locale
  }
})
</script>

<template>
  <div class="app-shell">
    <NuxtRouteAnnouncer />
    <AppHeader />
    <NuxtPage class="app-main" />
    <AppFooter />
  </div>
</template>
