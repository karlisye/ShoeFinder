import { preferredLocaleRedirect } from '~/utils/locale'

export default defineNuxtRouteMiddleware((to) => {
  const savedLocale = useCookie('shoe_finder_locale')
  const redirect = preferredLocaleRedirect(to.path, savedLocale.value)

  if (redirect) {
    return navigateTo(redirect, { redirectCode: 302 })
  }
})
