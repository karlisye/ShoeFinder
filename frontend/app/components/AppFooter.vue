<script setup>
const { t } = useI18n()
const localePath = useLocalePath()
</script>

<template>
  <footer class="mt-18 border-t border-secondary-light/15 bg-surface">
    <div
      class="site-container flex flex-col gap-5 py-8 text-sm text-secondary sm:flex-row sm:items-center sm:justify-between"
    >
      <div>
        <p class="font-bold text-primary-dark">ShoeFinder</p>
        <p class="mt-1">{{ t('footer.description') }}</p>
      </div>

      <div class="flex items-center gap-5">
        <NuxtLink :to="localePath('/catalogue')" class="font-semibold hover:text-primary-dark">
          {{ t('nav.catalogue') }}
        </NuxtLink>
        <a href="/admin" class="font-semibold hover:text-primary-dark">
          {{ t('footer.admin') }}
        </a>
      </div>
    </div>
  </footer>
</template>
