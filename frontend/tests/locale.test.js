import assert from 'node:assert/strict'
import test from 'node:test'

import { preferredLocaleRedirect } from '../app/utils/locale.js'

test('Latvian stays the default locale without a saved English preference', () => {
  assert.equal(preferredLocaleRedirect('/', null), null)
  assert.equal(preferredLocaleRedirect('/', 'lv'), null)
  assert.equal(preferredLocaleRedirect('/', 'invalid'), null)
})

test('a saved English preference redirects only the unprefixed homepage', () => {
  assert.equal(preferredLocaleRedirect('/', 'en'), '/en/')
  assert.equal(preferredLocaleRedirect('/catalogue', 'en'), null)
  assert.equal(preferredLocaleRedirect('/en/', 'en'), null)
})
