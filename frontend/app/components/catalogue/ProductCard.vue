<script setup>
import { catalogueCardRoute } from '~/utils/catalogueCard'

const props = defineProps({
  shoe: {
    type: Object,
    required: true
  }
})

const { t } = useI18n()
const { formatMoney } = useMoney()
const localePath = useLocalePath()

const visibleSizes = computed(() => props.shoe.available_sizes?.slice(0, 5) ?? [])
const remainingSizeCount = computed(() =>
  Math.max(0, (props.shoe.available_sizes?.length ?? 0) - visibleSizes.value.length)
)
const productRoute = computed(() => localePath(catalogueCardRoute(props.shoe)))
</script>

<template>
  <NuxtLink
    :to="productRoute"
    class="product-card-link"
    :aria-label="
      t('product.openProductColour', {
        name: shoe.name,
        colour: shoe.colour.name
      })
    "
  >
    <article class="product-card">
      <CatalogueProductImage :image="shoe.primary_image" />

      <div class="product-card-body">
        <div class="product-card-heading">
          <div class="product-card-heading-text">
            <p class="product-card-brand">
              {{ shoe.brand.name }}
            </p>
            <h3 class="product-card-title">
              {{ shoe.name }}
            </h3>
          </div>

          <span v-if="shoe.on_sale" class="sale-badge">
            {{ t('product.onSale') }}
          </span>
        </div>

        <p class="product-card-category">{{ shoe.category.name }}</p>

        <div class="product-card-colours">
          <p class="product-card-label">{{ t('product.colours') }}</p>
          <ul class="product-card-colour-list" :aria-label="t('product.colours')">
            <li v-for="colour in shoe.colours" :key="colour.variant_id">
              <span
                class="product-card-colour"
                :class="{ 'product-card-colour-active': colour.code === shoe.colour.code }"
                :aria-current="colour.code === shoe.colour.code ? 'true' : undefined"
              >
                {{ colour.name }}
              </span>
            </li>
          </ul>
        </div>

        <div class="product-card-price">
          <template v-if="shoe.price_available">
            <p class="product-card-label">{{ t('product.from') }}</p>
            <p class="product-card-price-value">
              {{ formatMoney(shoe.lowest_price.amount, shoe.lowest_price.currency) }}
            </p>
          </template>
          <p v-else class="product-card-price-missing">
            {{ t('product.priceUnavailable') }}
          </p>
        </div>

        <div class="product-card-sizes">
          <p class="product-card-label">{{ t('product.availableSizes') }}</p>
          <div v-if="visibleSizes.length" class="size-list">
            <span v-for="size in visibleSizes" :key="size.label" class="size-tag">
              {{ size.label }}
            </span>
            <span v-if="remainingSizeCount" class="size-tag size-tag-muted">
              +{{ remainingSizeCount }}
            </span>
          </div>
          <p v-else class="product-card-no-sizes">{{ t('product.noSizes') }}</p>
        </div>
      </div>
    </article>
  </NuxtLink>
</template>
