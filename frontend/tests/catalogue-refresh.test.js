import assert from 'node:assert/strict'
import test from 'node:test'

import { listenForCatalogueRefresh } from '../app/utils/catalogueRefresh.js'

test('catalogue data refreshes when its window regains focus', () => {
  const target = new EventTarget()
  let refreshCount = 0
  const stop = listenForCatalogueRefresh(target, () => {
    refreshCount += 1
  })

  target.dispatchEvent(new Event('focus'))
  assert.equal(refreshCount, 1)

  stop()
  target.dispatchEvent(new Event('focus'))
  assert.equal(refreshCount, 1)
})
