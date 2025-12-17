(function () {
  const searchInput = document.querySelector('[data-filter="search"]');
  const cards = Array.from(document.querySelectorAll('[data-tool-card]'));
  const emptyStates = Array.from(document.querySelectorAll('[data-empty]'));
  const sectionCounts = Array.from(document.querySelectorAll('[data-section-count]'));
  const totalCountBadge = document.querySelector('[data-tool-count]');
  const tokenInput = document.querySelector('[data-token-input]');
  const tokenForm = document.querySelector('[data-token-form]');
  const tokenMessage = document.querySelector('[data-token-msg]');
  const tokenClear = document.querySelector('[data-token-clear]');
  const launchButtons = Array.from(document.querySelectorAll('[data-launch]'));
  const storageKey = 'portalAccessToken';

  function showTokenMessage(message) {
    if (tokenMessage) {
      tokenMessage.textContent = message;
    }
  }

  function getToken() {
    return sessionStorage.getItem(storageKey) || '';
  }

  function setToken(value) {
    if (value) {
      sessionStorage.setItem(storageKey, value);
    } else {
      sessionStorage.removeItem(storageKey);
    }
  }

  function applyFilters() {
    const term = (searchInput?.value || '').trim().toLowerCase();
    const counts = { web: 0, cli: 0 };
    let totalVisible = 0;

    cards.forEach((card) => {
      const haystack = card.dataset.search || '';
      const mode = card.dataset.mode || 'web';
      const match = !term || haystack.includes(term);
      card.hidden = !match;
      if (match) {
        counts[mode] = (counts[mode] || 0) + 1;
        totalVisible += 1;
      }
    });

    sectionCounts.forEach((el) => {
      const mode = el.dataset.sectionCount || 'web';
      el.textContent = (counts[mode] || 0).toString();
    });

    emptyStates.forEach((state) => {
      const mode = state.dataset.empty || 'web';
      state.hidden = (counts[mode] || 0) > 0;
    });

    if (totalCountBadge) {
      totalCountBadge.textContent = totalVisible.toString();
    }
  }

  searchInput?.addEventListener('input', applyFilters);

  tokenForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const value = (tokenInput?.value || '').trim();
    if (!value) {
      tokenInput?.classList.add('token-error');
      showTokenMessage('Token is required to launch every tool.');
      return;
    }
    setToken(value);
    tokenInput?.classList.remove('token-error');
    showTokenMessage('Token saved for this session.');
  });

  tokenClear?.addEventListener('click', () => {
    setToken('');
    if (tokenInput) {
      tokenInput.value = '';
      tokenInput.classList.remove('token-error');
    }
    showTokenMessage('Token cleared. Enter a new token to enable launches.');
  });

  tokenInput?.addEventListener('input', () => {
    tokenInput.classList.remove('token-error');
    showTokenMessage('Required for launching every tool. Stored only in this browser session.');
  });

  launchButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const url = button.dataset.launch;
      if (!url) return;
      const token = getToken();
      if (!token) {
        tokenInput?.focus();
        tokenInput?.classList.add('token-error');
        showTokenMessage('Please save your access token before launching a tool.');
        return;
      }
      const joiner = url.includes('?') ? '&' : '?';
      const destination = `${url}${joiner}token=${encodeURIComponent(token)}`;
      window.open(destination, '_blank', 'noopener');
    });
  });

  const savedToken = getToken();
  if (savedToken && tokenInput) {
    tokenInput.value = savedToken;
  }

  applyFilters();
})();
