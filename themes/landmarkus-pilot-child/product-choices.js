(() => {
 const form = document.currentScript.closest('form.variations_form');
 const fields = form ? [...form.querySelectorAll('.lm-choice-field')] : [];
 if (!fields.length) return;

 const color = form.querySelector('[data-attribute="pa_color"]');
 if (color) {
  const row = color.closest('tr');
  const options = row.parentElement;
  const clear = options.querySelector('.reset_variations');
  options.prepend(row);
  if (clear) options.lastElementChild.querySelector('td.value')?.append(clear);
 }

 const sync = () => fields.forEach(field => {
  const select = field.querySelector('select');
  field.querySelectorAll('.lm-choice').forEach(button => {
   const option = [...select.options].find(item => item.value === button.dataset.value);
   button.disabled = !option || option.disabled;
   button.setAttribute('aria-pressed', String(select.value === button.dataset.value));
  });

  const selected = field.querySelector('.lm-selected-color');
  if (selected) {
   const name = field.querySelector('.lm-choice[aria-pressed="true"]')?.dataset.colorName;
   selected.hidden = !name;
   selected.textContent = name ? 'Color: ' + name : '';
  }
 });

 fields.forEach(field => {
  const select = field.querySelector('select');
  const buttons = field.querySelector('.lm-choice-buttons');
  if (!select || !buttons) return;

  const tooltip = field.querySelector('.lm-color-tooltip');
  if (tooltip) {
   const hide = () => { tooltip.hidden = true; };
   const show = button => {
    tooltip.textContent = button.dataset.colorName;
    tooltip.hidden = false;
    const card = button.getBoundingClientRect();
    const tip = tooltip.getBoundingClientRect();
    const top = card.top - tip.height - 8;
    tooltip.style.left = Math.max(12, Math.min(innerWidth - tip.width - 12, card.left + (card.width - tip.width) / 2)) + 'px';
    tooltip.style.top = Math.max(12, Math.min(innerHeight - tip.height - 12, top >= 12 ? top : card.bottom + 8)) + 'px';
   };
   const hideUnlessFocused = button => { if (!button.matches(':focus-visible')) hide(); };

   buttons.querySelectorAll('.lm-choice-image').forEach(button => {
    button.setAttribute('aria-describedby', tooltip.id);
    button.addEventListener('mouseenter', () => show(button));
    button.addEventListener('mouseleave', () => hideUnlessFocused(button));
    button.addEventListener('focus', () => { if (button.matches(':focus-visible')) show(button); });
    button.addEventListener('blur', hide);
    button.addEventListener('keydown', event => { if (event.key === 'Escape') hide(); });
    button.addEventListener('click', () => hideUnlessFocused(button));
   });
   window.addEventListener('scroll', hide, true);
   window.addEventListener('resize', hide);
  }

  buttons.querySelectorAll('.lm-choice').forEach(button => {
   button.addEventListener('click', () => {
    select.value = select.value === button.dataset.value ? '' : button.dataset.value;
    select.dispatchEvent(new Event('change', {bubbles: true}));
    sync();

    if (!select.value || !button.dataset.imageSrc) return;
    const image = document.querySelector('.woocommerce-product-gallery .wp-post-image,.woocommerce-product-gallery__image img');
    if (!image) return;
    image.src = button.dataset.imageSrc;
    image.srcset = button.dataset.imageSrcset || '';
    image.setAttribute('data-src', button.dataset.imageSrc);
    image.setAttribute('data-large_image', button.dataset.imageSrc);
    const link = image.closest('a');
    if (link) link.href = button.dataset.imageSrc;
   });

   button.addEventListener('keydown', event => {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const available = [...buttons.querySelectorAll('button:not(:disabled)')];
    const index = available.indexOf(button);
    const offset = event.key === 'ArrowRight' ? 1 : -1;
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? available.length - 1 : (index + offset + available.length) % available.length;
    available[next]?.focus();
   });
  });

  buttons.hidden = false;
  field.classList.add('lm-choice-ready');
 });

 const bindWooEvents = () => {
  window.jQuery?.(form).on('woocommerce_update_variation_values found_variation reset_data', () => queueMicrotask(sync));
 };
 if (window.jQuery) bindWooEvents();
 else document.addEventListener('DOMContentLoaded', bindWooEvents, {once: true});
 sync();
})();
