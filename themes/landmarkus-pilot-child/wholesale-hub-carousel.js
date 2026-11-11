function lmInitHubCarousel(carousel) {
  const viewport = carousel.querySelector('.lm-hub-viewport');
  const cards = [...viewport.querySelectorAll('.products > .product')];
  if (!cards.length) return;

  const controls = carousel.querySelector('.lm-hub-carousel-controls');
  const position = controls.querySelector('.lm-hub-carousel-position');
  const previous = controls.querySelector('[data-lm-hub-move="-1"]');
  const next = controls.querySelector('[data-lm-hub-move="1"]');
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let frame = 0;

  function visibleIndices() {
    const bounds = viewport.getBoundingClientRect();
    return cards.flatMap((card, index) => {
      const rect = card.getBoundingClientRect();
      const visible = Math.min(rect.right, bounds.right) - Math.max(rect.left, bounds.left);
      return visible >= rect.width / 2 ? [index] : [];
    });
  }

  function updateControls() {
    const visible = visibleIndices();
    const start = (visible[0] ?? 0) + 1;
    const end = (visible.at(-1) ?? 0) + 1;
    const text = start === end ? `${start} of ${cards.length}` : `${start}–${end} of ${cards.length}`;
    if (position.textContent !== text) position.textContent = text;
    const max = viewport.scrollWidth - viewport.clientWidth;
    controls.hidden = max < 2;
    previous.setAttribute('aria-disabled', String(viewport.scrollLeft < 2));
    next.setAttribute('aria-disabled', String(viewport.scrollLeft >= max - 2));
    frame = 0;
  }

  function scheduleUpdate() {
    if (!frame) frame = requestAnimationFrame(updateControls);
  }

  function goTo(index) {
    const card = cards[Math.max(0, Math.min(cards.length - 1, index))];
    const padding = parseFloat(getComputedStyle(viewport).paddingLeft);
    const left = viewport.scrollLeft + card.getBoundingClientRect().left - viewport.getBoundingClientRect().left - padding;
    viewport.scrollTo({left, behavior: motion.matches ? 'instant' : 'smooth'});
  }

  controls.addEventListener('click', event => {
    const button = event.target.closest('[data-lm-hub-move]');
    if (!button || button.getAttribute('aria-disabled') === 'true') return;
    goTo((visibleIndices()[0] ?? 0) + Number(button.dataset.lmHubMove));
  });
  viewport.addEventListener('keydown', event => {
    if (event.target !== viewport) return;
    const first = visibleIndices()[0] ?? 0;
    const targets = {ArrowLeft: first - 1, ArrowRight: first + 1, Home: 0, End: cards.length - 1};
    if (!(event.key in targets)) return;
    event.preventDefault();
    goTo(targets[event.key]);
  });
  viewport.addEventListener('scroll', scheduleUpdate, {passive: true});
  window.addEventListener('resize', scheduleUpdate, {passive: true});
  updateControls();

  function loadSwatches(card) {
    for (const button of card.querySelectorAll('.lm-hub-swatch')) {
      if (button.firstElementChild) continue;
      const image = new Image(24, 24);
      image.alt = '';
      image.decoding = 'async';
      image.src = button.dataset.thumb;
      button.append(image);
    }
  }

  const swatchCards = cards.filter(card => card.querySelector('.lm-hub-swatches'));
  const observer = 'IntersectionObserver' in window
    ? new IntersectionObserver(entries => {
      for (const entry of entries) {
        if (!entry.isIntersecting || entry.intersectionRatio < 0.35) continue;
        loadSwatches(entry.target.closest('.product'));
        observer.unobserve(entry.target);
      }
    }, {threshold: 0.35})
    : null;

  for (const card of swatchCards) {
    const row = card.querySelector('.lm-hub-swatches');
    row.hidden = false;
    observer?.observe(row);
    card.addEventListener('focusin', () => loadSwatches(card), {once: true});
    if (!observer) card.addEventListener('pointerenter', () => loadSwatches(card), {once: true});
  }

  carousel.addEventListener('click', event => {
    const choice = event.target.closest('.lm-hub-swatch');
    if (!choice) return;
    const card = choice.closest('.product');
    const image = card.querySelector('figure .ct-media-container img:not(.ct-swap)');
    if (!image) return;

    // Only the explicit color selection fetches the larger card image.
    image.removeAttribute('srcset');
    image.removeAttribute('sizes');
    image.src = choice.dataset.preview;
    card.dataset.lmHubColor = 'selected';
    for (const button of card.querySelectorAll('.lm-hub-swatch')) {
      button.setAttribute('aria-pressed', String(button === choice));
    }
  });
}


function lmInitHubCategoryReveal() {
  const grid = document.querySelector('#hub-categories .lm-hub-category-grid');
  if (!grid || !('IntersectionObserver' in window)) return;

  const observer = new IntersectionObserver(entries => {
    for (const entry of entries) {
      if (entry.intersectionRatio < .18) continue;
      entry.target.classList.add('is-revealed');
      observer.unobserve(entry.target);
    }
  }, {threshold: .18});

  for (const card of grid.children) {
    if (card.classList.contains('lm-hub-category')) observer.observe(card);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  lmInitHubCategoryReveal();
  document.querySelectorAll('.lm-hub-carousel').forEach(lmInitHubCarousel);
});
