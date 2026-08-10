import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import test from 'node:test'

const header = await readFile(new URL('../app/components/AppHeader.vue', import.meta.url), 'utf8')
const stylesheet = await readFile(new URL('../app/assets/css/main.css', import.meta.url), 'utf8')
const latvian = JSON.parse(
  await readFile(new URL('../i18n/locales/lv.json', import.meta.url), 'utf8')
)
const english = JSON.parse(
  await readFile(new URL('../i18n/locales/en.json', import.meta.url), 'utf8')
)

test('small-screen header exposes an accessible navigation disclosure', () => {
  assert.match(header, /class="mobile-menu-toggle"/)
  assert.match(header, /:aria-expanded="mobileMenuOpen"/)
  assert.match(header, /aria-controls="mobile-navigation"/)
  assert.match(header, /id="mobile-navigation"/)
  assert.match(header, /event\.key === 'Escape'/)
  assert.equal(latvian.nav.openMenu, 'Atvērt izvēlni')
  assert.equal(latvian.nav.closeMenu, 'Aizvērt izvēlni')
  assert.equal(english.nav.openMenu, 'Open menu')
  assert.equal(english.nav.closeMenu, 'Close menu')
})

test('mobile navigation yields to desktop navigation at the shared breakpoint', () => {
  assert.match(stylesheet, /\.header-actions\s*{[^}]*@apply hidden/s)
  assert.match(
    stylesheet,
    /@media \(width >= 40rem\)[\s\S]*\.header-actions\s*{[^}]*display:\s*flex;/
  )
  assert.match(
    stylesheet,
    /@media \(width >= 40rem\)[\s\S]*\.mobile-menu-toggle,[\s\S]*\.mobile-menu\s*{[^}]*display:\s*none;/
  )
})
