export function useCatalogueApi() {
  const config = useRuntimeConfig()
  const baseURL = import.meta.server
    ? config.backendApiUrl || 'http://backend-web/api/v1'
    : config.public.apiBase || '/api/v1'

  function get(path, options = {}) {
    return $fetch(path, {
      baseURL,
      timeout: 8000,
      ...options
    })
  }

  return { get }
}
