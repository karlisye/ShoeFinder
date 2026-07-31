<script setup>
const { t } = useI18n()
const localePath = useLocalePath()

usePublicSeo({
  title: computed(() => t('meta.privacyTitle')),
  description: computed(() => t('meta.privacyDescription')),
  path: '/privacy'
})
</script>

<template>
  <main class="content-page">
    <div class="content-page-inner container">
      <article class="content-page-article">
        <header class="content-page-header">
          <p class="section-eyebrow">{{ t('privacy.eyebrow') }}</p>
          <h1 class="content-page-title">{{ t('privacy.title') }}</h1>
          <p class="content-page-intro">{{ t('privacy.intro') }}</p>
          <p class="content-page-updated">{{ t('privacy.updated') }}</p>
        </header>

        <p class="content-page-notice">{{ t('privacy.prototypeNotice') }}</p>

        <section class="content-page-section" aria-labelledby="privacy-data-heading">
          <h2 id="privacy-data-heading" class="content-page-heading">
            {{ t('privacy.dataTitle') }}
          </h2>
          <ul class="content-page-list">
            <li>{{ t('privacy.languageData') }}</li>
            <li>{{ t('privacy.clickData') }}</li>
            <li>{{ t('privacy.technicalData') }}</li>
            <li>{{ t('privacy.messageData') }}</li>
          </ul>
        </section>

        <section class="content-page-section" aria-labelledby="privacy-purpose-heading">
          <h2 id="privacy-purpose-heading" class="content-page-heading">
            {{ t('privacy.purposeTitle') }}
          </h2>
          <ul class="content-page-list">
            <li>{{ t('privacy.languagePurpose') }}</li>
            <li>{{ t('privacy.redirectPurpose') }}</li>
            <li>{{ t('privacy.analyticsPurpose') }}</li>
            <li>{{ t('privacy.securityPurpose') }}</li>
          </ul>
        </section>

        <section class="content-page-section" aria-labelledby="privacy-sharing-heading">
          <h2 id="privacy-sharing-heading" class="content-page-heading">
            {{ t('privacy.sharingTitle') }}
          </h2>
          <p class="content-page-copy">{{ t('privacy.sharingDescription') }}</p>
        </section>

        <section class="content-page-section" aria-labelledby="privacy-retention-heading">
          <h2 id="privacy-retention-heading" class="content-page-heading">
            {{ t('privacy.retentionTitle') }}
          </h2>
          <p class="content-page-copy">{{ t('privacy.retentionDescription') }}</p>
        </section>

        <section class="content-page-section" aria-labelledby="privacy-choices-heading">
          <h2 id="privacy-choices-heading" class="content-page-heading">
            {{ t('privacy.choicesTitle') }}
          </h2>
          <p class="content-page-copy">{{ t('privacy.choicesDescription') }}</p>
        </section>

        <section class="content-page-section" aria-labelledby="privacy-contact-heading">
          <h2 id="privacy-contact-heading" class="content-page-heading">
            {{ t('privacy.contactTitle') }}
          </h2>
          <p class="content-page-copy">
            {{ t('privacy.contactDescription') }}
            <NuxtLink :to="localePath('/contact')" class="content-page-link">
              {{ t('privacy.contactLink') }} </NuxtLink
            >.
          </p>
        </section>
      </article>
    </div>
  </main>
</template>
