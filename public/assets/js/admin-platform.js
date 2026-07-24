(() => {
  const switcher = document.querySelector('.admin-brand-switcher');
  if (!switcher) return;
  const trigger = switcher.querySelector('.admin-brand-switcher__trigger');
  const menu = switcher.querySelector('.admin-brand-menu');
  const items = () => [...menu.querySelectorAll('a, button')];
  const close = () => { menu.hidden = true; trigger.setAttribute('aria-expanded', 'false'); };
  trigger.addEventListener('click', () => {
    const opening = menu.hidden;
    menu.hidden = !opening;
    trigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
    if (opening) items()[0]?.focus();
  });
  document.addEventListener('click', (event) => { if (!switcher.contains(event.target)) close(); });
  switcher.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') { close(); trigger.focus(); return; }
    if (menu.hidden || !['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const options = items();
    const current = options.indexOf(document.activeElement);
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? options.length - 1 :
      event.key === 'ArrowDown' ? (current + 1) % options.length : (current - 1 + options.length) % options.length;
    options[next]?.focus();
  });
})();
