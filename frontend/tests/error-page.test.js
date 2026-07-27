import assert from 'node:assert/strict'
import test from 'node:test'

import {
  errorPageStatusCode,
  localizedErrorTargets,
  localizedHomePath
} from '../app/utils/errorPage.js'

test('error status accepts valid HTTP errors and falls back to 500', () => {
  assert.equal(errorPageStatusCode({ statusCode: 404 }), 404)
  assert.equal(errorPageStatusCode({ status: '503' }), 503)
  assert.equal(errorPageStatusCode({ statusCode: 'invalid' }), 500)
})

test('error locale targets preserve the missing path and query', () => {
  assert.deepEqual(localizedErrorTargets('/en/missing-page?source=test'), {
    lv: '/missing-page?source=test',
    en: '/en/missing-page?source=test'
  })
  assert.deepEqual(localizedErrorTargets('/missing-page'), {
    lv: '/missing-page',
    en: '/en/missing-page'
  })
})

test('error recovery returns to the active locale homepage', () => {
  assert.equal(localizedHomePath('lv'), '/')
  assert.equal(localizedHomePath('en'), '/en/')
})
