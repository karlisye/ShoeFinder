import assert from 'node:assert/strict'
import test from 'node:test'

import { imageCanRender, recordFailedImage } from '../app/utils/imageFallback.js'

test('images render only when a usable source has not failed', () => {
  assert.equal(imageCanRender({ url: 'https://images.example.test/shoe.jpg' }), true)
  assert.equal(imageCanRender({ url: '' }), false)
  assert.equal(imageCanRender(null), false)
  assert.equal(imageCanRender({ url: 'https://images.example.test/shoe.jpg' }, true), false)
})

test('gallery failures are recorded without mutating existing state', () => {
  const failed = new Set([1])
  const next = recordFailedImage(failed, 2)

  assert.deepEqual([...failed], [1])
  assert.deepEqual([...next], [1, 2])
})
