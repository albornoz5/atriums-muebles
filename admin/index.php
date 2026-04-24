<?php
session_start();

define('ADMIN_PASS', 'atriums2024');
define('DATA_DIR',   realpath(__DIR__ . '/../data') . '/');
define('IMG_DIR',    realpath(__DIR__ . '/../assets/img/productos') . '/');
define('HERO_DIR',   realpath(__DIR__ . '/../assets/img') . '/');

/* ═══════════════════════════════════════════════════════════════
   AJAX HANDLERS
═══════════════════════════════════════════════════════════════ */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['admin'])) { echo json_encode(['error'=>'No autorizado']); exit; }

    $productos = json_decode(file_get_contents(DATA_DIR.'productos.json'), true) ?? [];

    switch ($_GET['ajax']) {

        case 'toggle':
            $id = intval($_POST['id'] ?? 0);
            $activo = true;
            foreach ($productos as &$p) {
                if ((int)$p['id'] === $id) {
                    $p['activo'] = !($p['activo'] ?? true);
                    $activo = $p['activo'];
                    break;
                }
            }
            file_put_contents(DATA_DIR.'productos.json',
                json_encode($productos, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            echo json_encode(['success'=>true, 'activo'=>$activo]);
            break;

        case 'eliminar':
            $id = intval($_POST['id'] ?? 0);
            $productos = array_values(array_filter($productos, fn($p)=>(int)$p['id'] !== $id));
            file_put_contents(DATA_DIR.'productos.json',
                json_encode($productos, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            echo json_encode(['success'=>true]);
            break;

        case 'upload':
            $file    = $_FILES['imagen'] ?? null;
            if (!$file) { echo json_encode(['error'=>'Sin archivo']); break; }
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            if (!in_array($ext, $allowed))  { echo json_encode(['error'=>'Tipo no permitido']); break; }
            if ($file['size'] > 8*1024*1024){ echo json_encode(['error'=>'Máximo 8 MB']); break; }
            $fn = 'img_'.uniqid().'.'.$ext;
            if (!is_dir(IMG_DIR)) mkdir(IMG_DIR, 0755, true);
            move_uploaded_file($file['tmp_name'], IMG_DIR.$fn);
            echo json_encode(['success'=>true, 'url'=>'assets/img/productos/'.$fn]);
            break;

        case 'upload_hero':
            $file = $_FILES['imagen'] ?? null;
            if (!$file) { echo json_encode(['error'=>'Sin archivo']); break; }
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) { echo json_encode(['error'=>'Tipo no permitido']); break; }
            $slot = max(1, min(3, intval($_POST['slot'] ?? 1)));
            $fn   = 'hero-'.$slot.'.'.$ext;
            move_uploaded_file($file['tmp_name'], HERO_DIR.$fn);
            echo json_encode(['success'=>true, 'url'=>'assets/img/'.$fn]);
            break;

        case 'remove_img':
            $id   = intval($_POST['id'] ?? 0);
            $url  = trim($_POST['url'] ?? '');
            foreach ($productos as &$p) {
                if ((int)$p['id'] === $id) {
                    $p['imagenes'] = array_values(array_filter(
                        $p['imagenes'] ?? [], fn($i)=>$i !== $url));
                    break;
                }
            }
            file_put_contents(DATA_DIR.'productos.json',
                json_encode($productos, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            echo json_encode(['success'=>true]);
            break;
    }
    exit;
}

/* ═══════════════════════════════════════════════════════════════
   FORM POST HANDLERS
═══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Login
    if ($action === 'login') {
        if ($_POST['password'] === ADMIN_PASS) {
            $_SESSION['admin'] = true;
            header('Location: index.php'); exit;
        }
        $loginError = 'Contraseña incorrecta.';
    }

    if (!empty($_SESSION['admin'])) {
        $productos = json_decode(file_get_contents(DATA_DIR.'productos.json'), true) ?? [];

        // ── Guardar producto existente ─────────────────────────────────────
        if ($action === 'save_producto') {
            $id = intval($_POST['id']);
            foreach ($productos as &$p) {
                if ((int)$p['id'] !== $id) continue;
                $p['nombre']        = trim($_POST['nombre']        ?? '');
                $p['descripcion']   = trim($_POST['descripcion']   ?? '');
                $p['precio']        = trim($_POST['precio']        ?? '');
                $p['precio_cuotas'] = trim($_POST['precio_cuotas'] ?? '');
                $p['precio_promo']  = trim($_POST['precio_promo']  ?? '');
                $p['categoria']     = trim($_POST['categoria']     ?? '');
                $p['orden']         = intval($_POST['orden']       ?? $p['orden']);
                $p['activo']        = isset($_POST['activo']);
                // Imágenes existentes
                $imgs = json_decode($_POST['imagenes_json'] ?? '[]', true) ?: [];
                // Nuevas subidas
                if (!empty($_FILES['imagenes_nuevas']['tmp_name'][0])) {
                    foreach ($_FILES['imagenes_nuevas']['tmp_name'] as $k => $tmp) {
                        if (!$tmp) continue;
                        $ext = strtolower(pathinfo($_FILES['imagenes_nuevas']['name'][$k], PATHINFO_EXTENSION));
                        $fn  = 'img_'.uniqid().'.'.$ext;
                        if (move_uploaded_file($tmp, IMG_DIR.$fn))
                            $imgs[] = 'assets/img/productos/'.$fn;
                    }
                }
                $p['imagenes'] = $imgs;
                break;
            }
            file_put_contents(DATA_DIR.'productos.json',
                json_encode($productos, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            header('Location: index.php?section=productos&ok=1'); exit;
        }

        // ── Nuevo producto ─────────────────────────────────────────────────
        if ($action === 'nuevo_producto') {
            $maxId    = $productos ? max(array_column($productos, 'id'))    : 0;
            $maxOrden = $productos ? max(array_column($productos, 'orden')) : 0;
            $imgs = [];
            if (!empty($_FILES['imagenes_nuevas']['tmp_name'][0])) {
                foreach ($_FILES['imagenes_nuevas']['tmp_name'] as $k => $tmp) {
                    if (!$tmp) continue;
                    $ext = strtolower(pathinfo($_FILES['imagenes_nuevas']['name'][$k], PATHINFO_EXTENSION));
                    $fn  = 'img_'.uniqid().'.'.$ext;
                    if (move_uploaded_file($tmp, IMG_DIR.$fn))
                        $imgs[] = 'assets/img/productos/'.$fn;
                }
            }
            $productos[] = [
                'id'           => $maxId + 1,
                'slug'         => trim($_POST['slug'] ?? ''),
                'nombre'       => trim($_POST['nombre'] ?? ''),
                'descripcion'  => trim($_POST['descripcion'] ?? ''),
                'precio'       => trim($_POST['precio'] ?? ''),
                'precio_cuotas'=> trim($_POST['precio_cuotas'] ?? ''),
                'precio_promo' => trim($_POST['precio_promo'] ?? ''),
                'categoria'    => trim($_POST['categoria'] ?? ''),
                'orden'        => intval($_POST['orden'] ?? ($maxOrden + 1)),
                'activo'       => true,
                'imagenes'     => $imgs,
            ];
            file_put_contents(DATA_DIR.'productos.json',
                json_encode($productos, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            header('Location: index.php?section=productos&ok=1'); exit;
        }

        // ── Guardar config inicio ──────────────────────────────────────────
        if ($action === 'save_config') {
            $cfg = ['hero'=>['slides'=>[]], 'nosotros'=>[]];
            for ($i = 0; $i < 3; $i++) {
                $cfg['hero']['slides'][] = [
                    'badge'    => trim($_POST["s{$i}_badge"]    ?? ''),
                    'titulo'   => trim($_POST["s{$i}_titulo"]   ?? ''),
                    'subtitulo'=> trim($_POST["s{$i}_subtitulo"] ?? ''),
                    'imagen'   => trim($_POST["s{$i}_imagen"]   ?? ''),
                ];
            }
            $cfg['nosotros'] = [
                'titulo' => trim($_POST['nos_titulo'] ?? ''),
                'texto1' => trim($_POST['nos_texto1'] ?? ''),
                'texto2' => trim($_POST['nos_texto2'] ?? ''),
            ];
            file_put_contents(DATA_DIR.'config.json',
                json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            header('Location: index.php?section=inicio&ok=1'); exit;
        }
    }
}

// Logout
if (($_GET['action'] ?? '') === 'logout') {
    session_destroy();
    header('Location: index.php'); exit;
}

/* ═══════════════════════════════════════════════════════════════
   LOGIN PAGE
═══════════════════════════════════════════════════════════════ */
if (empty($_SESSION['admin'])) { ?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin – Atriums Muebles</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#f0ebe4;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;border-radius:14px;padding:48px 40px;width:100%;max-width:380px;box-shadow:0 4px 24px rgba(0,0,0,.12)}
h1{font-size:1.6rem;color:#1a1a1a;margin-bottom:6px}h1 span{color:#A0522D}
.sub{color:#888;font-size:.9rem;margin-bottom:32px}
label{display:block;font-size:.83rem;font-weight:600;color:#444;margin-bottom:6px;margin-top:18px}
input{width:100%;padding:12px 16px;border:2px solid #ddd;border-radius:8px;font-size:1rem;outline:none;transition:.2s}
input:focus{border-color:#A0522D}
button{width:100%;padding:14px;background:#A0522D;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;margin-top:24px;transition:.2s}
button:hover{background:#7d3f1f}
.err{color:#c0392b;font-size:.85rem;margin-top:14px;text-align:center;padding:10px;background:#fdf2f2;border-radius:6px}
</style></head><body>
<div class="card">
  <h1><span>Atriums</span> Admin</h1>
  <p class="sub">Panel de administración del sitio</p>
  <form method="post">
    <input type="hidden" name="action" value="login">
    <label>Contraseña</label>
    <input type="password" name="password" autofocus required placeholder="••••••••">
    <?php if (!empty($loginError)) echo "<p class='err'>$loginError</p>"; ?>
    <button type="submit">Ingresar</button>
  </form>
</div>
</body></html>
<?php exit; }

/* ═══════════════════════════════════════════════════════════════
   CARGAR DATOS
═══════════════════════════════════════════════════════════════ */
$productos = json_decode(file_get_contents(DATA_DIR.'productos.json'), true) ?? [];
usort($productos, fn($a,$b)=>($a['orden']??999)-($b['orden']??999));

$cfgFile = DATA_DIR.'config.json';
$cfg     = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) : [];

$heroSlides = $cfg['hero']['slides'] ?? [
    ['badge'=>'Fábrica Propia · Stock Permanente','titulo'=>'Muebles de Algarrobo','subtitulo'=>'Diseñados y fabricados en el Chaco.','imagen'=>'assets/img/hero-1.jpg'],
    ['badge'=>'21 Modelos','titulo'=>'Comedores que reúnen a la familia','subtitulo'=>'Mesas desde 1.20 m hasta 3 m.','imagen'=>'assets/img/hero-2.jpg'],
    ['badge'=>'Living · Dormitorio','titulo'=>'Tu hogar con el alma del Algarrobo','subtitulo'=>'Desde bahiuts y cristaleros hasta camas.','imagen'=>'assets/img/hero-3.jpg'],
];
$nosotros = $cfg['nosotros'] ?? [
    'titulo'=>'Más de 20 años fabricando muebles con alma chaqueña',
    'texto1'=>'En Atriums Muebles fabricamos cada pieza con madera de Algarrobo seleccionada.',
    'texto2'=>'Contamos con stock permanente en nuestros locales de Resistencia, Machagai y Corrientes.',
];

$section = $_GET['section'] ?? 'productos';
$editId  = isset($_GET['edit']) ? intval($_GET['edit']) : null;
$isAdd   = isset($_GET['add']);

$editP = null;
if ($editId) foreach ($productos as $p) { if ((int)$p['id']===$editId){$editP=$p;break;} }

$cats = ['comedores'=>'Juegos de Comedor','sillas'=>'Sillas','mesas'=>'Mesas','living'=>'Living','dormitorio'=>'Dormitorio'];

/* ═══════════════════════════════════════════════════════════════
   HTML PANEL
═══════════════════════════════════════════════════════════════ */
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin – Atriums Muebles</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#f5f2ee;color:#1a1a1a;display:flex;min-height:100vh}

/* ── Sidebar ── */
.sidebar{width:220px;flex-shrink:0;background:#0d0d0d;display:flex;flex-direction:column;padding:0 0 24px}
.sidebar-logo{padding:24px 20px 20px;border-bottom:1px solid rgba(255,255,255,.08)}
.sidebar-logo span{color:#A0522D;font-weight:800;font-size:1.1rem;letter-spacing:.04em}
.sidebar-logo small{display:block;color:rgba(255,255,255,.35);font-size:.7rem;margin-top:2px}
.sidebar nav{padding:16px 12px;flex:1}
.sidebar nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;color:rgba(255,255,255,.7);text-decoration:none;border-radius:8px;font-size:.88rem;font-weight:500;margin-bottom:4px;transition:.18s}
.sidebar nav a:hover,.sidebar nav a.active{background:rgba(160,82,45,.25);color:#fff}
.sidebar nav a svg{flex-shrink:0;opacity:.7}
.sidebar nav a.active svg{opacity:1}
.sidebar-foot{padding:12px}
.sidebar-foot a{display:flex;align-items:center;gap:8px;color:rgba(255,255,255,.4);font-size:.8rem;text-decoration:none;padding:8px 12px;border-radius:6px;transition:.18s}
.sidebar-foot a:hover{color:#fff;background:rgba(255,255,255,.06)}

/* ── Main ── */
.main{flex:1;overflow:auto;padding:32px}
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px}
.topbar h1{font-size:1.3rem;font-weight:700}
.topbar small{color:#888;font-size:.82rem;display:block;margin-top:2px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:7px;border:none;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;transition:.18s}
.btn-primary{background:#A0522D;color:#fff}.btn-primary:hover{background:#7d3f1f}
.btn-danger{background:#e74c3c;color:#fff}.btn-danger:hover{background:#c0392b}
.btn-ghost{background:#fff;color:#333;border:1.5px solid #ddd}.btn-ghost:hover{border-color:#A0522D;color:#A0522D}
.btn-sm{padding:6px 13px;font-size:.78rem}
.ok-banner{background:#d4edda;color:#155724;padding:12px 18px;border-radius:8px;font-size:.88rem;margin-bottom:20px;border:1px solid #c3e6cb}

/* ── Table ── */
.table-wrap{background:#fff;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,.07);overflow:hidden}
table{width:100%;border-collapse:collapse}
th{text-align:left;padding:12px 16px;font-size:.75rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#888;background:#fafafa;border-bottom:1px solid #eee}
td{padding:12px 16px;border-bottom:1px solid #f0f0f0;vertical-align:middle;font-size:.88rem}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fdf9f6}
.thumb{width:52px;height:52px;object-fit:cover;border-radius:6px;background:#f0ebe4}
.thumb-placeholder{width:52px;height:52px;background:#f0ebe4;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:.65rem}
.badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:.72rem;font-weight:600}
.badge-activo{background:#d4edda;color:#155724}
.badge-inactivo{background:#f8d7da;color:#721c24}

/* ── Toggle switch ── */
.toggle{position:relative;display:inline-block;width:40px;height:22px}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:#ccc;border-radius:22px;cursor:pointer;transition:.25s}
.toggle-slider::before{content:'';position:absolute;width:16px;height:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.25s}
.toggle input:checked+.toggle-slider{background:#27ae60}
.toggle input:checked+.toggle-slider::before{transform:translateX(18px)}

/* ── Forms ── */
.form-card{background:#fff;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,.07);padding:28px 32px;max-width:780px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group.full{grid-column:1/-1}
.form-group label{font-size:.82rem;font-weight:600;color:#444}
.form-group input,.form-group textarea,.form-group select{padding:10px 13px;border:2px solid #e8e0d8;border-radius:7px;font-size:.92rem;outline:none;font-family:inherit;transition:.18s;background:#fff;color:#1a1a1a}
.form-group input:focus,.form-group textarea:focus,.form-group select:focus{border-color:#A0522D}
.form-group textarea{resize:vertical;min-height:80px}
.form-hint{font-size:.76rem;color:#999;margin-top:2px}
.form-actions{display:flex;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid #f0f0f0}
.section-sep{height:1px;background:#f0f0f0;margin:28px 0}

/* ── Images ── */
.imgs-wrap{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px}
.img-item{position:relative;width:90px;height:90px}
.img-item img{width:100%;height:100%;object-fit:cover;border-radius:8px;border:2px solid #e8e0d8}
.img-del{position:absolute;top:-6px;right:-6px;width:20px;height:20px;background:#e74c3c;color:#fff;border:none;border-radius:50%;cursor:pointer;font-size:.75rem;display:flex;align-items:center;justify-content:center;line-height:1}
.img-del:hover{background:#c0392b}
.upload-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:2px dashed #ccc;border-radius:8px;cursor:pointer;color:#888;font-size:.82rem;transition:.18s;background:none}
.upload-btn:hover{border-color:#A0522D;color:#A0522D}

/* ── Hero slides editor ── */
.slide-block{background:#fdf9f6;border:1px solid #e8e0d8;border-radius:10px;padding:20px;margin-bottom:16px}
.slide-block h4{font-size:.85rem;font-weight:700;color:#A0522D;margin-bottom:14px}
.hero-preview{width:100%;height:120px;object-fit:cover;border-radius:7px;margin-bottom:10px;background:#e8ddd0}
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:28px}
.stat-card{background:#fff;border-radius:10px;padding:18px 20px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.stat-card .num{font-size:1.8rem;font-weight:800;color:#A0522D}
.stat-card .lbl{font-size:.78rem;color:#888;margin-top:2px}

@media(max-width:900px){
  .sidebar{width:60px}.sidebar-logo small,.sidebar nav a span,.sidebar-foot a span{display:none}
  .sidebar-logo{padding:20px 16px}.main{padding:20px}
  .form-grid{grid-template-columns:1fr}.stats-grid{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <span>ATRIUMS</span>
    <small>Panel Admin</small>
  </div>
  <nav>
    <a href="?section=productos" class="<?= $section==='productos'?'active':'' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
      <span>Productos</span>
    </a>
    <a href="?section=inicio" class="<?= $section==='inicio'?'active':'' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
      <span>Inicio</span>
    </a>
    <a href="../index.html" target="_blank">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 19H5V5h7V3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
      <span>Ver sitio</span>
    </a>
  </nav>
  <div class="sidebar-foot">
    <a href="?action=logout">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
      <span>Salir</span>
    </a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">

<?php if (isset($_GET['ok'])): ?>
<div class="ok-banner">✓ Cambios guardados correctamente.</div>
<?php endif; ?>

<?php
/* ════════════════════════════════════════
   SECCIÓN: PRODUCTOS
════════════════════════════════════════ */
if ($section === 'productos' && !$editP && !$isAdd):
    $total    = count($productos);
    $activos  = count(array_filter($productos, fn($p)=>($p['activo']??true)));
    $inactivos= $total - $activos;
?>

<div class="topbar">
  <div>
    <h1>Productos</h1>
    <small><?= $total ?> productos · <?= $activos ?> visibles · <?= $inactivos ?> ocultos</small>
  </div>
  <a href="?section=productos&add=1" class="btn btn-primary">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
    Nuevo producto
  </a>
</div>

<div class="stats-grid">
  <div class="stat-card"><div class="num"><?= $total ?></div><div class="lbl">Total productos</div></div>
  <div class="stat-card"><div class="num"><?= $activos ?></div><div class="lbl">Visibles en el sitio</div></div>
  <div class="stat-card"><div class="num"><?= $inactivos ?></div><div class="lbl">Ocultos</div></div>
</div>

<div class="table-wrap">
<table>
  <thead><tr>
    <th>Foto</th><th>Producto</th><th>Precio</th><th>Categoría</th><th>Visible</th><th>Acciones</th>
  </tr></thead>
  <tbody id="tabla-productos">
  <?php foreach ($productos as $p):
    $activo = $p['activo'] ?? true;
    $img    = $p['imagenes'][0] ?? '';
  ?>
  <tr id="row-<?= $p['id'] ?>">
    <td>
      <?php if ($img): ?>
        <img src="../<?= htmlspecialchars($img) ?>" class="thumb" onerror="this.style.display='none'">
      <?php else: ?>
        <div class="thumb-placeholder">sin foto</div>
      <?php endif; ?>
    </td>
    <td>
      <strong><?= htmlspecialchars($p['nombre']) ?></strong>
      <div style="color:#999;font-size:.76rem;margin-top:2px">#<?= $p['id'] ?> · orden <?= $p['orden'] ?></div>
    </td>
    <td style="white-space:nowrap">
      <div style="font-weight:700"><?= htmlspecialchars($p['precio']) ?></div>
      <?php if (!empty($p['precio_promo'])): ?>
        <div style="color:#888;font-size:.76rem"><?= htmlspecialchars($p['precio_promo']) ?> ef.</div>
      <?php endif; ?>
    </td>
    <td><?= htmlspecialchars($cats[$p['categoria']] ?? $p['categoria']) ?></td>
    <td>
      <label class="toggle" title="<?= $activo?'Visible':'Oculto' ?>">
        <input type="checkbox" <?= $activo?'checked':'' ?>
               onchange="toggleActivo(<?= $p['id'] ?>, this)">
        <span class="toggle-slider"></span>
      </label>
    </td>
    <td>
      <a href="?section=productos&edit=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
      <button class="btn btn-danger btn-sm" style="margin-left:6px"
              onclick="eliminarProducto(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['nombre'])) ?>')">
        Eliminar
      </button>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php
/* ════════════════════════════════════════
   EDITAR PRODUCTO
════════════════════════════════════════ */
elseif ($section === 'productos' && $editP):
?>

<div class="topbar">
  <div>
    <h1>Editar producto</h1>
    <small>#<?= $editP['id'] ?> — <?= htmlspecialchars($editP['nombre']) ?></small>
  </div>
  <a href="?section=productos" class="btn btn-ghost">← Volver</a>
</div>

<div class="form-card">
<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="action" value="save_producto">
  <input type="hidden" name="id" value="<?= $editP['id'] ?>">
  <input type="hidden" name="imagenes_json" id="imagenes_json"
         value="<?= htmlspecialchars(json_encode($editP['imagenes'] ?? [])) ?>">

  <div class="form-grid">

    <div class="form-group full">
      <label>Nombre del producto</label>
      <input type="text" name="nombre" value="<?= htmlspecialchars($editP['nombre']) ?>" required>
    </div>

    <div class="form-group full">
      <label>Descripción</label>
      <textarea name="descripcion" rows="4"><?= htmlspecialchars($editP['descripcion'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label>Precio (con $)</label>
      <input type="text" name="precio" value="<?= htmlspecialchars($editP['precio'] ?? '') ?>" placeholder="$1.500.000">
    </div>

    <div class="form-group">
      <label>Precio promo (efectivo/transf.)</label>
      <input type="text" name="precio_promo" value="<?= htmlspecialchars($editP['precio_promo'] ?? '') ?>" placeholder="$1.200.000">
    </div>

    <div class="form-group">
      <label>Cuota (12 cuotas sin interés)</label>
      <input type="text" name="precio_cuotas" value="<?= htmlspecialchars($editP['precio_cuotas'] ?? '') ?>" placeholder="$125.000">
    </div>

    <div class="form-group">
      <label>Categoría</label>
      <select name="categoria">
        <?php foreach ($cats as $k=>$v): ?>
          <option value="<?= $k ?>" <?= ($editP['categoria']===$k)?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>Orden en el catálogo</label>
      <input type="number" name="orden" value="<?= $editP['orden'] ?? 99 ?>" min="1">
    </div>

    <div class="form-group">
      <label>Visible en el sitio</label>
      <label style="display:flex;align-items:center;gap:10px;margin-top:4px">
        <input type="checkbox" name="activo" style="width:18px;height:18px" <?= ($editP['activo']??true)?'checked':'' ?>>
        <span style="font-size:.9rem;font-weight:normal">Mostrar en el catálogo</span>
      </label>
    </div>

    <!-- Imágenes actuales -->
    <div class="form-group full">
      <label>Fotos actuales</label>
      <div class="imgs-wrap" id="imgs-wrap">
        <?php foreach ($editP['imagenes'] ?? [] as $img): ?>
        <div class="img-item" id="img-<?= md5($img) ?>">
          <img src="../<?= htmlspecialchars($img) ?>" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2290%22 height=%2290%22><rect fill=%22%23f0ebe4%22 width=%2290%22 height=%2290%22/></svg>'">
          <button type="button" class="img-del"
                  onclick="removeImg('<?= addslashes($img) ?>', <?= $editP['id'] ?>)">✕</button>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="form-hint">Hacé clic en ✕ para eliminar una foto</p>
    </div>

    <!-- Subir nuevas fotos -->
    <div class="form-group full">
      <label>Agregar fotos</label>
      <input type="file" name="imagenes_nuevas[]" multiple accept="image/*"
             style="display:none" id="file-input"
             onchange="previewNewFiles(this)">
      <button type="button" class="upload-btn" onclick="document.getElementById('file-input').click()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z"/></svg>
        Seleccionar fotos (JPG, PNG, WEBP)
      </button>
      <div class="imgs-wrap" id="new-imgs-wrap"></div>
      <p class="form-hint">Podés seleccionar varias a la vez. Se agregan a las existentes.</p>
    </div>

  </div><!-- .form-grid -->

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Guardar cambios</button>
    <a href="?section=productos" class="btn btn-ghost">Cancelar</a>
  </div>
</form>
</div>

<?php
/* ════════════════════════════════════════
   NUEVO PRODUCTO
════════════════════════════════════════ */
elseif ($section === 'productos' && $isAdd):
    $nextOrden = $productos ? max(array_column($productos, 'orden')) + 1 : 1;
?>

<div class="topbar">
  <div><h1>Nuevo producto</h1></div>
  <a href="?section=productos" class="btn btn-ghost">← Volver</a>
</div>

<div class="form-card">
<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="action" value="nuevo_producto">

  <div class="form-grid">

    <div class="form-group full">
      <label>Nombre del producto *</label>
      <input type="text" name="nombre" required placeholder="Ej: Mesa 1.60 + 6 sillas Juliana">
    </div>

    <div class="form-group full">
      <label>Descripción</label>
      <textarea name="descripcion" rows="4" placeholder="Descripción del producto..."></textarea>
    </div>

    <div class="form-group">
      <label>Precio (con $)</label>
      <input type="text" name="precio" placeholder="$1.500.000">
    </div>

    <div class="form-group">
      <label>Precio promo (efectivo/transf.)</label>
      <input type="text" name="precio_promo" placeholder="$1.200.000">
    </div>

    <div class="form-group">
      <label>Cuota (12 cuotas sin interés)</label>
      <input type="text" name="precio_cuotas" placeholder="$125.000">
    </div>

    <div class="form-group">
      <label>Categoría</label>
      <select name="categoria">
        <?php foreach ($cats as $k=>$v): ?>
          <option value="<?= $k ?>"><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>Orden en el catálogo</label>
      <input type="number" name="orden" value="<?= $nextOrden ?>" min="1">
    </div>

    <div class="form-group">
      <label>Slug (URL)</label>
      <input type="text" name="slug" placeholder="mesa-160-6-juliana">
      <span class="form-hint">Sin espacios ni tildes, usar guiones</span>
    </div>

    <div class="form-group full">
      <label>Fotos del producto</label>
      <input type="file" name="imagenes_nuevas[]" multiple accept="image/*"
             style="display:none" id="file-input-new" onchange="previewNewFiles(this)">
      <button type="button" class="upload-btn" onclick="document.getElementById('file-input-new').click()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z"/></svg>
        Seleccionar fotos
      </button>
      <div class="imgs-wrap" id="new-imgs-wrap"></div>
    </div>

  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Crear producto</button>
    <a href="?section=productos" class="btn btn-ghost">Cancelar</a>
  </div>
</form>
</div>

<?php
/* ════════════════════════════════════════
   SECCIÓN: INICIO
════════════════════════════════════════ */
elseif ($section === 'inicio'):
?>

<div class="topbar">
  <div>
    <h1>Editar página de inicio</h1>
    <small>Hero, sección Nosotros</small>
  </div>
</div>

<form method="post" id="form-inicio">
<input type="hidden" name="action" value="save_config">

<div class="form-card">
  <h3 style="margin-bottom:20px;font-size:1rem">🖼 Hero (banner principal)</h3>
  <p style="color:#888;font-size:.83rem;margin-bottom:20px">
    El hero muestra 3 slides rotativos. Editá el texto de cada uno y subí las fotos de fondo.
  </p>

  <?php for ($i = 0; $i < 3; $i++):
    $sl = $heroSlides[$i] ?? [];
  ?>
  <div class="slide-block">
    <h4>Slide <?= $i+1 ?></h4>

    <?php if (!empty($sl['imagen'])): ?>
      <img src="../<?= htmlspecialchars($sl['imagen']) ?>"
           class="hero-preview" id="hero-prev-<?= $i ?>"
           onerror="this.style.display='none'">
    <?php else: ?>
      <div class="hero-preview" id="hero-prev-<?= $i ?>"
           style="display:flex;align-items:center;justify-content:center;color:#bbb;font-size:.8rem">
        Sin imagen — subí una foto de fondo
      </div>
    <?php endif; ?>

    <input type="hidden" name="s<?= $i ?>_imagen" id="s<?= $i ?>_imagen"
           value="<?= htmlspecialchars($sl['imagen'] ?? '') ?>">

    <div style="margin-bottom:12px">
      <button type="button" class="upload-btn"
              onclick="uploadHero(<?= $i ?>)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z"/></svg>
        Cambiar foto de fondo
      </button>
      <input type="file" id="hero-file-<?= $i ?>" accept="image/jpeg,image/png,image/webp" style="display:none">
    </div>

    <div class="form-grid" style="margin-top:0">
      <div class="form-group full">
        <label>Frase badge (texto pequeño arriba del título)</label>
        <input type="text" name="s<?= $i ?>_badge"
               value="<?= htmlspecialchars($sl['badge'] ?? '') ?>"
               placeholder="Ej: Fábrica Propia · Stock Permanente">
      </div>
      <div class="form-group full">
        <label>Título principal</label>
        <input type="text" name="s<?= $i ?>_titulo"
               value="<?= htmlspecialchars($sl['titulo'] ?? '') ?>"
               placeholder="Muebles de Algarrobo">
        <span class="form-hint">Podés usar \n para salto de línea</span>
      </div>
      <div class="form-group full">
        <label>Subtítulo</label>
        <textarea name="s<?= $i ?>_subtitulo" rows="2"><?= htmlspecialchars($sl['subtitulo'] ?? '') ?></textarea>
      </div>
    </div>
  </div>
  <?php endfor; ?>

  <div class="section-sep"></div>
  <h3 style="margin-bottom:20px;font-size:1rem">👋 Sección "Sobre nosotros"</h3>

  <div class="form-grid">
    <div class="form-group full">
      <label>Título</label>
      <input type="text" name="nos_titulo"
             value="<?= htmlspecialchars($nosotros['titulo'] ?? '') ?>">
    </div>
    <div class="form-group full">
      <label>Primer párrafo</label>
      <textarea name="nos_texto1" rows="3"><?= htmlspecialchars($nosotros['texto1'] ?? '') ?></textarea>
    </div>
    <div class="form-group full">
      <label>Segundo párrafo</label>
      <textarea name="nos_texto2" rows="3"><?= htmlspecialchars($nosotros['texto2'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Guardar cambios</button>
  </div>
</div><!-- .form-card -->
</form>

<?php endif; ?>

</main><!-- .main -->

<!-- ═══ JAVASCRIPT ═══ -->
<script>
/* Toggle activo */
function toggleActivo(id, el) {
  fetch('?ajax=toggle', {
    method: 'POST',
    body: new URLSearchParams({ id }),
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
  })
  .then(r => r.json())
  .then(d => {
    if (!d.success) { el.checked = !el.checked; alert('Error al guardar'); }
  });
}

/* Eliminar producto */
function eliminarProducto(id, nombre) {
  if (!confirm(`¿Eliminar "${nombre}"?\n\nEsta acción no se puede deshacer.`)) return;
  fetch('?ajax=eliminar', {
    method: 'POST',
    body: new URLSearchParams({ id }),
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      const row = document.getElementById('row-' + id);
      if (row) row.remove();
    } else {
      alert('Error al eliminar');
    }
  });
}

/* Preview fotos nuevas antes de subir */
function previewNewFiles(input) {
  const wrap = document.getElementById('new-imgs-wrap');
  if (!wrap) return;
  Array.from(input.files).forEach(file => {
    const reader = new FileReader();
    reader.onload = e => {
      const div = document.createElement('div');
      div.className = 'img-item';
      div.innerHTML = `<img src="${e.target.result}" style="width:90px;height:90px;object-fit:cover;border-radius:8px">`;
      wrap.appendChild(div);
    };
    reader.readAsDataURL(file);
  });
}

/* Eliminar imagen existente */
function removeImg(url, productId) {
  if (!confirm('¿Eliminar esta foto?')) return;
  fetch('?ajax=remove_img', {
    method: 'POST',
    body: new URLSearchParams({ id: productId, url }),
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      const key = md5simple(url);
      // update hidden field
      const hidden = document.getElementById('imagenes_json');
      if (hidden) {
        const imgs = JSON.parse(hidden.value || '[]').filter(i => i !== url);
        hidden.value = JSON.stringify(imgs);
      }
      // remove visual element (find by data or use parent)
      document.querySelectorAll('.img-item').forEach(el => {
        if (el.querySelector('img')?.getAttribute('src')?.includes(url.split('/').pop())) {
          el.remove();
        }
      });
    }
  });
}

/* Subir foto de hero */
function uploadHero(slot) {
  const input = document.getElementById('hero-file-' + slot);
  input.onchange = () => {
    const fd = new FormData();
    fd.append('imagen', input.files[0]);
    fd.append('slot', slot + 1);
    fetch('?ajax=upload_hero', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          document.getElementById('s' + slot + '_imagen').value = d.url;
          const prev = document.getElementById('hero-prev-' + slot);
          if (prev) { prev.src = '../' + d.url; prev.style.display = ''; }
          alert('Foto subida. Guardá los cambios para aplicar.');
        } else {
          alert('Error: ' + (d.error || 'Error al subir'));
        }
      });
  };
  input.click();
}

/* md5 simple para claves (solo para UI, no seguridad) */
function md5simple(str) { return btoa(str).replace(/[^a-zA-Z0-9]/g,'').substring(0,12); }
</script>

</body></html>
