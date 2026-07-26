import withNuxt from './.nuxt/eslint.config.mjs'
import prettier from 'eslint-config-prettier/flat'

export default withNuxt(
  {
    ignores: ['.nuxt/**', '.output/**', 'node_modules/**']
  },
  prettier
)
