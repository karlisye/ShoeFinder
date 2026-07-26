export default defineNuxtRouteMiddleware((to) => {
  const savedLocale = useCookie('shoe_finder_locale')

  if (to.path === '/' && savedLocale.value === 'en') {
    return navigateTo('/en/', { redirectCode: 302 })
  }
})
