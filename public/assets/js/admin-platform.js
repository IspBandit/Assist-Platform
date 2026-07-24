(() => {
  const switcher = document.querySelector('.admin-brand-switcher');
  if (!switcher) return;
  const trigger = switcher.querySelector('.admin-brand-switcher__trigger');
  const menu = switcher.querySelector('.admin-brand-menu');
  const close = () => { menu.hidden = true; trigger.setAttribute('aria-expanded', 'false'); };
  trigger.addEventListener('click', () => {
    const opening = menu.hidden;
    menu.hidden = !opening;
    trigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
  });
  document.addEventListener('click', (event) => { if (!switcher.contains(event.target)) close(); });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { close(); trigger.focus(); } });
})();
