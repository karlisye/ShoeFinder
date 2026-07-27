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
  <article
    class="group panel-shadow overflow-hidden rounded-2xl border border-secondary-light/15 bg-elevated"
  >
    <CatalogueProductImage :image="shoe.primary_image" />

    <div class="p-5">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="truncate text-xs font-bold tracking-[0.12em] text-secondary uppercase">
            {{ shoe.brand.name }}
          </p>
          <h3 class="mt-1 truncate text-lg font-bold text-primary-dark">
            {{ shoe.name }}
          </h3>
        </div>

        <span
          v-if="shoe.on_sale"
          class="shrink-0 rounded-full bg-success-light px-2.5 py-1 text-xs font-bold text-success-dark"
        >
          {{ t('product.onSale') }}
        </span>
      </div>

      <p class="mt-2 text-sm text-secondary">{{ shoe.category.name }}</p>

      <div class="mt-5 min-h-12">
        <template v-if="shoe.price_available">
          <p class="text-xs font-semibold text-secondary">{{ t('product.from') }}</p>
          <p class="mt-0.5 text-2xl font-bold tracking-tight text-primary-dark">
            {{ formatMoney(shoe.lowest_price.amount, shoe.lowest_price.currency) }}
          </p>
        </template>
        <p v-else class="pt-3 text-sm font-semibold text-secondary">
          {{ t('product.priceUnavailable') }}
        </p>
      </div>

      <div class="mt-5 border-t border-secondary-light/15 pt-4">
        <p class="text-xs font-semibold text-secondary">{{ t('product.availableSizes') }}</p>
        <div v-if="visibleSizes.length" class="mt-2 flex flex-wrap gap-1.5">
          <span
            v-for="size in visibleSizes"
            :key="size.label"
            class="rounded-md bg-surface px-2 py-1 text-xs font-semibold text-primary"
          >
            {{ size.label }}
          </span>
          <span
            v-if="remainingSizeCount"
            class="rounded-md bg-surface px-2 py-1 text-xs font-semibold text-secondary"
          >
            +{{ remainingSizeCount }}
          </span>
        </div>
        <p v-else class="mt-2 text-sm text-secondary">{{ t('product.noSizes') }}</p>
      </div>
    </div>
  </article>
</template>
