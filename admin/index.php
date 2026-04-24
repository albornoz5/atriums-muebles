<?php
session_start();
define('ADMIN_PASS', 'atriums2024');
define('DATA_DIR', __DIR__ . '/../data/');
define('IMG_DIR',  DATA_DIR . 'img/');
define('HERO_DIR', __DIR__ . '/../assets/img/');

/* ── AJAX ── */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['admin'])) { echo json_encode(['error'=>'No autorizado']); exit; }
    $productos = json_decode(file_get_contents(DATA_DIR.'productos.json'), true) ?? [];
    $save = fn() => file_put_contents(DATA_DIR.'productos.json', json_encode($productos, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

    switch ($_GET['ajax']) {
        case 'toggle':
            $id = intval($_POST['id'] ?? 0);
            foreach ($productos as &$p) {
                if ((int)$p['id'] === $id) { $p['activo'] = !($p['activo'] ?? true); $activo = $p['activo']; break; }
            }
            $save(); echo json_encode(['success'=>true,'activo'=>$activo??true]); break;

        case 'eliminar':
            $id = intval($_POST['id'] ?? 0);
            $productos = array_values(array_filter($productos, fn($p)=>(int)$p['id']!==$id));
            $save(); echo json_encode(['success'=>true]); break;

        case 'upload':
            $file = $_FILES['imagen'] ?? null;
            if (!$file || $file['error']) { echo json_encode(['error'=>'Sin archivo']); break; }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext,['jpg','jpeg','png','webp'])) { echo json_encode(['error'=>'Tipo no permitido']); break; }
            $fn = 'img_'.uniqid().'.'.$ext;
            if (!is_dir(IMG_DIR)) mkdir(IMG_DIR, 0775, true);
            move_uploaded_file($file['tmp_name'], IMG_DIR.$fn);
            echo json_encode(['success'=>true,'filename'=>$fn]); break;

        case 'upload_hero':
            $slot = intval($_POST['slot'] ?? 1);
            $file = $_FILES['imagen'] ?? null;
            if (!$file || $file['error']) { echo json_encode(['error'=>'Sin archivo']); break; }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext,['jpg','jpeg','png','webp'])) { echo json_encode(['error'=>'Tipo no permitido']); break; }
            $fn = 'hero-'.$slot.'.'.$ext;
            move_uploaded_file($file['tmp_name'], HERO_DIR.$fn);
            $cfg = json_decode(file_get_contents(DATA_DIR.'config.json'), true) ?? [];
            $cfg['hero']['slides'][$slot-1]['imagen'] = 'assets/img/'.$fn;
            file_put_contents(DATA_DIR.'config.json', json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            echo json_encode(['success'=>true,'filename'=>$fn]); break;

        case 'remove_img':
            $id = intval($_POST['id'] ?? 0); $img = basename($_POST['img'] ?? '');
            foreach ($productos as &$p) {
                if ((int)$p['id']===$id) { $p['imagenes'] = array_values(array_filter($p['imagenes']??[], fn($x)=>$x!==$img)); break; }
            }
            $save();
            @unlink(IMG_DIR.$img);
            echo json_encode(['success'=>true]); break;

        case 'save_producto':
            $id = intval($_POST['id'] ?? 0);
            foreach ($productos as &$p) {
                if ((int)$p['id']===$id) {
                    $p['nombre']      = trim($_POST['nombre'] ?? '');
                    $p['descripcion'] = trim($_POST['descripcion'] ?? '');
                    $p['precio']      = floatval($_POST['precio'] ?? 0);
                    $p['categoria']   = trim($_POST['categoria'] ?? '');
                    $p['material']    = trim($_POST['material'] ?? '');
                    $p['dimensiones'] = trim($_POST['dimensiones'] ?? '');
                    $p['peso']        = trim($_POST['peso'] ?? '');
                    $p['activo']      = isset($_POST['activo']);
                    $p['destacado']   = isset($_POST['destacado']);
                    break;
                }
            }
            $save(); echo json_encode(['success'=>true]); break;

        case 'nuevo_producto':
            $maxId = max(array_column($productos,'id') ?: [0]);
            $productos[] = [
                'id'          => $maxId + 1,
                'slug'        => trim($_POST['slug'] ?? 'producto-'.($maxId+1)),
                'nombre'      => trim($_POST['nombre'] ?? 'Nuevo producto'),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'precio'      => floatval($_POST['precio'] ?? 0),
                'categoria'   => trim($_POST['categoria'] ?? 'general'),
                'material'    => trim($_POST['material'] ?? ''),
                'dimensiones' => trim($_POST['dimensiones'] ?? ''),
                'peso'        => trim($_POST['peso'] ?? ''),
                'imagenes'    => [],
                'activo'      => true,
                'destacado'   => false,
                'orden'       => $maxId + 1,
            ];
            $save(); echo json_encode(['success'=>true,'id'=>$maxId+1]); break;

        case 'save_config':
            $cfg = json_decode(file_get_contents(DATA_DIR.'config.json'), true) ?? [];
            $cfg['anuncio'] = trim($_POST['anuncio'] ?? '');
            for ($i=0;$i<3;$i++) {
                $cfg['hero']['slides'][$i]['titulo']    = trim($_POST["titulo_$i"] ?? '');
                $cfg['hero']['slides'][$i]['subtitulo'] = trim($_POST["subtitulo_$i"] ?? '');
                $cfg['hero']['slides'][$i]['badge']     = trim($_POST["badge_$i"] ?? '');
            }
            $cfg['nosotros']['titulo'] = trim($_POST['nos_titulo'] ?? '');
            $cfg['nosotros']['texto1'] = trim($_POST['nos_texto1'] ?? '');
            $cfg['nosotros']['texto2'] = trim($_POST['nos_texto2'] ?? '');
            file_put_contents(DATA_DIR.'config.json', json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            echo json_encode(['success'=>true]); break;

        case 'get_producto':
            $id = intval($_GET['id'] ?? 0);
            foreach ($productos as $p) { if ((int)$p['id']===$id) { echo json_encode($p); exit; } }
            echo json_encode(['error'=>'No encontrado']); break;
    }
    exit;
}

