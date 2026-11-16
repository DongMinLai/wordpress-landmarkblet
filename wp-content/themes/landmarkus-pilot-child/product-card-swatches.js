document.addEventListener('DOMContentLoaded', () => {
  const rows = document.querySelectorAll('.product .lm-hub-swatches');

  function loadThumbnails(row) {
    for (const button of row.querySelectorAll('.lm-hub-swatch')) {
      if (button.firstElementChild) continue;
      const image = new Image(24, 24);
      image.alt = '';
      image.decoding = 'async';
      image.src = button.dataset.thumb;
      button.append(image);
    }
  }

  const observer = 'IntersectionObserver' in window
    ? new IntersectionObserver(entries => {
      for (const entry of entries) {
        if (!entry.isIntersecting || entry.intersectionRatio < 0.35) continue;
        loadThumbnails(entry.target);
        observer.unobserve(entry.target);
      }
    }, {threshold: 0.35})
    : null;

  for (const row of rows) {
    const card = row.closest('.product');
    const image = card.querySelector('figure .ct-media-container img:not(.ct-swap)');
    if (!image) continue;

    row.hidden = false;
    if (observer) observer.observe(row);
    else loadThumbnails(row);
    row.addEventListener('focusin', () => loadThumbnails(row), {once: true});

    row.addEventListener('click', event => {
      const choice = event.target.closest('.lm-hub-swatch');
      if (!choice) return;

      image.removeAttribute('srcset');
      image.removeAttribute('sizes');
      image.src = choice.dataset.preview;
      card.dataset.lmCardColor = 'selected';
      for (const button of row.querySelectorAll('.lm-hub-swatch')) {
        button.setAttribute('aria-pressed', String(button === choice));
      }
    });
  }
});
