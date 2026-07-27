import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxtjs/i18n', '@nuxt/eslint'],
  eslint: {
    config: {
      autoInit: false
    }
  },
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    backendHealthUrl: process.env.NUXT_BACKEND_HEALTH_URL || 'http://backend-web/up',
    backendApiUrl: process.env.NUXT_BACKEND_API_URL || 'http://backend-web/api/v1',
    public: {
      siteUrl: process.env.NUXT_PUBLIC_SITE_URL || 'http://localhost:8080',
      apiBase: '/api/v1'
    }
  },
  vite: {
    plugins: [tailwindcss()]
  },
  i18n: {
    strategy: 'prefix_except_default',
    defaultLocale: 'lv',
    detectBrowserLanguage: false,
    langDir: 'locales',
    locales: [
      {
        code: 'lv',
        language: 'lv-LV',
        name: 'Latviešu',
        file: 'lv.json'
      },
      {
        code: 'en',
        language: 'en-GB',
        name: 'English',
        file: 'en.json'
      }
    ]
  }
})
