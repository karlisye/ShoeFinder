import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import test from 'node:test'

const footer = await readFile(new URL('../app/components/AppFooter.vue', import.meta.url), 'utf8')

test('public footer groups navigation without exposing the admin route', () => {
  assert.match(footer, /footer\.browse/)
  assert.match(footer, /footer\.information/)
  assert.match(footer, /localePath\('\/affiliate-disclosure'\)/)
  assert.doesNotMatch(footer, /href="\/admin/)
})
