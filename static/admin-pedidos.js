(() => {
  'use strict';
  const dialog = document.getElementById('jobsOrderModal');
  const content = document.getElementById('jobsOrderModalContent');
  if (!dialog || !content) return;
  document.addEventListener('click', event => {
    const open = event.target.closest('[data-order-detail]');
    if (open) {
      const template = document.getElementById(open.dataset.orderDetail);
      if (!template) return;
      content.replaceChildren(template.content.cloneNode(true));
      dialog.showModal();
      return;
    }
    if (event.target.closest('[data-close-order]')) dialog.close();
  });
  dialog.addEventListener('click', event => {
    if (event.target === dialog) dialog.close();
  });
})();
