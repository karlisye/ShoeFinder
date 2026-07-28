export function preferredLocaleRedirect(path, savedLocale) {
  return path === '/' && savedLocale === 'en' ? '/en/' : null
}
