<script setup>
const config = useRuntimeConfig()
const { t } = useI18n()

const contactEmail = computed(() => String(config.public.contactEmail || '').trim())
const contactHref = computed(() => (contactEmail.value ? `mailto:${contactEmail.value}` : null))

usePublicSeo({
  title: computed(() => t('meta.contactTitle')),
  description: computed(() => t('meta.contactDescription')),
  path: '/contact'
})
</script>

<template>
  <main class="content-page">
    <div class="content-page-inner container">
      <article class="content-page-article">
        <header class="content-page-header">
          <p class="section-eyebrow">{{ t('contact.eyebrow') }}</p>
          <h1 class="content-page-title">{{ t('contact.title') }}</h1>
          <p class="content-page-intro">{{ t('contact.intro') }}</p>
        </header>

        <section class="content-page-section" aria-labelledby="contact-email-heading">
          <h2 id="contact-email-heading" class="content-page-heading">
            {{ t('contact.emailTitle') }}
          </h2>
          <p v-if="contactEmail" class="content-page-copy">
            {{ t('contact.emailIntro') }}
            <a :href="contactHref" class="content-page-link content-email-link">
              {{ contactEmail }}
            </a>
          </p>
          <p v-else class="content-page-copy">{{ t('contact.emailUnavailable') }}</p>
        </section>

        <section class="content-page-section" aria-labelledby="contact-details-heading">
          <h2 id="contact-details-heading" class="content-page-heading">
            {{ t('contact.detailsTitle') }}
          </h2>
          <p class="content-page-copy">{{ t('contact.detailsDescription') }}</p>
        </section>

        <section class="content-page-section" aria-labelledby="contact-orders-heading">
          <h2 id="contact-orders-heading" class="content-page-heading">
            {{ t('contact.ordersTitle') }}
          </h2>
          <p class="content-page-copy">{{ t('contact.ordersDescription') }}</p>
        </section>
      </article>
    </div>
  </main>
</template>
