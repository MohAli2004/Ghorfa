/**
 * Tracks recommendation clicks and landlord contact actions for the hybrid engine.
 */
(function () {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

  function post(url, body) {
    if (!csrf) return;

    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        Accept: 'application/json',
      },
      body: JSON.stringify(body || {}),
    }).catch(() => {});
  }

  document.addEventListener('click', (event) => {
    const clickTarget = event.target.closest('[data-track-click]');
    if (!clickTarget) return;

    const card = clickTarget.closest('[data-property-id]');
    const propertyId = card?.dataset.propertyId;
    if (!propertyId) return;

    post(`/properties/${propertyId}/track-click`, {
      source: clickTarget.dataset.trackClick || 'unknown',
    });
  });

  document.querySelectorAll('[data-track-contact]').forEach((el) => {
    el.addEventListener('click', () => {
      const propertyId = el.dataset.propertyId;
      if (!propertyId) return;

      post(`/properties/${propertyId}/track-contact`, {
        channel: el.dataset.trackContact || 'unknown',
      });
    });
  });
})();
