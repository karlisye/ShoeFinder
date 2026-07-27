<template>
  <div class="catalogue-grid" aria-hidden="true">
    <div
      v-for="index in 6"
      :key="index"
      class="overflow-hidden rounded-2xl border border-secondary-light/10 bg-elevated"
    >
      <div class="skeleton h-52" />
      <div class="space-y-3 p-5">
        <div class="skeleton h-3 w-20 rounded" />
        <div class="skeleton h-6 w-3/4 rounded" />
        <div class="skeleton h-4 w-2/5 rounded" />
        <div class="skeleton mt-6 h-8 w-1/2 rounded" />
        <div class="skeleton mt-6 h-8 rounded" />
      </div>
    </div>
  </div>
</template>
