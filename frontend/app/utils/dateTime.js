const localeTags = {
  en: 'en-GB',
  lv: 'lv-LV'
}

export function formatListingUpdatedAt(value, locale = 'lv') {
  if (!value) {
    return null
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return null
  }

  return new Intl.DateTimeFormat(localeTags[locale] ?? localeTags.lv, {
    day: 'numeric',
    hour: '2-digit',
    hourCycle: 'h23',
    minute: '2-digit',
    month: 'short',
    timeZone: 'Europe/Riga',
    year: 'numeric'
  }).format(date)
}
