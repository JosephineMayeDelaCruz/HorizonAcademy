<!-- ▲ PAGE CONTENT ABOVE ▲ -->
    </div><!-- .content -->
  </div><!-- .main-area -->
</div><!-- .portal-wrap -->
</div><!-- .page -->

<!-- Toast notification container for temporary messages. -->
<div class="toast" id="toast"></div>

<!-- Load the main JavaScript file with shared UI functions. -->
<script src="<?= url('assets/js/main.js') ?>"></script>
<!-- If a page has custom inline JavaScript, it gets included here. -->
<?php if (!empty($extraJs)): ?>
<script><?= $extraJs ?></script>
<?php endif; ?>
</body>
</html>
