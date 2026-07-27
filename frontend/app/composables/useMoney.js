export function useMoney() {
  const { locale } = useI18n()

  function formatMoney(amount, currency = 'EUR') {
    if (amount === null || amount === undefined || amount === '') {
      return null
    }

    return new Intl.NumberFormat(locale.value === 'lv' ? 'lv-LV' : 'en-GB', {
      style: 'currency',
      currency
    }).format(Number(amount))
  }

  return { formatMoney }
}
