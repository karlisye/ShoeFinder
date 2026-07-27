<script setup>
const { locale } = useI18n()

useHead({
  htmlAttrs: {
    lang: locale
  }
})
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <NuxtRouteAnnouncer />
    <AppHeader />
    <NuxtPage class="flex-1" />
    <AppFooter />
  </div>
</template>
