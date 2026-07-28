export function imageCanRender(image, failed = false) {
  return Boolean(image?.url) && !failed
}

export function recordFailedImage(failedImages, index) {
  return new Set([...failedImages, index])
}
