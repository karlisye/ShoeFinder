import assert from 'node:assert/strict'
import { readFile, stat } from 'node:fs/promises'
import test from 'node:test'

const homepage = await readFile(new URL('../app/pages/index.vue', import.meta.url), 'utf8')
const stylesheet = await readFile(new URL('../app/assets/css/main.css', import.meta.url), 'utf8')
const latvian = JSON.parse(
  await readFile(new URL('../i18n/locales/lv.json', import.meta.url), 'utf8')
)
const english = JSON.parse(
  await readFile(new URL('../i18n/locales/en.json', import.meta.url), 'utf8')
)
const comparisonImage = await stat(
  new URL('../public/images/home-comparison-shoes.webp', import.meta.url)
)
const comparisonImageContents = await readFile(
  new URL('../public/images/home-comparison-shoes.webp', import.meta.url)
)

test('homepage offers a localized snap-scroll route to the catalogue', () => {
  assert.match(homepage, /class: 'home-scroll-snap'/)
  assert.match(homepage, /class="home-about home-panel"/)
  assert.match(homepage, /localePath\('\/catalogue'\)/)
  assert.equal(latvian.home.visitCatalogue, 'Apskatīt katalogu')
  assert.equal(english.home.visitCatalogue, 'Visit catalogue')
})

test('homepage uses an optimized responsive comparison image', () => {
  assert.match(homepage, /src="\/images\/home-comparison-shoes\.webp"/)
  assert.match(homepage, /home-about-media-mobile/)
  assert.match(homepage, /home-about-media-desktop/)
  assert.ok(comparisonImage.size > 10_000)
  assert.ok(comparisonImage.size < 100_000)
  assert.ok(comparisonImageContents.includes(Buffer.from('ALPH')))
})

test('homepage slows wheel navigation between snap panels', () => {
  assert.match(homepage, /HOME_SCROLL_DURATION_MS = 500/)
  assert.match(homepage, /addEventListener\('wheel', handleHomeWheel, \{ passive: false \}\)/)
  assert.match(homepage, /event\.preventDefault\(\)/)
  assert.match(
    stylesheet,
    /html\.home-scroll-snap\.home-scroll-animating\s*{[^}]*scroll-snap-type:\s*none;/s
  )
  assert.match(stylesheet, /scroll-snap-stop:\s*normal;/)
})

test('homepage scroll snapping respects reduced-motion preferences', () => {
  assert.match(stylesheet, /html\.home-scroll-snap\s*{[^}]*scroll-snap-type:\s*y mandatory;/s)
  assert.match(
    stylesheet,
    /@media \(prefers-reduced-motion: reduce\)[\s\S]*html\.home-scroll-snap\s*{[^}]*scroll-snap-type:\s*none;/
  )
})
