/** @type {import('stylelint').Config} */
export default {
  extends: ['stylelint-config-standard'],
  ignoreFiles: ['.nuxt/**', '.output/**', 'node_modules/**'],
  plugins: ['stylelint-order'],
  rules: {
    'at-rule-no-unknown': [
      true,
      {
        ignoreAtRules: ['apply', 'theme']
      }
    ],
    'import-notation': 'string',
    'order/properties-alphabetical-order': true
  }
}
