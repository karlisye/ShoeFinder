<script setup>
const props = defineProps({
  image: {
    type: Object,
    default: null
  }
})

const failed = ref(false)

watch(
  () => props.image?.url,
  () => {
    failed.value = false
  }
)
</script>

<template>
  <div class="relative aspect-[4/3] overflow-hidden bg-surface">
    <img
      v-if="image?.url && !failed"
      :src="image.url"
      :alt="image.alt || ''"
      class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
      loading="lazy"
      @error="failed = true"
    />

    <div
      v-else
      class="flex h-full flex-col items-center justify-center gap-3 text-secondary-light"
      role="img"
      :aria-label="$t('product.imageFallback')"
    >
      <svg viewBox="0 0 64 64" class="h-15 w-15 fill-none" stroke="currentColor" stroke-width="2">
        <path
          d="M8 38c9.6-.4 16.6-5.4 21-15l6 6.6c3.8 4.2 9 7 14.6 8l2.4.4v6.4c-11.6 2.8-25 3-40 .6a4.8 4.8 0 0 1-4-4.8V38Z"
        />
        <path d="m29 23-5-5m12 12 4.8-4.8M10 50h42" />
      </svg>
      <span class="text-xs font-semibold">{{ $t('product.imageFallback') }}</span>
    </div>
  </div>
</template>
