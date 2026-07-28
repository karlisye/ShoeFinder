import assert from 'node:assert/strict'
import test from 'node:test'

import { catalogueCardRoute } from '../app/utils/catalogueCard.js'

test('a colour-specific catalogue card opens the matching product variant', () => {
  assert.deepEqual(
    catalogueCardRoute({
      slug: 'air-max-1',
      colour: {
        code: 'blue'
      }
    }),
    {
      path: '/shoes/air-max-1',
      query: {
        colour: 'blue'
      }
    }
  )
})
