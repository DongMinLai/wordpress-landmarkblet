document.addEventListener('DOMContentLoaded', () => {
  const collection = document.querySelector('.lm-golf-collection');
  if (!collection) return;

  const categories = collection.querySelector(".lm-catalog-category-details");
  if (categories) categories.open = false;

  const observer = typeof IntersectionObserver === 'function'
    ? new IntersectionObserver(entries => {
        for (const { target, isIntersecting } of entries) {
          if (!isIntersecting) continue;
          loadSwatches(target);
          observer.unobserve(target);
        }
      })
    : null;

  function loadSwatches(row) {
    for (const button of row.querySelectorAll('.lm-hub-swatch')) {
      if (button.firstElementChild) continue;
      const image = new Image(22, 22);
      image.alt = '';
      image.decoding = 'async';
      image.src = button.dataset.thumb;
      button.append(image);
    }
  }

  for (const row of collection.querySelectorAll('.lm-hub-swatches')) {
    row.hidden = false;
    row.addEventListener('focusin', () => loadSwatches(row), { once: true });
    if (observer) observer.observe(row);
    else loadSwatches(row);
  }

  collection.addEventListener('click', event => {
    const choice = event.target.closest('.lm-hub-swatch');
    if (!choice) return;
    const card = choice.closest('.product');
    const image = card?.querySelector('figure .ct-media-container img:not(.ct-swap)');
    if (!image) return;

    image.removeAttribute('srcset');
    image.removeAttribute('sizes');
    image.src = choice.dataset.preview;
    card.dataset.lmGolfColor = 'selected';
    for (const button of choice.parentElement.querySelectorAll('.lm-hub-swatch')) {
      button.setAttribute('aria-pressed', String(button === choice));
    }
  });
});
