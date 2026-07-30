export function listenForCatalogueRefresh(target, refresh) {
  const handleFocus = () => refresh()

  target.addEventListener('focus', handleFocus)

  return () => target.removeEventListener('focus', handleFocus)
}
