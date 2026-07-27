<script setup>
const props = defineProps({
  currentPage: {
    type: Number,
    required: true
  },
  lastPage: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['change'])

const pages = computed(() => {
  const start = Math.max(1, props.currentPage - 2)
  const end = Math.min(props.lastPage, props.currentPage + 2)

  return Array.from({ length: end - start + 1 }, (_, index) => start + index)
})
</script>

<template>
  <nav v-if="lastPage > 1" :aria-label="$t('pagination.label')" class="pagination">
    <button
      type="button"
      class="pagination-button"
      :disabled="currentPage === 1"
      :aria-label="$t('pagination.previous')"
      @click="emit('change', currentPage - 1)"
    >
      <svg viewBox="0 0 20 20" class="pagination-icon" stroke="currentColor" stroke-width="2">
        <path d="m12.5 4.5-5 5.5 5 5.5" />
      </svg>
    </button>

    <button
      v-for="page in pages"
      :key="page"
      type="button"
      class="pagination-button"
      :class="{ 'pagination-button-active': page === currentPage }"
      :aria-current="page === currentPage ? 'page' : undefined"
      :aria-label="$t('pagination.page', { page })"
      @click="emit('change', page)"
    >
      {{ page }}
    </button>

    <button
      type="button"
      class="pagination-button"
      :disabled="currentPage === lastPage"
      :aria-label="$t('pagination.next')"
      @click="emit('change', currentPage + 1)"
    >
      <svg viewBox="0 0 20 20" class="pagination-icon" stroke="currentColor" stroke-width="2">
        <path d="m7.5 4.5 5 5.5-5 5.5" />
      </svg>
    </button>
  </nav>
</template>
