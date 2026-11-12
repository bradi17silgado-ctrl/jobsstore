(() => {
  const input = document.getElementById('jobsImageInput');
  const preview = document.getElementById('jobsImagePreview');
  if (!input || !preview) return;
  let currentUrl = null;
  input.addEventListener('change', () => {
    const file = input.files && input.files[0];
    if (currentUrl) { URL.revokeObjectURL(currentUrl); currentUrl = null; }
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 3 * 1024 * 1024) {
      alert('Selecciona una imagen JPG, PNG o WEBP de máximo 3 MB.');
      input.value = '';
      return;
    }
    currentUrl = URL.createObjectURL(file);
    preview.replaceChildren();
    const img = document.createElement('img');
    img.src = currentUrl;
    img.alt = 'Vista previa de la imagen seleccionada';
    const caption = document.createElement('span');
    caption.textContent = file.name;
    preview.append(img, caption);
    preview.hidden = false;
  });
})();
