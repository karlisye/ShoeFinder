<script setup>
const { t } = useI18n()
const localePath = useLocalePath()
</script>

<template>
  <footer class="site-footer">
    <div class="footer-inner container">
      <div>
        <p class="footer-brand">ShoeFinder</p>
        <p class="footer-description">{{ t('footer.description') }}</p>
      </div>

      <div class="footer-links">
        <NuxtLink :to="localePath('/catalogue')" class="footer-link">
          {{ t('nav.catalogue') }}
        </NuxtLink>
        <a href="/admin" class="footer-link">
          {{ t('footer.admin') }}
        </a>
      </div>
    </div>
  </footer>
</template>
