(function () {
  const form = document.getElementById('register-form');
  const btn = document.getElementById('register-submit');
  const box = document.getElementById('cmc-js-error');
  const flash = document.getElementById('cmc-alert-error');
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const idleHtml = btn ? btn.innerHTML : '';
  let submitting = false;

  if (flash) flash.scrollIntoView({ behavior: 'smooth', block: 'center' });
  if (!form) return;

  const digitsOnly = (event) => {
    const input = event.target;
    const cleaned = String(input.value || '').replace(/\D+/g, '');
    if (input.value !== cleaned) input.value = cleaned;
  };
  form.querySelectorAll('input[name="parent_id"], input[name="sponsor_id"]').forEach((input) => {
    if (input.type === 'hidden') return;
    input.addEventListener('input', digitsOnly);
    input.addEventListener('blur', digitsOnly);
  });

  const setBusy = (busy, label) => {
    if (!btn) return;
    btn.disabled = busy;
    btn.setAttribute('aria-busy', busy ? 'true' : 'false');
    if (busy) {
      btn.textContent = label || 'Starting payment...';
    } else {
      btn.innerHTML = idleHtml;
    }
  };

  const showError = (message) => {
    if (!box) return;
    box.textContent = message;
    box.classList.remove('hidden');
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };

  const hideError = () => {
    if (box) {
      box.textContent = '';
      box.classList.add('hidden');
    }
    if (flash) flash.classList.add('hidden');
    form.querySelectorAll('.cmc-field-error').forEach((el) => el.remove());
    form.querySelectorAll('.cmc-js-invalid').forEach((el) => {
      el.classList.remove('cmc-js-invalid', 'border-danger');
    });
  };

  const applyFieldErrors = (errors) => {
    if (!errors || typeof errors !== 'object') return;
    Object.entries(errors).forEach(([name, messages]) => {
      const input = form.querySelector('[name="' + name + '"]');
      if (!input) return;
      input.classList.add('border-danger', 'cmc-js-invalid');
      const hint = document.createElement('p');
      hint.className = 'cmc-field-error text-xs text-danger mt-1';
      hint.textContent = Array.isArray(messages) ? messages.filter(Boolean).join(' ') : String(messages);
      input.insertAdjacentElement('afterend', hint);
    });
  };

  const isSafeCheckoutUrl = (url) => {
    if (typeof url !== 'string' || !url.trim()) return false;
    try {
      const parsed = new URL(url, window.location.origin);
      return parsed.protocol === 'https:' || parsed.protocol === 'http:';
    } catch (err) {
      return false;
    }
  };

  const customerError = (response, data) => {
    if (response.status === 419) return 'Your session expired. Refresh the page and try again.';
    if (response.status === 429) return 'Too many attempts. Please wait a minute and try again.';
    if (data && typeof data.error === 'string' && data.error.trim()) return data.error;
    if (data && data.errors && typeof data.errors === 'object') {
      const joined = Object.values(data.errors).flat().filter(Boolean).join(' ');
      if (joined) return joined;
    }
    return 'Could not start payment. Please try again.';
  };

  const fail = (message, errors) => {
    applyFieldErrors(errors);
    showError(message);
    setBusy(false);
    submitting = false;
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submitting) return;
    submitting = true;
    hideError();
    setBusy(true, 'Starting payment...');

    try {
      const response = await fetch(form.getAttribute('action') || '/customer/register', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
        body: new FormData(form),
        credentials: 'same-origin',
        redirect: 'manual',
      });

      const data = await response.json().catch(() => ({}));
      if (data && data.ok === true && isSafeCheckoutUrl(data.redirect_url)) {
        setBusy(true, 'Redirecting to payment...');
        window.location.assign(data.redirect_url);
        return;
      }

      if (response.type === 'opaqueredirect' || response.status === 0) {
        fail('Could not start payment. Please refresh the page and try again.');
        return;
      }

      fail(customerError(response, data), data.errors);
    } catch (err) {
      fail('Could not start payment. Please try again.');
    }
  });
})();
