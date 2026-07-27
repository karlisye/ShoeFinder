<script setup>
const props = defineProps({
  shoe: {
    type: Object,
    required: true
  }
})

const { t } = useI18n()
const { formatMoney } = useMoney()

const visibleSizes = computed(() => props.shoe.available_sizes?.slice(0, 5) ?? [])
const remainingSizeCount = computed(() =>
  Math.max(0, (props.shoe.available_sizes?.length ?? 0) - visibleSizes.value.length)
)
</script>

<template>
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
</template>
