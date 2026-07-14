<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$eid = eid();
$db  = getDB();

try { $db->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS rut_empresa  VARCHAR(20)  DEFAULT ''"); } catch(PDOException $e) {}
try { $db->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS descripcion  VARCHAR(120) DEFAULT ''"); } catch(PDOException $e) {}

$se = $db->prepare("SELECT nombre, logo_path, rut_empresa, descripcion,
                           direccion, comuna, region, telefono, correo
                    FROM empresas WHERE id_empresa = ?");
$se->execute([$eid]);
$emp = $se->fetch();
if (!$emp) { echo 'Empresa no encontrada.'; exit; }

$sr = $db->prepare("SELECT id_ingreso, fecha_ingreso, nombre_cliente, telefono_cliente, rut_cliente,
                           tipo_ingreso, marca_ingreso, modelo_ingreso, dano_ingreso,
                           valor_ingreso, obs, ingresado_por
                    FROM reparaciones WHERE id_ingreso = ? AND id_empresa = ?");
$sr->execute([$id, $eid]);
$rep = $sr->fetch();
if (!$rep) { echo 'Orden de servicio no encontrada.'; exit; }

$logoSrc   = ($emp['logo_path'] && file_exists(__DIR__.'/'.$emp['logo_path']))
           ? BASE.'/'.$emp['logo_path'] : '';
$ubicacion = trim(implode(', ', array_filter([
    $emp['direccion'] ?? '', $emp['comuna'] ?? '',
])));
$fechaFmt  = $rep['fecha_ingreso']
           ? date('d/m/Y H:i', strtotime($rep['fecha_ingreso'])) : '';
$numFmt    = str_pad($rep['id_ingreso'], 3, '0', STR_PAD_LEFT);
$valorFmt  = $rep['valor_ingreso'] > 0
           ? '$ '.number_format((int)$rep['valor_ingreso'], 0, ',', '.') : '—';
$equipo    = trim(implode(' ', array_filter([
    $rep['marca_ingreso'], $rep['modelo_ingreso'],
    $rep['tipo_ingreso'] ? '('.$rep['tipo_ingreso'].')' : '',
])));

function hh(string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Orden #<?= $numFmt ?></title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/orden.css">
</head>
<body>
<div class="page">
<div class="modal-box">

  <!-- ══ CABECERA ════════════════════════════ -->
  <div class="modal-hd">
    <div class="modal-hd-left">
      <h3>Orden de servicio</h3>
      <span class="hd-num">#<?= hh($numFmt) ?></span>
      <span class="hd-fecha"><?= hh($fechaFmt) ?></span>
    </div>
    <button class="btn-close-hd" id="btn-cerrar" title="Cerrar">
      <span class="material-icons-round">close</span>
    </button>
  </div>

  <!-- ══ EMPRESA STRIP ═══════════════════════ -->
  <div class="emp-strip">
    <?php if ($logoSrc): ?>
      <img class="emp-logo" src="<?= hh($logoSrc) ?>" alt="Logo">
    <?php else: ?>
      <div class="emp-logo-ph"><span class="material-icons-round">store</span></div>
    <?php endif; ?>
    <div class="emp-info">
      <div class="emp-nombre"><?= hh($emp['nombre'] ?? '') ?></div>
      <div class="emp-sub">
        <?php $parts = array_filter([$emp['rut_empresa'] ?? '', $ubicacion]); ?>
        <?= hh(implode(' · ', $parts)) ?>
        <?php if ($emp['descripcion']): ?> · <?= hh($emp['descripcion']) ?><?php endif; ?>
      </div>
    </div>
    <div class="emp-contacto">
      <?php if ($emp['telefono']): ?>
      <div class="ct">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        <?= hh($emp['telefono']) ?>
      </div>
      <?php endif; ?>
      <?php if ($emp['correo']): ?>
      <div class="ct">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="#8b949e"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
        <?= hh($emp['correo']) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ CUERPO ══════════════════════════════ -->
  <div class="modal-body">

    <!-- Cliente -->
    <div>
      <div class="section-lbl">Datos del cliente</div>
      <div class="info-grid">
        <div class="info-field span2">
          <span class="lbl">Nombre</span>
          <span class="val"><?= hh($rep['nombre_cliente']) ?></span>
        </div>
        <div class="info-field">
          <span class="lbl">RUT</span>
          <span class="val"><?= hh($rep['rut_cliente'] ?: '—') ?></span>
        </div>
        <div class="info-field">
          <span class="lbl">Teléfono</span>
          <span class="val"><?= hh($rep['telefono_cliente']) ?></span>
        </div>
        <div class="info-field span2">
          <span class="lbl">Equipo</span>
          <span class="val accent"><?= hh($equipo) ?></span>
        </div>
      </div>
    </div>

    <!-- Detalle equipo -->
    <div>
      <div class="section-lbl">Detalle del servicio</div>
      <div class="equipo-row">
        <div class="eq-cell">
          <div class="eq-lbl">Tipo</div>
          <div class="eq-val"><?= hh($rep['tipo_ingreso']) ?></div>
        </div>
        <div class="eq-cell">
          <div class="eq-lbl">Marca</div>
          <div class="eq-val"><?= hh($rep['marca_ingreso']) ?></div>
        </div>
        <div class="eq-cell">
          <div class="eq-lbl">Modelo</div>
          <div class="eq-val"><?= hh($rep['modelo_ingreso']) ?></div>
        </div>
        <div class="eq-cell">
          <div class="eq-lbl">Trabajo a realizar</div>
          <div class="eq-val"><?= hh($rep['dano_ingreso']) ?></div>
        </div>
      </div>
    </div>

    <!-- Observación + valor -->
    <div>
      <div class="section-lbl">Observación de entrada</div>
      <div class="obs-valor">
        <div class="obs-box"><?= hh($rep['obs'] ?? '') ?: '<span style="color:var(--txt3)">Sin observaciones.</span>' ?></div>
        <div class="valor-box">
          <span class="lbl">Valor del servicio</span>
          <span class="val"><?= hh($valorFmt) ?></span>
        </div>
      </div>
    </div>

    <!-- Legal -->
    <div class="legal-box">
      Ley 19,496 art. 42 — Las especies entregadas en reparación se considerarán abandonadas si no son retiradas en el plazo de un año
      desde la fecha del comprobante. Garantía de 30 días desde la entrega conforme. Cambio de pantalla incluye lámina protectora;
      la garantía está sujeta a la presentación de la lámina original instalada al momento de la reparación.
    </div>

    <!-- Firmas -->
    <div>
      <div class="section-lbl">Firmas</div>
      <div class="firmas-row">
        <div class="firma-card">
          <div class="fc-space"></div>
          <div class="fc-lbl">Firma Ingreso Servicio</div>
        </div>
        <div class="firma-card">
          <div class="fc-space"></div>
          <div class="fc-lbl">Fecha Entrega Servicio</div>
        </div>
        <div class="firma-card">
          <div class="fc-space"></div>
          <div class="fc-lbl">Firma Entrega Conforme</div>
        </div>
      </div>
    </div>

  </div><!-- /modal-body -->

  <!-- ══ PIE ════════════════════════════════ -->
  <div class="modal-ft">
    <button class="btn-sec" id="btn-cerrar">
      <span class="material-icons-round">close</span> Cerrar
    </button>
    <button class="btn-sec accent" id="btn-imprimir">
      <span class="material-icons-round">print</span> Imprimir / PDF
    </button>
  </div>

</div><!-- /modal-box -->
</div><!-- /page -->
<script src="<?= BASE ?>/assets/js/orden.js"></script>
</body>
</html>
