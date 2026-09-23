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
});
