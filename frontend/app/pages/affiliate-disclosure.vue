<script setup>
const { t } = useI18n()
const localePath = useLocalePath()

usePublicSeo({
  title: computed(() => t('meta.affiliateTitle')),
  description: computed(() => t('meta.affiliateDescription')),
  path: '/affiliate-disclosure'
})
</script>

<template>
  <main class="content-page">
    <div class="content-page-inner container">
      <article class="content-page-article">
        <header class="content-page-header">
          <p class="section-eyebrow">{{ t('affiliate.eyebrow') }}</p>
          <h1 class="content-page-title">{{ t('affiliate.title') }}</h1>
          <p class="content-page-intro">{{ t('affiliate.intro') }}</p>
        </header>

        <section class="content-page-section" aria-labelledby="affiliate-links-heading">
          <h2 id="affiliate-links-heading" class="content-page-heading">
            {{ t('affiliate.linksTitle') }}
          </h2>
          <p class="content-page-copy">{{ t('affiliate.linksDescription') }}</p>
        </section>

        <section class="content-page-section" aria-labelledby="affiliate-order-heading">
          <h2 id="affiliate-order-heading" class="content-page-heading">
            {{ t('affiliate.orderTitle') }}
          </h2>
          <p class="content-page-copy">{{ t('affiliate.orderDescription') }}</p>
        </section>

        <section class="content-page-section" aria-labelledby="affiliate-accuracy-heading">
          <h2 id="affiliate-accuracy-heading" class="content-page-heading">
            {{ t('affiliate.accuracyTitle') }}
          </h2>
          <p class="content-page-copy">{{ t('affiliate.accuracyDescription') }}</p>
        </section>

        <section class="content-page-section" aria-labelledby="affiliate-retailer-heading">
          <h2 id="affiliate-retailer-heading" class="content-page-heading">
            {{ t('affiliate.retailerTitle') }}
          </h2>
          <p class="content-page-copy">{{ t('affiliate.retailerDescription') }}</p>
        </section>

        <section class="content-page-section" aria-labelledby="affiliate-contact-heading">
          <h2 id="affiliate-contact-heading" class="content-page-heading">
            {{ t('affiliate.contactTitle') }}
          </h2>
          <p class="content-page-copy">
            {{ t('affiliate.contactDescription') }}
            <NuxtLink :to="localePath('/contact')" class="content-page-link">
              {{ t('affiliate.contactLink') }} </NuxtLink
            >.
          </p>
        </section>
      </article>
    </div>
  </main>
</template>
