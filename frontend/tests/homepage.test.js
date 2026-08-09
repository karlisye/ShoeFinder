import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import test from 'node:test'

const homepage = await readFile(new URL('../app/pages/index.vue', import.meta.url), 'utf8')
const stylesheet = await readFile(new URL('../app/assets/css/main.css', import.meta.url), 'utf8')
const latvian = JSON.parse(
  await readFile(new URL('../i18n/locales/lv.json', import.meta.url), 'utf8')
)
const english = JSON.parse(
  await readFile(new URL('../i18n/locales/en.json', import.meta.url), 'utf8')
)

test('homepage offers a localized snap-scroll route to the catalogue', () => {
  assert.match(homepage, /class: 'home-scroll-snap'/)
  assert.match(homepage, /class="home-about home-panel"/)
  assert.match(homepage, /localePath\('\/catalogue'\)/)
  assert.equal(latvian.home.visitCatalogue, 'Apskatīt katalogu')
  assert.equal(english.home.visitCatalogue, 'Visit catalogue')
})

test('homepage scroll snapping respects reduced-motion preferences', () => {
  assert.match(stylesheet, /html\.home-scroll-snap\s*{[^}]*scroll-snap-type:\s*y mandatory;/s)
  assert.match(
    stylesheet,
    /@media \(prefers-reduced-motion: reduce\)[\s\S]*html\.home-scroll-snap\s*{[^}]*scroll-snap-type:\s*none;/
  )
})
