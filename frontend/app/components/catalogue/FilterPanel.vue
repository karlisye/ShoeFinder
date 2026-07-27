<script setup>
const props = defineProps({
  filters: {
    type: Object,
    required: true
  },
  options: {
    type: Object,
    default: () => ({
      brands: [],
      categories: [],
      audiences: [],
      colours: [],
      sizes: [],
      retailers: [],
      price_bounds: null
    })
  },
  idPrefix: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['update:filters', 'apply', 'reset'])

function setField(key, value) {
  emit('update:filters', {
    ...props.filters,
    [key]: value
  })
}

function toggleArrayValue(key, value, checked) {
  const values = new Set(props.filters[key])

  if (checked) {
    values.add(value)
  } else {
    values.delete(value)
  }

  setField(key, [...values])
}

function isSelected(key, value) {
  return props.filters[key].includes(value)
}
</script>

<template>
  <form class="filter-form" @submit.prevent="emit('apply')">
    <details v-if="options.brands.length" open class="filter-group">
      <summary>{{ $t('filters.brand') }}</summary>
      <div class="filter-options">
        <label v-for="brand in options.brands" :key="brand.slug" class="filter-option">
          <input
            :id="`${idPrefix}-brand-${brand.slug}`"
            type="checkbox"
            :checked="isSelected('brand', brand.slug)"
            @change="toggleArrayValue('brand', brand.slug, $event.target.checked)"
          />
          <span>{{ brand.name }}</span>
        </label>
      </div>
    </details>

    <details v-if="options.categories.length" open class="filter-group">
      <summary>{{ $t('filters.category') }}</summary>
      <div class="filter-options">
        <label v-for="category in options.categories" :key="category.slug" class="filter-option">
          <input
            :id="`${idPrefix}-category-${category.slug}`"
            type="checkbox"
            :checked="isSelected('category', category.slug)"
            @change="toggleArrayValue('category', category.slug, $event.target.checked)"
          />
          <span>{{ category.name }}</span>
        </label>
      </div>
    </details>

    <details v-if="options.audiences.length" open class="filter-group">
      <summary>{{ $t('filters.audience') }}</summary>
      <div class="filter-options">
        <label v-for="audience in options.audiences" :key="audience.value" class="filter-option">
          <input
            :id="`${idPrefix}-audience-${audience.value}`"
            type="checkbox"
            :checked="isSelected('audience', audience.value)"
            @change="toggleArrayValue('audience', audience.value, $event.target.checked)"
          />
          <span>{{ audience.label }}</span>
        </label>
      </div>
    </details>

    <details v-if="options.colours.length" class="filter-group">
      <summary>{{ $t('filters.colour') }}</summary>
      <div class="filter-options">
        <label v-for="colour in options.colours" :key="colour.code" class="filter-option">
          <input
            :id="`${idPrefix}-colour-${colour.code}`"
            type="checkbox"
            :checked="isSelected('colour', colour.code)"
            @change="toggleArrayValue('colour', colour.code, $event.target.checked)"
          />
          <span>{{ colour.name }}</span>
        </label>
      </div>
    </details>

    <details v-if="options.sizes.length" class="filter-group">
      <summary>{{ $t('filters.size') }}</summary>
      <div class="filter-size-grid">
        <label v-for="size in options.sizes" :key="size.label" class="size-option">
          <input
            :id="`${idPrefix}-size-${size.label}`"
            type="checkbox"
            class="visually-hidden"
            :checked="isSelected('size', size.label)"
            @change="toggleArrayValue('size', size.label, $event.target.checked)"
          />
          <span>{{ size.label }}</span>
        </label>
      </div>
    </details>

    <details v-if="options.retailers.length" class="filter-group">
      <summary>{{ $t('filters.retailer') }}</summary>
      <div class="filter-options">
        <label v-for="retailer in options.retailers" :key="retailer.slug" class="filter-option">
          <input
            :id="`${idPrefix}-retailer-${retailer.slug}`"
            type="checkbox"
            :checked="isSelected('retailer', retailer.slug)"
            @change="toggleArrayValue('retailer', retailer.slug, $event.target.checked)"
          />
          <span>{{ retailer.name }}</span>
        </label>
      </div>
    </details>

    <details open class="filter-group">
      <summary>{{ $t('filters.price') }}</summary>
      <div class="filter-price-grid">
        <label class="filter-price-label">
          {{ $t('filters.priceFrom') }}
          <input
            :id="`${idPrefix}-min-price`"
            type="number"
            min="0"
            step="0.01"
            inputmode="decimal"
            class="filter-price-input"
            :placeholder="options.price_bounds?.minimum ?? '0'"
            :value="filters.min_price"
            @input="setField('min_price', $event.target.value)"
          />
        </label>
        <label class="filter-price-label">
          {{ $t('filters.priceTo') }}
          <input
            :id="`${idPrefix}-max-price`"
            type="number"
            min="0"
            step="0.01"
            inputmode="decimal"
            class="filter-price-input"
            :placeholder="options.price_bounds?.maximum ?? ''"
            :value="filters.max_price"
            @input="setField('max_price', $event.target.value)"
          />
        </label>
      </div>
    </details>

    <fieldset class="filter-availability">
      <legend class="visually-hidden">{{ $t('filters.availability') }}</legend>
      <div class="filter-availability-options">
        <label class="filter-option">
          <input
            :id="`${idPrefix}-in-stock`"
            type="checkbox"
            :checked="filters.in_stock"
            @change="setField('in_stock', $event.target.checked)"
          />
          <span>{{ $t('filters.inStock') }}</span>
        </label>
        <label class="filter-option">
          <input
            :id="`${idPrefix}-on-sale`"
            type="checkbox"
            :checked="filters.on_sale"
            @change="setField('on_sale', $event.target.checked)"
          />
          <span>{{ $t('filters.onSale') }}</span>
        </label>
      </div>
    </fieldset>

    <div class="filter-actions">
      <button type="button" class="button-secondary" @click="emit('reset')">
        {{ $t('filters.clear') }}
      </button>
      <button type="submit" class="button-primary">
        {{ $t('filters.apply') }}
      </button>
    </div>
  </form>
</template>
