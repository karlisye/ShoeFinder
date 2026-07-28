export function catalogueCardRoute(card) {
  return {
    path: `/shoes/${card.slug}`,
    query: {
      colour: card.colour.code
    }
  }
}
