document.addEventListener('DOMContentLoaded', () => {
  const collection = document.querySelector('.lm-golf-collection');
  if (!collection) return;

  for (const controls of collection.querySelectorAll('.lm-golf-colors')) {
    controls.hidden = false;
    const row = controls.querySelector('.lm-golf-swatches');
    const choices = [...row.querySelectorAll('.lm-golf-swatch')];
    const toggle = row.querySelector('.lm-golf-swatch-toggle');

    function updateRows() {
      const columns = getComputedStyle(row).gridTemplateColumns.split(' ').length;
      const overflow = choices.length > columns * 2;
      const limit = overflow ? columns * 2 - 1 : choices.length;
      const expanded = overflow && toggle.getAttribute('aria-expanded') === 'true';
      const focused = document.activeElement;

      toggle.hidden = !overflow;
      toggle.setAttribute('aria-expanded', String(expanded));
      const label = expanded ? 'Show fewer colors' : `Show ${choices.length - limit} more colors`;
      toggle.setAttribute('aria-label', label);
      toggle.title = label;
      toggle.textContent = expanded ? '−' : '+';
      // Keep the toggle before the extra colors so Tab reaches them after opening.
      if (overflow && toggle.nextElementSibling !== choices[limit]) {
        row.insertBefore(toggle, choices[limit]);
      }
      choices.forEach((choice, index) => {
        choice.hidden = !expanded && index >= limit;
      });
      if (row.contains(focused) && (focused.hidden || document.activeElement !== focused)) {
        (overflow ? toggle : choices[0]).focus({ preventScroll: true });
      }
    }

    toggle.addEventListener('click', () => {
      toggle.setAttribute('aria-expanded', String(toggle.getAttribute('aria-expanded') !== 'true'));
      updateRows();
    });
    updateRows();
    if (typeof ResizeObserver === 'function') {
      new ResizeObserver(updateRows).observe(row);
    } else {
      window.addEventListener('resize', updateRows);
    }
    controls.addEventListener('click', event => {
      const choice = event.target.closest('.lm-golf-swatch');
      if (!choice || choice.getAttribute('aria-pressed') === 'true') return;

      const image = controls.closest('.lm-golf-product').querySelector('.lm-golf-product-image img');
      if (!image) return;
      if (choice.dataset.srcset) image.setAttribute('srcset', choice.dataset.srcset);
      else image.removeAttribute('srcset');
      image.src = choice.dataset.src;
      controls.querySelector('.lm-golf-color-name').textContent = choice.getAttribute('aria-label');
      controls.querySelector('[aria-pressed="true"]')?.setAttribute('aria-pressed', 'false');
      choice.setAttribute('aria-pressed', 'true');
    });
  }

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
