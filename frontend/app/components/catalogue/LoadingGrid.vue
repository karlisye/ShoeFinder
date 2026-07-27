<template>
  <div class="catalogue-grid" aria-hidden="true">
    <div v-for="index in 6" :key="index" class="loading-card">
      <div class="skeleton loading-card-image" />
      <div class="loading-card-body">
        <div class="skeleton loading-line loading-line-brand" />
        <div class="skeleton loading-line loading-line-title" />
        <div class="skeleton loading-line loading-line-category" />
        <div class="skeleton loading-line loading-line-price" />
        <div class="skeleton loading-line loading-line-sizes" />
      </div>
    </div>
  </div>
</template>
