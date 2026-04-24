<?php
if (($_POST['pass'] ?? '') !== 'atriums2024') {
    if (isset($_POST['pass'])) { echo '<p style="color:red">Contraseña incorrecta</p>'; }
    ?>
    <form method="post" enctype="multipart/form-data">
        <input type="password" name="pass" placeholder="Contraseña" required>
        <input type="file" name="files[]" multiple accept="image/*" required>
        <select name="destino">
            <option value="productos">Fotos de productos (img/productos/)</option>
            <option value="hero">Fotos hero (img/)</option>
        </select>
        <button type="submit">Subir</button>
    </form>
    <?php
    exit;
}

$destino = $_POST['destino'] === 'hero'
    ? __DIR__ . '/assets/img/'
    : __DIR__ . '/data/img/';

$ok = []; $err = [];
foreach ($_FILES['files']['name'] as $i => $name) {
    if ($_FILES['files']['error'][$i] !== 0) { $err[] = $name; continue; }
    $target = $destino . basename($name);
    if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $target)) {
        $ok[] = $name;
    } else {
        $err[] = $name . ' (error al mover)';
    }
}

echo '<h3>Subidas OK (' . count($ok) . '):</h3><pre>' . implode("\n", $ok) . '</pre>';
if ($err) echo '<h3 style="color:red">Errores (' . count($err) . '):</h3><pre>' . implode("\n", $err) . '</pre>';
echo '<br><a href="upload-init.php">Subir más</a> | <a href="/">Ver sitio</a>';
