<?php
$current = basename($_SERVER['PHP_SELF'], '.php');
$links = [
  'admin'                => ['dashboard',        'Resumen'],
  'admin_clientes'       => ['business',         'Clientes'],
  'admin_suscripciones'  => ['workspace_premium','Suscripciones'],
  'admin_actividad'      => ['history',          'Actividad'],
  'admin_soporte'        => ['support_agent',    'Soporte'],
];
// admin_empresa es sub-página de clientes
if ($current === 'admin_empresa') $current = 'admin_clientes';
?>
<aside class="adm-sidebar">
  <div class="adm-brand">
    <div class="brand-icon" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);width:34px;height:34px;font-size:16px;">C</div>
    <div>
      <div style="font-weight:700;font-size:14px;color:var(--txt);">Centrotec</div>
      <div style="font-size:11px;color:#7c3aed;">Administración</div>
    </div>
  </div>
  <nav class="adm-nav">
    <?php foreach ($links as $page => [$icon, $label]): ?>
    <a href="<?= BASE ?>/<?= $page ?>.php" class="adm-link <?= $current === $page ? 'active' : '' ?>">
      <span class="material-icons-round"><?= $icon ?></span><?= $label ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="adm-sidebar-footer">
    <div style="font-size:12px;color:var(--txt2);"><?= htmlspecialchars(sadmin_nombre()) ?></div>
    <a href="<?= BASE ?>/admin_logout.php" class="adm-logout">
      <span class="material-icons-round">logout</span>Salir
    </a>
  </div>
</aside>
