<?php
require __DIR__ . '/_bootstrap.php';
$method=$_SERVER['REQUEST_METHOD'];
$action=$_GET['action'] ?? 'login';

if($action==='status' && $method==='GET'){
    if(empty($_SESSION['user_id'])) respond(['ok'=>true,'authenticated'=>false]);
    respond(['ok'=>true,'authenticated'=>true,'data'=>['id'=>(int)$_SESSION['user_id'],'nombre'=>$_SESSION['user_name'],'correo'=>$_SESSION['user_email']]]);
}

if($action==='logout' && ($method==='POST' || $method==='GET')){
    $_SESSION=[];
    if(ini_get('session.use_cookies')){
        $p=session_get_cookie_params();
        setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
    }
    session_destroy();
    respond(['ok'=>true]);
}

if($method!=='POST') respond(['ok'=>false,'error'=>'Método no soportado.'],405);
$d=jsonInput();


if($action==='authorize_purge' && $method==='POST'){
    requireAuth();
    $d=jsonInput();
    $password=(string)($d['password']??'');
    if($password==='')respond(['ok'=>false,'error'=>'Escribe la contraseña de tu cuenta.'],422);
    $stmt=$pdo->prepare('SELECT password_hash FROM usuarios_sistema WHERE id=? AND activo=1 LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user=$stmt->fetch();
    if(!$user || !password_verify($password,$user['password_hash']))respond(['ok'=>false,'error'=>'La contraseña de la cuenta no es correcta.'],401);
    $_SESSION['purge_token']=bin2hex(random_bytes(32));
    $_SESSION['purge_expires']=time()+300;
    respond(['ok'=>true,'data'=>['token'=>$_SESSION['purge_token'],'expires_in'=>300]]);
}

if($action==='purge_semesters' && $method==='POST'){
    requireAuth();
    $d=jsonInput();
    $token=(string)($d['token']??'');
    $expires=(int)($_SESSION['purge_expires']??0);
    $sessionToken=(string)($_SESSION['purge_token']??'');
    if(!$token || !$sessionToken || $expires<time() || !hash_equals($sessionToken,$token))respond(['ok'=>false,'error'=>'La autorización de borrado expiró. Vuelve a verificar tu contraseña.'],401);
    $semesters=$d['semesters']??[];
    if(!is_array($semesters))respond(['ok'=>false,'error'=>'La selección de semestres no es válida.'],422);
    $semesters=array_values(array_unique(array_map('intval',$semesters)));
    $semesters=array_values(array_filter($semesters,fn($n)=>$n>=1&&$n<=6));
    if(!$semesters)respond(['ok'=>false,'error'=>'Selecciona al menos un semestre.'],422);
    $placeholders=implode(',',array_fill(0,count($semesters),'?'));
    $studentsStmt=$pdo->prepare("SELECT id,ine_1_path,ine_2_path,ine_3_path,ine_4_path FROM alumnos WHERE semestre IN ($placeholders)");
    $studentsStmt->execute($semesters);
    $students=$studentsStmt->fetchAll();
    $studentIds=array_map(fn($r)=>(int)$r['id'],$students);
    $paths=[];
    foreach($students as $row){foreach(['ine_1_path','ine_2_path','ine_3_path','ine_4_path'] as $key){if(!empty($row[$key]))$paths[]=basename($row[$key]);}}
    $paths=array_values(array_unique($paths));
    $deletedStudents=0;$deletedTutors=0;
    try{
        $pdo->beginTransaction();
        if($studentIds){
            $idPlaceholders=implode(',',array_fill(0,count($studentIds),'?'));
            $countTutor=$pdo->prepare("SELECT COUNT(*) FROM tutores_autorizados WHERE alumno_id IN ($idPlaceholders)");
            $countTutor->execute($studentIds);$deletedTutors=(int)$countTutor->fetchColumn();
            $delTutors=$pdo->prepare("DELETE FROM tutores_autorizados WHERE alumno_id IN ($idPlaceholders)");
            $delTutors->execute($studentIds);
            $delStudents=$pdo->prepare("DELETE FROM alumnos WHERE id IN ($idPlaceholders)");
            $delStudents->execute($studentIds);$deletedStudents=$delStudents->rowCount();
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();respond(['ok'=>false,'error'=>'No se pudieron borrar los registros seleccionados.'],500);}
    foreach($paths as $file){$full=dirname(__DIR__).'/private_uploads/ines/'.basename($file);if(is_file($full))@unlink($full);}
    unset($_SESSION['purge_token'],$_SESSION['purge_expires']);
    respond(['ok'=>true,'data'=>['semesters'=>$semesters,'students_deleted'=>$deletedStudents,'tutors_deleted'=>$deletedTutors,'ines_deleted'=>count($paths)]]);
}

if($action==='register'){
    foreach(['nombre','correo','password'] as $key){if(empty($d[$key]))respond(['ok'=>false,'error'=>"Falta el campo: $key"],422);}
    $nombre=trim((string)$d['nombre']);
    $correo=strtolower(trim((string)$d['correo']));
    $password=(string)$d['password'];
    if($nombre==='' || mb_strlen($nombre)>120 || !preg_match("/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:[ '\\-][A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u",$nombre))respond(['ok'=>false,'error'=>'El nombre solo puede contener letras, espacios, guiones o apóstrofes y tener como máximo 120 caracteres.'],422);
    if(!filter_var($correo,FILTER_VALIDATE_EMAIL))respond(['ok'=>false,'error'=>'Correo inválido.'],422);
    if(!preg_match('/^(?=.{10,}$)(?!.*\s)(?=.*[A-Za-z])(?=.*[A-Z])(?=.*\d).*$/',$password))respond(['ok'=>false,'error'=>'La contraseña debe tener 10 caracteres, sin espacios, con número, letra y mayúscula.'],422);

    try{
        $pdo->beginTransaction();
        $count=(int)$pdo->query('SELECT COUNT(*) FROM usuarios_sistema FOR UPDATE')->fetchColumn();
        if($count>=12){$pdo->rollBack();respond(['ok'=>false,'error'=>'Se alcanzó el máximo de 12 cuentas permitidas.'],409);}
        $stmt=$pdo->prepare('SELECT id FROM usuarios_sistema WHERE correo=? LIMIT 1');
        $stmt->execute([$correo]);
        if($stmt->fetch()){$pdo->rollBack();respond(['ok'=>false,'error'=>'Ya existe una cuenta con ese correo electrónico.'],409);}
        $stmt=$pdo->prepare('INSERT INTO usuarios_sistema(nombre,correo,password_hash) VALUES(?,?,?)');
        $stmt->execute([$nombre,$correo,password_hash($password,PASSWORD_DEFAULT)]);
        $newId=(int)$pdo->lastInsertId();
        $stmt=$pdo->prepare("INSERT INTO configuraciones_usuario (usuario_id,fondo,modo,imagenes_decorativas,color_institucional) VALUES (?, 'fondo_claro1','claro',1,'azul')");
        $stmt->execute([$newId]);
        $pdo->commit();
        respond(['ok'=>true,'id'=>$newId,'data'=>['nombre'=>$nombre,'correo'=>$correo]]);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        if($e instanceof PDOException && (int)$e->errorInfo[1]===1062)respond(['ok'=>false,'error'=>'Ya existe una cuenta con ese correo electrónico.'],409);
        respond(['ok'=>false,'error'=>'No se pudo crear la cuenta.'],500);
    }
}

if($action==='login'){
    foreach(['nombre','correo','password'] as $key){if(empty($d[$key]))respond(['ok'=>false,'error'=>"Falta el campo: $key"],422);}
    $email=strtolower(trim((string)$d['correo']));
    $stmt=$pdo->prepare('SELECT id,nombre,correo,password_hash FROM usuarios_sistema WHERE correo=? AND activo=1 LIMIT 1');
    $stmt->execute([$email]);
    $user=$stmt->fetch();
    if(!$user || !password_verify((string)$d['password'],$user['password_hash']))respond(['ok'=>false,'error'=>'Correo o contraseña incorrectos.'],401);
    if(strcasecmp(trim((string)$d['nombre']),trim((string)$user['nombre']))!==0)respond(['ok'=>false,'error'=>'El nombre no coincide con la cuenta.'],401);
    session_regenerate_id(true);
    $_SESSION['user_id']=(int)$user['id'];
    $_SESSION['user_name']=$user['nombre'];
    $_SESSION['user_email']=$user['correo'];
    respond(['ok'=>true,'data'=>['id'=>(int)$user['id'],'nombre'=>$user['nombre'],'correo'=>$user['correo']]]);
}
respond(['ok'=>false,'error'=>'Acción no soportada.'],400);
