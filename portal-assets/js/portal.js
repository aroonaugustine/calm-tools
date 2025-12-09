(function () {
  const searchInput = document.querySelector('[data-filter="search"]');
  const categorySelect = document.querySelector('[data-filter="category"]');
  const cards = Array.from(document.querySelectorAll('[data-tool-card]'));
  const emptyState = document.querySelector('[data-empty-state]');
  const countBadge = document.querySelector('[data-tool-count]');

  function applyFilters() {
    const term = (searchInput?.value || '').trim().toLowerCase();
    const category = categorySelect?.value || 'all';
    let matches = 0;

    cards.forEach((card) => {
      const haystack = card.dataset.search || '';
      const cardCategory = card.dataset.category || '';
      const textMatch = !term || haystack.includes(term);
      const categoryMatch = category === 'all' || cardCategory === category;

      const show = textMatch && categoryMatch;
      card.hidden = !show;
      if (show) {
        matches += 1;
      }
    });

    if (countBadge) {
      countBadge.textContent = matches.toString();
    }

    if (emptyState) {
      emptyState.hidden = matches > 0;
    }
  }

  searchInput?.addEventListener('input', applyFilters);
  categorySelect?.addEventListener('change', applyFilters);

  applyFilters();
})();