/* ── LOGIN ── */
if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASS) { $_SESSION['admin'] = true; }
    else { $loginError = true; }
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: /admin/'); exit; }
if (empty($_SESSION['admin'])) { showLogin($loginError??false); exit; }

/* ── DATOS ── */
$productos = json_decode(file_get_contents(DATA_DIR.'productos.json'), true) ?? [];
$cfg       = json_decode(file_get_contents(DATA_DIR.'config.json'), true) ?? [];
$section   = $_GET['section'] ?? 'productos';

function showLogin($error) { ?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin · Atriums</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#f5f0eb;display:flex;align-items:center;justify-content:center;min-height:100vh}
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
.card{background:#fff;border-radius:16px;padding:48px 40px;width:100%;max-width:380px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.logo{text-align:center;margin-bottom:32px}
.logo h1{font-size:22px;font-weight:700;color:#1a1a1a;letter-spacing:-0.5px}
.logo p{color:#888;font-size:13px;margin-top:4px}
label{display:block;font-size:13px;font-weight:500;color:#555;margin-bottom:6px}
input[type=password]{width:100%;padding:12px 14px;border:1.5px solid #e0d8cf;border-radius:10px;font-size:15px;outline:none;transition:.2s}
input[type=password]:focus{border-color:#8B5A2B}
.btn{width:100%;margin-top:20px;padding:13px;background:#8B5A2B;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;transition:.2s}
.btn:hover{background:#7a4e25}
.error{background:#fff1f0;color:#c0392b;border-radius:8px;padding:10px 14px;font-size:13px;margin-top:12px;text-align:center}
</style></head>
<body>
<div class="card">
  <div class="logo"><h1>Panel Admin</h1><p>Atriums Muebles</p></div>
  <form method="post">
    <label>Contraseña</label>
    <input type="password" name="password" placeholder="••••••••" autofocus>
    <button class="btn">Ingresar</button>
    <?php if($error): ?><div class="error">Contraseña incorrecta</div><?php endif; ?>
  </form>
</div>
</body></html>
<?php }

/* ══════════════════════════════
   HTML PRINCIPAL
══════════════════════════════ */
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Panel Admin · Atriums</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#f5f0eb;color:#1a1a1a;min-height:100vh}

/* ─ NAV ─ */
.topbar{background:#fff;border-bottom:1px solid #e8e0d5;padding:0 32px;display:flex;align-items:center;justify-content:space-between;height:60px;position:sticky;top:0;z-index:100}
.topbar-brand{font-size:16px;font-weight:700;color:#1a1a1a}
.topbar-links{display:flex;gap:20px;align-items:center}
.topbar-links a{font-size:13px;color:#888;text-decoration:none}
.topbar-links a:hover{color:#8B5A2B}

/* ─ TABS ─ */
.tabs-wrap{padding:24px 32px 0}
.tabs{display:flex;gap:8px}
.tab{padding:10px 22px;border-radius:10px 10px 0 0;font-size:14px;font-weight:500;cursor:pointer;border:none;background:transparent;color:#888;transition:.2s}
.tab.active{background:#fff;color:#8B5A2B;font-weight:600;box-shadow:0 -2px 8px rgba(0,0,0,.04)}
.tab:hover:not(.active){color:#555}

/* ─ CONTENT ─ */
.content{padding:0 32px 32px;background:#fff;margin:0 32px 32px;border-radius:0 12px 12px 12px;box-shadow:0 2px 12px rgba(0,0,0,.04)}

/* ─ TOOLBAR ─ */
.toolbar{display:flex;align-items:center;gap:12px;padding:20px 0 16px;border-bottom:1px solid #f0ebe4}
.toolbar-right{margin-left:auto;display:flex;gap:10px;align-items:center}
.btn-primary{padding:10px 20px;background:#8B5A2B;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:.2s}
.btn-primary:hover{background:#7a4e25}
.btn-secondary{padding:10px 20px;background:#f5f0eb;color:#555;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:.2s}
.btn-secondary:hover{background:#ece5db}
.count-badge{font-size:13px;color:#888}
select.filter{padding:9px 14px;border:1.5px solid #e0d8cf;border-radius:8px;font-size:13px;color:#555;background:#fff;cursor:pointer;outline:none}

/* ─ PRODUCT LIST ─ */
.product-row{display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid #f5f0eb;transition:.15s}
.product-row:hover{background:#fdf9f5;margin:0 -20px;padding:14px 20px;border-radius:8px}
.product-thumb{width:52px;height:52px;border-radius:8px;object-fit:cover;background:#f0ebe4;flex-shrink:0}
.product-thumb-empty{width:52px;height:52px;border-radius:8px;background:#f0ebe4;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:20px;flex-shrink:0}
.product-info{flex:1;min-width:0}
.product-name{font-size:14px;font-weight:600;color:#1a1a1a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.product-meta{font-size:12px;color:#888;margin-top:2px}
.product-meta .cat{color:#8B5A2B;font-weight:500}
.badge-inactivo{background:#fff1f0;color:#c0392b;font-size:11px;font-weight:600;padding:2px 7px;border-radius:20px;margin-left:6px}
.badge-destacado{background:#fffbe6;color:#b8860b;font-size:11px;font-weight:600;padding:2px 7px;border-radius:20px;margin-left:6px}
.btn-delete{background:none;border:none;color:#c0392b;font-size:13px;cursor:pointer;font-weight:500;padding:4px 8px;border-radius:6px;transition:.15s}
.btn-delete:hover{background:#fff1f0}
.btn-edit{background:none;border:none;color:#8B5A2B;font-size:13px;cursor:pointer;font-weight:500;padding:4px 8px;border-radius:6px;transition:.15s}
.btn-edit:hover{background:#f5f0eb}

/* ─ MODAL ─ */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;display:none;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:16px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;padding:32px}
.modal h2{font-size:18px;font-weight:700;margin-bottom:24px;color:#1a1a1a}
.modal-close{float:right;background:none;border:none;font-size:22px;cursor:pointer;color:#888;line-height:1;margin-top:-4px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.form-group{margin-bottom:14px}
.form-group.full{grid-column:1/-1}
.form-group label{display:block;font-size:12px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.form-group input,.form-group textarea,.form-group select{width:100%;padding:10px 12px;border:1.5px solid #e0d8cf;border-radius:8px;font-size:14px;font-family:inherit;outline:none;transition:.2s;background:#fff}
.form-group input:focus,.form-group textarea:focus,.form-group select:focus{border-color:#8B5A2B}
.form-group textarea{resize:vertical;min-height:80px}
.img-grid{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px}
.img-thumb{position:relative;width:80px;height:80px}
.img-thumb img{width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid #e0d8cf}
.img-thumb.principal img{border-color:#8B5A2B}
.img-label{position:absolute;bottom:0;left:0;right:0;background:#8B5A2B;color:#fff;font-size:10px;font-weight:700;text-align:center;border-radius:0 0 6px 6px;padding:2px}
.img-remove{position:absolute;top:-6px;right:-6px;width:20px;height:20px;background:#c0392b;color:#fff;border:none;border-radius:50%;cursor:pointer;font-size:13px;line-height:20px;text-align:center;padding:0}
.img-add{width:80px;height:80px;border:2px dashed #d0c8be;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#bbb;font-size:28px;transition:.2s;background:#fafafa}
.img-add:hover{border-color:#8B5A2B;color:#8B5A2B;background:#f5f0eb}
.checks{display:flex;gap:20px;margin-top:4px}
.check-label{display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer}
.check-label input[type=checkbox]{width:16px;height:16px;accent-color:#8B5A2B}
.modal-footer{display:flex;gap:10px;margin-top:24px;justify-content:flex-end}
.btn-cancel{padding:11px 22px;background:#f5f0eb;color:#555;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
.btn-save{padding:11px 28px;background:#8B5A2B;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
.btn-save:hover{background:#7a4e25}

/* ─ INICIO SECTION ─ */
.section-card{background:#fdf9f5;border-radius:12px;padding:24px;margin-bottom:20px;border:1px solid #ede5d8}
.section-card h3{font-size:15px;font-weight:700;color:#1a1a1a;margin-bottom:16px}
.hero-img-preview{width:100%;height:140px;object-fit:cover;border-radius:8px;border:2px solid #e0d8cf;margin-top:8px;display:block}
.hero-img-empty{width:100%;height:140px;border:2px dashed #d0c8be;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:13px;margin-top:8px;cursor:pointer}
.hero-img-btn{margin-top:8px;padding:8px 14px;background:#f5f0eb;border:none;border-radius:7px;font-size:12px;font-weight:600;color:#8B5A2B;cursor:pointer}
.save-all{padding:12px 28px;background:#8B5A2B;color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;transition:.2s}
.save-all:hover{background:#7a4e25}
.toast{position:fixed;bottom:24px;right:24px;background:#1a1a1a;color:#fff;padding:12px 20px;border-radius:10px;font-size:14px;font-weight:500;z-index:9999;opacity:0;transform:translateY(8px);transition:.3s;pointer-events:none}
.toast.show{opacity:1;transform:translateY(0)}
</style>
</head>
<body>

<div class="topbar">
  <span class="topbar-brand">Panel Admin</span>
  <div class="topbar-links">
    <a href="/" target="_blank">Ver sitio web</a>
    <a href="?logout">Cerrar sesión</a>
  </div>
</div>

<div class="tabs-wrap">
  <div class="tabs">
    <button class="tab <?= $section==='inicio'?'active':'' ?>" onclick="location='?section=inicio'">Inicio</button>
    <button class="tab <?= $section==='productos'?'active':'' ?>" onclick="location='?section=productos'">Productos</button>
  </div>
</div>

<div class="content">

<?php if ($section === 'productos'): ?>
<!-- ══ PRODUCTOS ══ -->
<div class="toolbar">
  <span class="count-badge"><?= count($productos) ?> productos</span>
  <div class="toolbar-right">
    <select class="filter" id="filterCat" onchange="filtrar()">
      <option value="">Todas las categorías</option>
      <?php $cats = array_unique(array_column($productos,'categoria')); sort($cats); foreach($cats as $c): ?>
      <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars(ucfirst($c)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn-primary" onclick="abrirNuevo()">+ Nuevo producto</button>
  </div>
</div>

<div id="listaProductos">
<?php foreach ($productos as $p):
  $img = ($p['imagenes'][0] ?? null);
  $activo = $p['activo'] ?? true;
  $destacado = $p['destacado'] ?? false;
?>
<div class="product-row" data-cat="<?= htmlspecialchars($p['categoria']??'') ?>" data-id="<?= $p['id'] ?>">
  <?php if ($img): ?>
    <img class="product-thumb" src="/<?= htmlspecialchars($img) ?>" alt="">
  <?php else: ?>
    <div class="product-thumb-empty">·</div>
  <?php endif; ?>
  <div class="product-info">
    <div class="product-name">
      <?= htmlspecialchars($p['nombre']) ?>
      <?php if(!$activo): ?><span class="badge-inactivo">Inactivo</span><?php endif; ?>
      <?php if($destacado): ?><span class="badge-destacado">Destacado</span><?php endif; ?>
    </div>
    <div class="product-meta">
      $<?= number_format(floatval(preg_replace('/[^0-9]/','',strval($p['precio']??'0'))),0,',','.') ?> · <span class="cat"><?= htmlspecialchars($p['categoria']??'') ?></span>
    </div>
  </div>
  <button class="btn-edit" onclick="abrirEditar(<?= $p['id'] ?>)">Editar</button>
  <button class="btn-delete" onclick="eliminar(<?= $p['id'] ?>, this)">Eliminar</button>
</div>
<?php endforeach; ?>
</div>

<?php elseif ($section === 'inicio'):
  $slides = $cfg['hero']['slides'] ?? [[],[],[]];
  $anuncio = $cfg['anuncio'] ?? '';
  $nos = $cfg['nosotros'] ?? [];
?>
<!-- ══ INICIO ══ -->
<div style="padding-top:24px;display:flex;justify-content:flex-end;margin-bottom:4px">
  <button class="save-all" onclick="guardarInicio()">Guardar todo</button>
</div>

<div class="section-card">
  <h3>Barra de anuncios</h3>
  <div class="form-group">
    <label>Texto de la barra superior</label>
    <input type="text" id="anuncio" value="<?= htmlspecialchars($anuncio) ?>" placeholder="Ej: Envíos a todo el país · Consultá hoy">
  </div>
</div>

<?php for ($i=0;$i<3;$i++): $s=$slides[$i]??[]; ?>
<div class="section-card">
  <h3>Hero – Diapositiva <?= $i+1 ?></h3>
  <div class="form-row">
    <div class="form-group">
      <label>Título</label>
      <input type="text" id="titulo_<?= $i ?>" value="<?= htmlspecialchars($s['titulo']??'') ?>">
    </div>
    <div class="form-group">
      <label>Badge</label>
      <input type="text" id="badge_<?= $i ?>" value="<?= htmlspecialchars($s['badge']??'') ?>">
    </div>
  </div>
  <div class="form-group">
    <label>Subtítulo</label>
    <input type="text" id="subtitulo_<?= $i ?>" value="<?= htmlspecialchars($s['subtitulo']??'') ?>">
  </div>
  <div class="form-group">
    <label>Imagen de fondo</label>
    <?php if (!empty($s['imagen'])): ?>
      <img src="/<?= htmlspecialchars($s['imagen']) ?>" class="hero-img-preview" id="hero-preview-<?= $i ?>">
    <?php else: ?>
      <div class="hero-img-empty" id="hero-preview-<?= $i ?>" onclick="document.getElementById('hero-file-<?= $i ?>').click()">Sin imagen · Click para subir</div>
    <?php endif; ?>
    <input type="file" id="hero-file-<?= $i ?>" accept="image/*" style="display:none" onchange="subirHero(<?= $i ?>)">
    <button class="hero-img-btn" onclick="document.getElementById('hero-file-<?= $i ?>').click()">Cambiar imagen</button>
  </div>
</div>
<?php endfor; ?>

<div class="section-card">
  <h3>Sección Nosotros</h3>
  <div class="form-group">
    <label>Título</label>
    <input type="text" id="nos_titulo" value="<?= htmlspecialchars($nos['titulo']??'') ?>">
  </div>
  <div class="form-group">
    <label>Párrafo 1</label>
    <textarea id="nos_texto1"><?= htmlspecialchars($nos['texto1']??'') ?></textarea>
  </div>
  <div class="form-group">
    <label>Párrafo 2</label>
    <textarea id="nos_texto2"><?= htmlspecialchars($nos['texto2']??'') ?></textarea>
  </div>
</div>
<?php endif; ?>

</div><!-- /content -->

<!-- ══ MODAL EDITAR ══ -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <button class="modal-close" onclick="cerrarModal()">×</button>
    <h2 id="modalTitle">Editar producto</h2>
    <input type="hidden" id="editId">
    <div class="form-group">
      <label>Nombre</label>
      <input type="text" id="editNombre">
    </div>
    <div class="form-group">
      <label>Descripción</label>
      <textarea id="editDescripcion"></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Precio</label>
        <input type="number" id="editPrecio">
      </div>
      <div class="form-group">
        <label>Categoría</label>
        <select id="editCategoria">
          <option value="comedor-quincho">Comedor / Quincho</option>
          <option value="living">Living</option>
          <option value="dormitorio">Dormitorio</option>
          <option value="sillas">Sillas</option>
          <option value="mesas">Mesas</option>
          <option value="general">General</option>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Material</label>
        <input type="text" id="editMaterial" placeholder="Ej: Algarrobo Macizo">
      </div>
      <div class="form-group">
        <label>Dimensiones</label>
        <input type="text" id="editDimensiones" placeholder="Ej: 1,40m x 1,40m x 80">
      </div>
    </div>
    <div class="form-group" style="width:50%">
      <label>Peso</label>
      <input type="text" id="editPeso" placeholder="Ej: 55 kg">
    </div>
    <div class="form-group">
      <label>Imágenes</label>
      <div class="img-grid" id="imgGrid"></div>
    </div>
    <input type="file" id="fileInput" accept="image/*" style="display:none" multiple onchange="subirImagenes()">
    <div class="checks">
      <label class="check-label"><input type="checkbox" id="editDestacado"> Destacado</label>
      <label class="check-label"><input type="checkbox" id="editActivo"> Activo</label>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="cerrarModal()">Cancelar</button>
      <button class="btn-save" onclick="guardarProducto()">Actualizar</button>
    </div>
  </div>
</div>

<!-- ══ MODAL NUEVO ══ -->
<div class="modal-overlay" id="modalNuevo">
  <div class="modal">
    <button class="modal-close" onclick="document.getElementById('modalNuevo').classList.remove('open')">×</button>
    <h2>Nuevo producto</h2>
    <div class="form-group">
      <label>Nombre</label>
      <input type="text" id="newNombre">
    </div>
    <div class="form-group">
      <label>Slug (URL)</label>
      <input type="text" id="newSlug" placeholder="ej: mesa-cuadrada-120">
    </div>
    <div class="form-group">
      <label>Descripción</label>
      <textarea id="newDescripcion"></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Precio</label>
        <input type="number" id="newPrecio">
      </div>
      <div class="form-group">
        <label>Categoría</label>
        <select id="newCategoria">
          <option value="comedor-quincho">Comedor / Quincho</option>
          <option value="living">Living</option>
          <option value="dormitorio">Dormitorio</option>
          <option value="sillas">Sillas</option>
          <option value="mesas">Mesas</option>
          <option value="general">General</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="document.getElementById('modalNuevo').classList.remove('open')">Cancelar</button>
      <button class="btn-save" onclick="crearProducto()">Crear</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const BASE = '/admin/?ajax=';

function toast(msg, ok=true) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = ok ? '#1a1a1a' : '#c0392b';
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2800);
}

/* ─ FILTRO ─ */
function filtrar() {
  const cat = document.getElementById('filterCat')?.value || '';
  document.querySelectorAll('.product-row').forEach(r => {
    r.style.display = (!cat || r.dataset.cat === cat) ? '' : 'none';
  });
}

/* ─ ELIMINAR ─ */
async function eliminar(id, btn) {
  if (!confirm('¿Eliminar este producto?')) return;
  const fd = new FormData(); fd.append('id', id);
  const r = await fetch(BASE+'eliminar', {method:'POST', body:fd});
  const d = await r.json();
  if (d.success) { btn.closest('.product-row').remove(); toast('Producto eliminado'); }
  else toast('Error al eliminar', false);
}

/* ─ EDITAR ─ */
let editImagenes = [];

async function abrirEditar(id) {
  const r = await fetch(BASE+'get_producto&id='+id);
  const p = await r.json();
  document.getElementById('editId').value = p.id;
  document.getElementById('editNombre').value = p.nombre || '';
  document.getElementById('editDescripcion').value = p.descripcion || '';
  document.getElementById('editPrecio').value = p.precio || '';
  document.getElementById('editCategoria').value = p.categoria || 'general';
  document.getElementById('editMaterial').value = p.material || '';
  document.getElementById('editDimensiones').value = p.dimensiones || '';
  document.getElementById('editPeso').value = p.peso || '';
  document.getElementById('editDestacado').checked = !!p.destacado;
  document.getElementById('editActivo').checked = p.activo !== false;
  editImagenes = p.imagenes || [];
  renderImgGrid();
  document.getElementById('modalOverlay').classList.add('open');
}

function renderImgGrid() {
  const grid = document.getElementById('imgGrid');
  const id = document.getElementById('editId').value;
  grid.innerHTML = '';
  editImagenes.forEach((img, i) => {
    const d = document.createElement('div');
    d.className = 'img-thumb' + (i===0?' principal':'');
    d.innerHTML = `<img src="/${img}" alt=""><button class="img-remove" onclick="quitarImg('${img}', ${id})">×</button>${i===0?'<div class="img-label">Principal</div>':''}`;
    grid.appendChild(d);
  });
  const add = document.createElement('div');
  add.className = 'img-add';
  add.innerHTML = '+';
  add.onclick = () => document.getElementById('fileInput').click();
  grid.appendChild(add);
}

async function quitarImg(img, id) {
  const fd = new FormData(); fd.append('id', id); fd.append('img', img);
  const r = await fetch(BASE+'remove_img', {method:'POST', body:fd});
  const d = await r.json();
  if (d.success) { editImagenes = editImagenes.filter(x=>x!==img); renderImgGrid(); toast('Imagen eliminada'); }
}

async function subirImagenes() {
  const id = document.getElementById('editId').value;
  const files = document.getElementById('fileInput').files;
  for (const file of files) {
    const fd = new FormData(); fd.append('imagen', file);
    const r = await fetch(BASE+'upload', {method:'POST', body:fd});
    const d = await r.json();
    if (d.success) {
      editImagenes.push(d.filename);
      const pros = JSON.parse(<?= json_encode(json_encode($productos)) ?>);
      const p = pros.find(x=>x.id==id);
      if (p) { p.imagenes = editImagenes; }
    }
  }
  const fd2 = new FormData();
  fd2.append('id', id); fd2.append('imagenes', JSON.stringify(editImagenes));
  renderImgGrid();
  document.getElementById('fileInput').value = '';
  toast('Imagen subida');
}

async function guardarProducto() {
  const id = document.getElementById('editId').value;
  const fd = new FormData();
  fd.append('id', id);
  fd.append('nombre', document.getElementById('editNombre').value);
  fd.append('descripcion', document.getElementById('editDescripcion').value);
  fd.append('precio', document.getElementById('editPrecio').value);
  fd.append('categoria', document.getElementById('editCategoria').value);
  fd.append('material', document.getElementById('editMaterial').value);
  fd.append('dimensiones', document.getElementById('editDimensiones').value);
  fd.append('peso', document.getElementById('editPeso').value);
  if (document.getElementById('editActivo').checked) fd.append('activo','1');
  if (document.getElementById('editDestacado').checked) fd.append('destacado','1');
  const r = await fetch(BASE+'save_producto', {method:'POST', body:fd});
  const d = await r.json();
  if (d.success) {
    cerrarModal();
    toast('Guardado correctamente');
    setTimeout(()=>location.reload(), 800);
  } else toast('Error al guardar', false);
}

function cerrarModal() { document.getElementById('modalOverlay').classList.remove('open'); }

/* ─ NUEVO ─ */
function abrirNuevo() { document.getElementById('modalNuevo').classList.add('open'); }

async function crearProducto() {
  const fd = new FormData();
  fd.append('nombre', document.getElementById('newNombre').value);
  fd.append('slug', document.getElementById('newSlug').value);
  fd.append('descripcion', document.getElementById('newDescripcion').value);
  fd.append('precio', document.getElementById('newPrecio').value);
  fd.append('categoria', document.getElementById('newCategoria').value);
  const r = await fetch(BASE+'nuevo_producto', {method:'POST', body:fd});
  const d = await r.json();
  if (d.success) { toast('Producto creado'); setTimeout(()=>location.reload(), 800); }
  else toast('Error', false);
}

/* ─ INICIO ─ */
async function guardarInicio() {
  const fd = new FormData();
  fd.append('anuncio', document.getElementById('anuncio')?.value || '');
  for (let i=0;i<3;i++) {
    fd.append('titulo_'+i, document.getElementById('titulo_'+i)?.value||'');
    fd.append('subtitulo_'+i, document.getElementById('subtitulo_'+i)?.value||'');
    fd.append('badge_'+i, document.getElementById('badge_'+i)?.value||'');
  }
  fd.append('nos_titulo', document.getElementById('nos_titulo')?.value||'');
  fd.append('nos_texto1', document.getElementById('nos_texto1')?.value||'');
  fd.append('nos_texto2', document.getElementById('nos_texto2')?.value||'');
  const r = await fetch(BASE+'save_config', {method:'POST', body:fd});
  const d = await r.json();
  toast(d.success ? '¡Guardado!' : 'Error al guardar', d.success);
}

async function subirHero(slot) {
  const file = document.getElementById('hero-file-'+slot).files[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('imagen', file);
  fd.append('slot', slot+1);
  const r = await fetch(BASE+'upload_hero', {method:'POST', body:fd});
  const d = await r.json();
  if (d.success) {
    const prev = document.getElementById('hero-preview-'+slot);
    prev.outerHTML = `<img src="/assets/img/${d.filename}?t=${Date.now()}" class="hero-img-preview" id="hero-preview-${slot}">`;
    toast('Imagen actualizada');
  } else toast('Error al subir', false);
}
</script>
</body>
</html>
