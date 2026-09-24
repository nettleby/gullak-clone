</main>
<?php if (!empty($active_tab)): ?>
  <nav class="tabbar">
    <?php foreach ($tabs as $key => $t): ?>
      <a class="tab <?= ($active_tab === $key) ? 'active' : '' ?>" href="<?= url($t['url']) ?>">
        <?= lucide($t['icon']) ?>
        <span><?= e($t['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>
</div>
<script src="https://unpkg.com/lucide@0.462.0/dist/umd/lucide.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js" crossorigin="anonymous"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
