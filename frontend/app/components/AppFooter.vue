<script setup>
const { t } = useI18n()
const localePath = useLocalePath()
</script>

<template>
  <footer class="site-footer">
    <div class="footer-inner container">
      <div class="footer-summary">
        <p class="footer-brand">ShoeFinder</p>
        <p class="footer-description">{{ t('footer.description') }}</p>
      </div>

      <div class="footer-navigation">
        <nav :aria-label="t('footer.browseLabel')" class="footer-group">
          <p class="footer-heading">{{ t('footer.browse') }}</p>
          <div class="footer-link-list">
            <NuxtLink :to="localePath('/')" class="footer-link">
              {{ t('nav.home') }}
            </NuxtLink>
            <NuxtLink :to="localePath('/catalogue')" class="footer-link">
              {{ t('nav.catalogue') }}
            </NuxtLink>
          </div>
        </nav>

        <nav :aria-label="t('footer.informationLabel')" class="footer-group">
          <p class="footer-heading">{{ t('footer.information') }}</p>
          <div class="footer-link-list">
            <NuxtLink :to="localePath('/contact')" class="footer-link">
              {{ t('footer.contact') }}
            </NuxtLink>
            <NuxtLink :to="localePath('/privacy')" class="footer-link">
              {{ t('footer.privacy') }}
            </NuxtLink>
            <NuxtLink :to="localePath('/affiliate-disclosure')" class="footer-link">
              {{ t('footer.affiliateDisclosure') }}
            </NuxtLink>
          </div>
        </nav>
      </div>
    </div>
  </footer>
</template>
