<script setup>
import { deliveryText, offerPreviousPrice } from '~/utils/productComparison'

const props = defineProps({
  offer: {
    type: Object,
    required: true
  },
  selectedSize: {
    type: String,
    default: null
  },
  referrerPath: {
    type: String,
    required: true
  }
})

const { locale, t } = useI18n()
const { formatMoney } = useMoney()

const delivery = computed(() =>
  deliveryText(props.offer.delivery, locale.value, formatMoney, props.offer.currency)
)
const previousPrice = computed(() => offerPreviousPrice(props.offer))
const outboundHref = computed(() => {
  const query = new URLSearchParams({
    locale: locale.value,
    referrer: props.referrerPath
  })

  return `${props.offer.outbound_url}?${query.toString()}`
})
</script>

<template>
  <article class="retailer-offer" :class="{ 'retailer-offer-muted': !offer.qualifies }">
    <div class="retailer-offer-retailer">
      <img
        v-if="offer.retailer.logo_url"
        :src="offer.retailer.logo_url"
        alt=""
        class="retailer-offer-logo"
      />
      <span v-else class="retailer-offer-logo-placeholder" aria-hidden="true" />
      <div>
        <h3 class="retailer-offer-name">{{ offer.retailer.name }}</h3>
        <p v-if="offer.stale" class="retailer-offer-warning">
          {{ t('productDetail.staleOffer') }}
        </p>
        <p v-else-if="selectedSize && !offer.available" class="retailer-offer-unavailable">
          {{ t('productDetail.sizeUnavailable', { size: selectedSize }) }}
        </p>
        <p v-else class="retailer-offer-stock">
          {{ t('productDetail.inStock') }}
        </p>
      </div>
    </div>

    <div class="retailer-offer-delivery">
      <p>{{ delivery.cost }}</p>
      <p v-if="delivery.timeframe">{{ delivery.timeframe }}</p>
      <p v-if="offer.delivery.note" class="retailer-offer-note">
        {{ offer.delivery.note }}
      </p>
    </div>

    <div class="retailer-offer-price">
      <div v-if="offer.item_price" class="retailer-offer-price-values">
        <p class="retailer-offer-price-value">
          {{ formatMoney(offer.item_price, offer.currency) }}
        </p>
        <del v-if="previousPrice" class="retailer-offer-price-previous">
          {{ formatMoney(previousPrice, offer.currency) }}
        </del>
      </div>
      <p v-else class="retailer-offer-price-missing">
        {{ t('product.priceUnavailable') }}
      </p>
      <p v-if="offer.delivered_total" class="retailer-offer-total">
        {{
          t('productDetail.withDelivery', {
            price: formatMoney(offer.delivered_total, offer.currency)
          })
        }}
      </p>
    </div>

    <a :href="outboundHref" class="button-primary retailer-offer-action" rel="nofollow sponsored">
      {{ t('productDetail.openRetailer') }}
    </a>
  </article>
</template>
