export function errorPageStatusCode(error) {
  const statusCode = Number(error?.statusCode ?? error?.status ?? 500)

  return Number.isInteger(statusCode) && statusCode >= 400 && statusCode <= 599 ? statusCode : 500
}

export function localizedErrorTargets(requestedUrl) {
  const [pathname, search] = String(requestedUrl ?? '/').split('?')
  const unprefixedPath = pathname.replace(/^\/en(?=\/|$)/, '') || '/'
  const suffix = search ? `?${search}` : ''

  return {
    lv: `${unprefixedPath}${suffix}`,
    en: `${unprefixedPath === '/' ? '/en/' : `/en${unprefixedPath}`}${suffix}`
  }
}

export function localizedHomePath(locale) {
  return locale === 'en' ? '/en/' : '/'
}
