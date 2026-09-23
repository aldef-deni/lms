import './bootstrap';
document.addEventListener('DOMContentLoaded', () => {
 document.querySelectorAll('[data-toggle-sidebar]').forEach(button => button.addEventListener('click', () => {
  const sidebar = document.querySelector('.sidebar');
  sidebar?.classList.toggle('open');
  button.setAttribute('aria-expanded', sidebar?.classList.contains('open') ? 'true' : 'false');
 }));
 document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', event => {
  if (!confirm(form.dataset.confirm)) event.preventDefault();
 }));
 const timer = document.querySelector('[data-deadline]');
 if (timer) {
  const update = () => {
   const seconds = Math.max(0, Math.floor((Date.parse(timer.dataset.deadline) - Date.now()) / 1000));
   timer.textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2,'0')} remaining`;
   if (!seconds) { clearInterval(interval); document.querySelector('#quiz-form')?.requestSubmit(); }
  };
  const interval = setInterval(update, 1000); update();
 }

 const toggleFields = () => {
  const questionType = document.querySelector('[data-control="type"]')?.value;
  const mediaType = document.querySelector('[data-control="media_type"]')?.value;
  const signatureMode = document.querySelector('[data-control="signature_mode"]')?.value;
  document.querySelector('[data-field-wrap="options"]')?.toggleAttribute('hidden', questionType === 'essay');
  document.querySelector('[data-field-wrap="media_path"]')?.toggleAttribute('hidden', !['image', 'video'].includes(mediaType));
  document.querySelector('[data-field-wrap="media_url"]')?.toggleAttribute('hidden', mediaType !== 'video');
  document.querySelector('[data-field-wrap="signature_image"]')?.toggleAttribute('hidden', signatureMode === 'draw');
  document.querySelector('[data-field-wrap="signature_data"]')?.toggleAttribute('hidden', signatureMode !== 'draw');
 };
 document.querySelectorAll('[data-control]').forEach(control => control.addEventListener('change', toggleFields));
 toggleFields();

 document.querySelectorAll('[data-signature-pad]').forEach(pad => {
  const canvas = pad.querySelector('canvas');
  const output = pad.querySelector('[data-signature-output]');
  const context = canvas.getContext('2d');
  let drawing = false;
  let hasInk = false;
  context.lineWidth = 5;
  context.lineCap = 'round';
  context.lineJoin = 'round';
  context.strokeStyle = '#171c32';
  const point = event => {
   const rect = canvas.getBoundingClientRect();
   return { x: (event.clientX - rect.left) * canvas.width / rect.width, y: (event.clientY - rect.top) * canvas.height / rect.height };
  };
  canvas.addEventListener('pointerdown', event => {
   drawing = true;
   hasInk = true;
   canvas.setPointerCapture(event.pointerId);
   const position = point(event);
   context.beginPath();
   context.moveTo(position.x, position.y);
  });
  canvas.addEventListener('pointermove', event => {
   if (!drawing) return;
   const position = point(event);
   context.lineTo(position.x, position.y);
   context.stroke();
  });
  canvas.addEventListener('pointerup', () => { drawing = false; });
  canvas.addEventListener('pointercancel', () => { drawing = false; });
  pad.querySelector('[data-clear-signature]')?.addEventListener('click', () => {
   context.clearRect(0, 0, canvas.width, canvas.height);
   hasInk = false;
   output.value = '';
  });
  canvas.closest('form')?.addEventListener('submit', () => {
   if (hasInk) output.value = canvas.toDataURL('image/png');
  });
 });

 const avatarInput = document.querySelector('[data-avatar-input]');
 avatarInput?.addEventListener('change', () => {
  const file = avatarInput.files?.[0];
  if (!file || !file.type.startsWith('image/')) return;
  const previewUrl = URL.createObjectURL(file);
  document.querySelectorAll('[data-avatar-preview]').forEach(preview => {
   const image = document.createElement('img');
   image.src = previewUrl;
   image.alt = 'New profile photo preview';
   preview.replaceChildren(image);
  });
 });
});
