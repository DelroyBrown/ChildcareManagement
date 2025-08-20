// Load child profile modal via AJAX
document.addEventListener('click', function(e){
  const a = e.target.closest('.child-profile-link');
  if (!a) return;
  e.preventDefault();
  const id = a.getAttribute('data-child-id');
  const modalEl = document.getElementById('childProfileModal');
  const content = document.getElementById('childProfileContent');
  if (content) content.innerHTML = '<div class="modal-body p-5 text-center">Loading…</div>';
  fetch('index.php?page=child_profile&id='+encodeURIComponent(id),{credentials:'same-origin'})
    .then(r=>r.text())
    .then(html=>{ if (content) content.innerHTML = html; new bootstrap.Modal(modalEl).show(); })
    .catch(()=>{ if (content) content.innerHTML = '<div class="modal-body p-5 text-danger">Failed to load.</div>'; new bootstrap.Modal(modalEl).show(); });
});
