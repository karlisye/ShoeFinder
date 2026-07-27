<script setup>
const props = defineProps({
  images: {
    type: Array,
    default: () => []
  }
})

const activeIndex = ref(0)
const failedImages = ref(new Set())

watch(
  () => props.images,
  () => {
    activeIndex.value = 0
    failedImages.value = new Set()
  }
)

const activeImage = computed(() => props.images[activeIndex.value] ?? null)
const activeImageFailed = computed(() => failedImages.value.has(activeIndex.value))

function markFailed(index) {
  failedImages.value = new Set([...failedImages.value, index])
}
</script>

<template>
  <div class="product-gallery">
    <div class="product-gallery-main">
      <img
        v-if="activeImage?.url && !activeImageFailed"
        :src="activeImage.url"
        :alt="activeImage.alt || ''"
        class="product-gallery-image"
        @error="markFailed(activeIndex)"
      />
      <div
        v-else
        class="product-gallery-fallback"
        role="img"
        :aria-label="$t('product.imageFallback')"
      >
        <svg
          viewBox="0 0 64 64"
          class="product-gallery-fallback-icon"
          stroke="currentColor"
          stroke-width="2"
          aria-hidden="true"
        >
          <path
            d="M8 38c9.6-.4 16.6-5.4 21-15l6 6.6c3.8 4.2 9 7 14.6 8l2.4.4v6.4c-11.6 2.8-25 3-40 .6a4.8 4.8 0 0 1-4-4.8V38Z"
          />
          <path d="m29 23-5-5m12 12 4.8-4.8M10 50h42" />
        </svg>
        <span>{{ $t('product.imageFallback') }}</span>
      </div>
    </div>

    <div v-if="images.length > 1" class="product-gallery-thumbnails">
      <button
        v-for="(image, index) in images"
        :key="image.id"
        type="button"
        class="product-gallery-thumbnail"
        :class="{ 'product-gallery-thumbnail-active': activeIndex === index }"
        :aria-label="$t('productDetail.showImage', { number: index + 1 })"
        :aria-pressed="activeIndex === index"
        @click="activeIndex = index"
      >
        <img
          v-if="image.url && !failedImages.has(index)"
          :src="image.url"
          :alt="image.alt || ''"
          class="product-gallery-thumbnail-image"
          loading="lazy"
          @error="markFailed(index)"
        />
        <span v-else class="product-gallery-thumbnail-fallback" aria-hidden="true" />
      </button>
    </div>
  </div>
</template>
