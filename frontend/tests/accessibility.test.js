import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import test from 'node:test'

const stylesheet = await readFile(new URL('../app/assets/css/main.css', import.meta.url), 'utf8')

function colour(name) {
  const match = stylesheet.match(new RegExp(`--color-${name}:\\s*(#[0-9a-f]{6})`, 'i'))

  assert.ok(match, `Missing colour token: ${name}`)

  return match[1]
}

function luminance(hex) {
  const channels = hex
    .match(/[0-9a-f]{2}/gi)
    .map((channel) => Number.parseInt(channel, 16) / 255)
    .map((channel) => (channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4))

  return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722
}

function contrast(first, second) {
  const firstLuminance = luminance(first)
  const secondLuminance = luminance(second)

  return (
    (Math.max(firstLuminance, secondLuminance) + 0.05) /
    (Math.min(firstLuminance, secondLuminance) + 0.05)
  )
}

test('core text and feedback colour pairs meet WCAG AA contrast', () => {
  const pairs = [
    ['primary-dark', 'page'],
    ['secondary', 'page'],
    ['secondary-light', 'page'],
    ['primary-dark', 'elevated'],
    ['success-dark', 'success-light'],
    ['alert-dark', 'alert-light'],
    ['danger-dark', 'danger-light'],
    ['info-dark', 'info-light']
  ]

  for (const [foreground, background] of pairs) {
    assert.ok(
      contrast(colour(foreground), colour(background)) >= 4.5,
      `${foreground} on ${background} must meet 4.5:1`
    )
  }
})

test('primary actions meet WCAG AA text contrast', () => {
  assert.ok(contrast(colour('elevated'), colour('primary')) >= 4.5)
  assert.ok(contrast(colour('elevated'), colour('primary-dark')) >= 4.5)
})
