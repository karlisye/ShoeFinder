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
        <NuxtLink :to="localePath('/contact')" class="footer-link">
          {{ t('footer.contact') }}
        </NuxtLink>
        <NuxtLink :to="localePath('/privacy')" class="footer-link">
          {{ t('footer.privacy') }}
        </NuxtLink>
        <NuxtLink :to="localePath('/affiliate-disclosure')" class="footer-link">
          {{ t('footer.affiliateDisclosure') }}
        </NuxtLink>
        <a href="/admin" class="footer-link">
          {{ t('footer.admin') }}
        </a>
      </div>
    </div>
  </footer>
</template>
