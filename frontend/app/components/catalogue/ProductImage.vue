<script setup>
import { imageCanRender } from '~/utils/imageFallback'

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
  <div class="product-image">
    <img
      v-if="imageCanRender(image, failed)"
      :src="image.url"
      :alt="image.alt || ''"
      class="product-image-media"
      loading="lazy"
      @error="failed = true"
    />

    <div v-else class="product-image-fallback" role="img" :aria-label="$t('product.imageFallback')">
      <svg viewBox="0 0 64 64" class="product-image-icon" stroke="currentColor" stroke-width="2">
        <path
          d="M8 38c9.6-.4 16.6-5.4 21-15l6 6.6c3.8 4.2 9 7 14.6 8l2.4.4v6.4c-11.6 2.8-25 3-40 .6a4.8 4.8 0 0 1-4-4.8V38Z"
        />
        <path d="m29 23-5-5m12 12 4.8-4.8M10 50h42" />
      </svg>
      <span class="product-image-fallback-label">{{ $t('product.imageFallback') }}</span>
    </div>
  </div>
</template>
