    </main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="assets/js/app.js"></script>
<?php $__toasts = consume_flash(); if (!empty($__toasts)): ?>
  <div class="toast-container position-fixed bottom-0 end-0 p-3">
    <?php foreach ($__toasts as $t): $cls = $t['type']==='success'?'text-bg-success':($t['type']==='danger'?'text-bg-danger':'text-bg-secondary'); ?>
      <div class="toast align-items-center border-0 mb-2 <?= $cls ?>" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3500">
        <div class="d-flex">
          <div class="toast-body"><?= htmlspecialchars($t['msg']) ?></div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <script>document.querySelectorAll('.toast').forEach(el=>new bootstrap.Toast(el).show());</script>
<?php endif; ?>
</body>
</html>
