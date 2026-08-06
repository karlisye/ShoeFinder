import assert from 'node:assert/strict'
import test from 'node:test'

import { formatListingUpdatedAt } from '../app/utils/dateTime.js'

test('listing update times use an absolute localized Riga date and time', () => {
  const value = '2026-07-27T11:00:00+00:00'

  assert.equal(formatListingUpdatedAt(value, 'en'), '27 Jul 2026, 14:00')
  assert.equal(formatListingUpdatedAt(value, 'lv'), '2026. g. 27. jūl. 14:00')
})

test('missing or invalid listing update times stay hidden', () => {
  assert.equal(formatListingUpdatedAt(null, 'en'), null)
  assert.equal(formatListingUpdatedAt('not-a-date', 'lv'), null)
})
