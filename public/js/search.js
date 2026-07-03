/* =========================
   Clear filters functionality
========================= */
function clearAllFilters() {
  const form = document.querySelector('.filter-container form');
  if (!form) return;

  const textInputs = form.querySelectorAll('input[type="text"], input[type="number"]');
  textInputs.forEach(input => {
    input.value = '';
  });

  const checkboxes = form.querySelectorAll('input[type="checkbox"]');
  checkboxes.forEach(checkbox => {
    checkbox.checked = false;
  });

  const selects = form.querySelectorAll('select');
  selects.forEach(select => {
    select.selectedIndex = 0;
  });

  const inputs = form.querySelectorAll('input');
  inputs.forEach(input => {
    input.setCustomValidity('');
  });
}

/* =========================
   Filters toggle
========================= */

function ShowFilterToggle() {
  const filterToggleBtn = document.querySelector('.filter-toggle-btn');
  const searchShowBtn = document.querySelector('.search-show-btn');
  const searchFilters = document.querySelector('.search-filters');
  const filterOverlay = document.querySelector('.filter-overlay');
  const filterCloseBtn = document.querySelector('.filter-close-btn');
  const isMobileView = () => window.matchMedia('(max-width: 640px)').matches;

  function updateShowButtonVisibility() {
    if (!searchShowBtn) return;
    if (!isMobileView()) {
      searchShowBtn.style.display = '';
      return;
    }

    const isFilterOpen = searchFilters.classList.contains('active');
    searchShowBtn.style.display = isFilterOpen ? 'none' : 'flex';
  }

  function closeFilters() {
    searchFilters.classList.remove('active');
    searchFilters.classList.add('fixed-hide');
    if (filterOverlay) {
      filterOverlay.classList.remove('active');
    }
    document.body.style.overflow = 'auto';
    updateShowButtonVisibility();
  }

  function openFilters() {
    searchFilters.classList.add('active');
    searchFilters.classList.remove('fixed-hide');
    if (filterOverlay) {
      filterOverlay.classList.add('active');
    }
    document.body.style.overflow = 'hidden';
    updateShowButtonVisibility();
  }

  if (filterToggleBtn && searchFilters) {
    filterToggleBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      // Clear filters and return to plain search (do not wipe then auto-submit silently).
      if (!window.confirm('Clear all filters and search text?')) {
        return;
      }

      clearAllFilters();
      window.location.href = window.location.pathname;
    });
  }

  if (filterOverlay) {
    filterOverlay.addEventListener('click', () => {
      closeFilters();
    });
  }

  if (filterCloseBtn) {
    filterCloseBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      closeFilters();
    });
  }

  document.addEventListener('click', (e) => {
    if (!searchFilters.contains(e.target) && 
        !filterToggleBtn?.contains(e.target) && 
        !searchShowBtn?.contains(e.target) &&
        !filterOverlay?.contains(e.target) &&
        !filterCloseBtn?.contains(e.target)) {
      closeFilters();
    }
  });

  if (searchShowBtn && searchFilters) {
    searchShowBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      openFilters();
    });
  }

  window.addEventListener('resize', updateShowButtonVisibility);
  updateShowButtonVisibility();
}


/* =========================
   Settings dropdown
========================= */
function ShowSettingslist() {
  const lists = document.querySelectorAll('.setting-list');

  const setCardMenuState = (card, isOpen) => {
    if (!card) {
      return;
    }

    card.classList.toggle('menu-open', isOpen);
  };

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.setting-btn');
    const anyList = e.target.closest('.setting-list');

    if (btn) {
      e.preventDefault();
      e.stopPropagation();

      const card = btn.closest('.listing-card');
      const list = card ? card.querySelector('.setting-list') : null;

      lists.forEach((l) => {
        if (l !== list) {
          l.classList.remove('active');
          setCardMenuState(l.closest('.listing-card'), false);
        }
      });

      if (list) {
        list.classList.toggle('active');
        setCardMenuState(card, list.classList.contains('active'));
      }
      return;
    }

    if (anyList) {
      e.stopPropagation();
      return;
    }

    lists.forEach((l) => {
      l.classList.remove('active');
      setCardMenuState(l.closest('.listing-card'), false);
    });
  });
}

/* =========================
   Price range validation
========================= */
function validatePriceRange() {
  const minInput = document.getElementById('min-price');
  const maxInput = document.getElementById('max-price');
  if (!minInput || !maxInput) return;

  if (minInput.value !== '' && maxInput.value !== '') {
    const min = parseFloat(minInput.value);
    const max = parseFloat(maxInput.value);
    if (min > max) {
      minInput.setCustomValidity('Min price cannot be greater than max price.');
    } else {
      minInput.setCustomValidity('');
    }
  } else {
    minInput.setCustomValidity('');
  }
}

function getSearchFiltersForm() {
  return document.getElementById('search-filters-form');
}

function setFormSortValue(sortValue) {
  const form = getSearchFiltersForm();
  if (!form) {
    return;
  }

  let sortInput = form.querySelector('input[name="sort"]');

  if (!sortValue || sortValue === 'recommended') {
    sortInput?.remove();
    return;
  }

  if (!sortInput) {
    sortInput = document.createElement('input');
    sortInput.type = 'hidden';
    sortInput.name = 'sort';
    form.appendChild(sortInput);
  }

  sortInput.value = sortValue;
}

/* =========================
   Server-side sorting
========================= */
function initPropertySort(selectId = 'sort-options') {
  const select = document.getElementById(selectId);
  if (!select) return;

  const urlParams = new URLSearchParams(window.location.search);
  const urlSort = urlParams.get('sort');
  if (urlSort) {
    const normalizedSort = urlSort.replace('price_high', 'price-high').replace('price_low', 'price-low');
    select.value = normalizedSort;
  }

  select.addEventListener('change', (e) => {
    const form = getSearchFiltersForm();
    if (!form) {
      return;
    }

    setFormSortValue(e.target.value);
    form.submit();
  });
}

/* =========================
   Boot
========================= */
function initSearchPage() {
  ShowFilterToggle();
  ShowSettingslist();
  initPropertySort();
  if (typeof window.initLikeButtons === 'function') {
    window.initLikeButtons();
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSearchPage);
} else {
  initSearchPage();
}

