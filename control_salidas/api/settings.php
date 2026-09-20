<?php
require __DIR__ . '/_bootstrap.php';
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$userId = (int)$_SESSION['user_id'];

$allowedFondos = ['fondo_oscuro1','fondo_oscuro2','fondo_oscuro3','fondo_claro1','fondo_claro2','fondo_claro3','sin_fondo'];
$allowedModos = ['claro','oscuro'];
$allowedColores = ['azul','rojo'];

function ensureUserSettings(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT * FROM configuraciones_usuario WHERE usuario_id=? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row) {
        // Migra únicamente una fila que fue creada con los valores predeterminados
        // de la primera entrega de V3.12 y que nunca había sido modificada.
        if ((string)$row['fondo'] === 'fondo_oscuro1' && (string)$row['modo'] === 'oscuro'
            && (string)$row['creado_en'] === (string)$row['actualizado_en']) {
            $migrate = $pdo->prepare("UPDATE configuraciones_usuario SET fondo='fondo_claro1', modo='claro' WHERE usuario_id=?");
            $migrate->execute([$userId]);
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
        }
        return $row ?: [];
    }
    $stmt = $pdo->prepare("INSERT INTO configuraciones_usuario (usuario_id,fondo,modo,imagenes_decorativas,color_institucional) VALUES (?, 'fondo_claro1','claro',1,'azul')");
    $stmt->execute([$userId]);
    $stmt = $pdo->prepare('SELECT * FROM configuraciones_usuario WHERE usuario_id=? LIMIT 1');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: [];
}

if ($method === 'GET') {
    $row = ensureUserSettings($pdo, $userId);
    respond(['ok'=>true,'data'=>[
        'fondo'=>(string)$row['fondo'],
        'modo'=>(string)$row['modo'],
        'imagenes_decorativas'=>(int)$row['imagenes_decorativas'],
        'color_institucional'=>(string)$row['color_institucional'],
    ]]);
}

if ($method !== 'PUT' && $method !== 'POST') respond(['ok'=>false,'error'=>'Método no soportado.'],405);
$d = jsonInput();
$fondo = (string)($d['fondo'] ?? '');
$modo = (string)($d['modo'] ?? '');
$decor = (int)($d['imagenes_decorativas'] ?? -1);
$color = (string)($d['color_institucional'] ?? '');
if (!in_array($fondo,$allowedFondos,true)) respond(['ok'=>false,'error'=>'El fondo seleccionado no es válido.'],422);
if (!in_array($modo,$allowedModos,true)) respond(['ok'=>false,'error'=>'El modo seleccionado no es válido.'],422);
if ($decor !== 0 && $decor !== 1) respond(['ok'=>false,'error'=>'La configuración de decoración no es válida.'],422);
if (!in_array($color,$allowedColores,true)) respond(['ok'=>false,'error'=>'El color institucional seleccionado no es válido.'],422);

$ensure = ensureUserSettings($pdo,$userId);
$stmt = $pdo->prepare('UPDATE configuraciones_usuario SET fondo=?, modo=?, imagenes_decorativas=?, color_institucional=? WHERE usuario_id=?');
$stmt->execute([$fondo,$modo,$decor,$color,$userId]);
respond(['ok'=>true,'data'=>[
    'fondo'=>$fondo,
    'modo'=>$modo,
    'imagenes_decorativas'=>$decor,
    'color_institucional'=>$color,
]]);
