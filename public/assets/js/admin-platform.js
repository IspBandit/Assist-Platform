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

(() => {
  const seconds = Number(document.body.dataset.autoRefreshSeconds || 0);
  if (!Number.isFinite(seconds) || seconds < 5) return;

  let formChanged = false;
  document.addEventListener('input', () => { formChanged = true; }, { passive: true });
  document.addEventListener('change', () => { formChanged = true; }, { passive: true });

  window.setInterval(() => {
    const active = document.activeElement;
    const editing = active && ['INPUT', 'SELECT', 'TEXTAREA'].includes(active.tagName);
    if (document.visibilityState !== 'visible' || formChanged || editing) return;
    window.location.reload();
  }, seconds * 1000);
})();

(() => {
  const form = document.querySelector('form[data-auto-submit]');
  if (!form) return;
  const delay = Math.max(500, Number(form.dataset.autoSubmit || 1200));
  window.setTimeout(() => {
    if (document.visibilityState === 'visible') form.requestSubmit();
  }, delay);
})();

(() => {
  if (!window.matchMedia('(max-width: 720px)').matches) return;
  document.querySelectorAll('details[data-mobile-collapse]').forEach((panel) => {
    panel.removeAttribute('open');
  });
})();

(() => {
  const writeClipboard = async (value) => {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(value);
      return;
    }
    const fallback = document.createElement('textarea');
    fallback.value = value;
    fallback.setAttribute('readonly', '');
    fallback.style.position = 'fixed';
    fallback.style.opacity = '0';
    document.body.appendChild(fallback);
    fallback.select();
    document.execCommand('copy');
    fallback.remove();
  };

  document.querySelectorAll('[data-copy-target]').forEach((button) => {
    button.addEventListener('click', async () => {
      const target = document.querySelector(button.dataset.copyTarget || '');
      if (!target) return;
      const value = 'value' in target ? target.value : target.textContent;
      const status = button.closest('.card')?.querySelector('[data-copy-status]');
      try {
        await writeClipboard(String(value || ''));
        if (status) status.textContent = 'Copied.';
      } catch (error) {
        if (status) status.textContent = 'Copy failed. Select the text and copy it manually.';
      }
    });
  });

  document.querySelectorAll('[data-native-share]').forEach((button) => {
    if (!navigator.share) {
      button.hidden = true;
      return;
    }
    button.addEventListener('click', async () => {
      try {
        await navigator.share({
          title: button.dataset.shareTitle || document.title,
          text: button.dataset.shareText || '',
          url: button.dataset.shareUrl || window.location.href,
        });
      } catch (error) {
        if (error && error.name !== 'AbortError') {
          const status = button.closest('.card')?.querySelector('[data-copy-status]');
          if (status) status.textContent = 'Sharing was unavailable. Copy the message instead.';
        }
      }
    });
  });
})();
