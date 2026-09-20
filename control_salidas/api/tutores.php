<?php
function validPersonNameServer(string $v): bool { $v=trim($v); return mb_strlen($v,'UTF-8')<=75 && (bool)preg_match("/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:[ '-][A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u", $v); }
require __DIR__ . '/_bootstrap.php';
requireAuth();

function storeTutorIne(string $dataUrl,string $matricula,int $slot): string {
    $dir=dirname(__DIR__).'/private_uploads/ines';
    if(!is_dir($dir) && !mkdir($dir,0750,true))respond(['ok'=>false,'error'=>'No se pudo preparar el almacenamiento de INEs.'],500);
    if(!preg_match('#^data:image/(jpeg|png|webp);base64,#i',$dataUrl,$m))respond(['ok'=>false,'error'=>'Formato de INE no permitido.'],422);
    $raw=base64_decode(substr($dataUrl,strpos($dataUrl,',')+1),true);
    if($raw===false || strlen($raw)>3*1024*1024)respond(['ok'=>false,'error'=>'Cada INE debe pesar como máximo 3 MB.'],422);
    $safe=preg_replace('/[^A-Za-z0-9_-]/','_',trim($matricula));
    $ext=strtolower($m[1])==='jpeg'?'jpg':strtolower($m[1]);
    $name=$safe.'_t'.($slot+1).'_'.bin2hex(random_bytes(6)).'.'.$ext;
    if(file_put_contents($dir.'/'.$name,$raw)===false)respond(['ok'=>false,'error'=>'No se pudo guardar la INE.'],500);
    return $name;
}
function deleteTutorIne(?string $path): void {
    if(!$path)return;
    $full=dirname(__DIR__).'/private_uploads/ines/'.basename($path);
    if(is_file($full))@unlink($full);
}
function studentInePaths(array $student): array { return [$student['ine_1_path']??null,$student['ine_2_path']??null,$student['ine_3_path']??null,$student['ine_4_path']??null]; }
function saveStudentInePaths(PDO $pdo,int $studentId,array $paths): void {
    $stmt=$pdo->prepare('UPDATE alumnos SET ine_1_path=?,ine_2_path=?,ine_3_path=?,ine_4_path=? WHERE id=? AND activo=1');
    $stmt->execute([$paths[0]??null,$paths[1]??null,$paths[2]??null,$paths[3]??null,$studentId]);
}
function findTutorSlot(PDO $pdo,int $studentId,int $tutorId): int {
    $stmt=$pdo->prepare('SELECT id FROM tutores_autorizados WHERE alumno_id=? AND activo=1 ORDER BY id');$stmt->execute([$studentId]);
    $ids=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    $slot=array_search($tutorId,$ids,true);
    return $slot===false?-1:(int)$slot;
}

function generateInternalTutorCode(PDO $pdo): string {
    do {
        $code='SYS-'.bin2hex(random_bytes(12));
        $check=$pdo->prepare('SELECT id FROM tutores_autorizados WHERE codigo=? LIMIT 1');
        $check->execute([$code]);
    } while($check->fetch());
    return $code;
}

$method=$_SERVER['REQUEST_METHOD'];$id=isset($_GET['id'])?(int)$_GET['id']:0;
if($method==='GET'){
    $sql='SELECT t.*, CONCAT(a.nombres," ",a.apellidos) AS alumno FROM tutores_autorizados t LEFT JOIN alumnos a ON a.id=t.alumno_id WHERE t.activo=1 ORDER BY t.apellidos,t.nombres';
    respond(['ok'=>true,'data'=>$pdo->query($sql)->fetchAll()]);
}

if($method==='POST' || $method==='PUT'){
    $d=jsonInput();
    foreach(['nombres','apellidos','parentesco','telefono','alumno_id'] as $key){if(!isset($d[$key])||trim((string)$d[$key])==='')respond(['ok'=>false,'error'=>"Falta el campo: $key"],422);}
    if(!validPersonNameServer((string)$d['nombres'])||!validPersonNameServer((string)$d['apellidos']))respond(['ok'=>false,'error'=>'Los nombres y apellidos del tutor solo pueden contener letras, espacios, guiones o apóstrofes y tener como máximo 75 caracteres.'],422);
    $alumnoId=(int)$d['alumno_id'];
    $studentStmt=$pdo->prepare('SELECT * FROM alumnos WHERE id=? AND activo=1 LIMIT 1');$studentStmt->execute([$alumnoId]);$student=$studentStmt->fetch();
    if(!$student)respond(['ok'=>false,'error'=>'El alumno seleccionado no existe o está dado de baja.'],422);
    $alumnoNombre=trim($student['nombres'].' '.$student['apellidos']);
    $ine=isset($d['ine'])&&is_string($d['ine'])&&str_starts_with($d['ine'],'data:image/')?$d['ine']:null;
    $keep=isset($d['keep_ine_file'])&&is_string($d['keep_ine_file'])?basename($d['keep_ine_file']):'';

    $paths=studentInePaths($student);$created=null;$oldRemoved=null;
    if($method==='POST'){
        $activeCount=(int)$pdo->query('SELECT COUNT(*) FROM tutores_autorizados WHERE alumno_id='.(int)$alumnoId.' AND activo=1')->fetchColumn();
        if($activeCount>=4)respond(['ok'=>false,'error'=>'Este alumno ya tiene 4 tutores registrados.'],422);
        $slot=$activeCount;
        if(!$ine)respond(['ok'=>false,'error'=>'Cada tutor debe tener una INE registrada.'],422);
        try{
            $created=storeTutorIne($ine,(string)$student['matricula'],$slot);
            $paths[$slot]=$created;
            $pdo->beginTransaction();saveStudentInePaths($pdo,$alumnoId,$paths);
            $stmt=$pdo->prepare('INSERT INTO tutores_autorizados(alumno_id,alumno_nombre,nombres,apellidos,parentesco,telefono,codigo) VALUES(?,?,?,?,?,?,?)');
            $stmt->execute([$alumnoId,$alumnoNombre,$d['nombres'],$d['apellidos'],$d['parentesco'],$d['telefono'],generateInternalTutorCode($pdo)]);$newId=(int)$pdo->lastInsertId();
            $pdo->commit();respond(['ok'=>true,'id'=>$newId]);
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($created)deleteTutorIne($created);if($e instanceof PDOException&&$e->getCode()==='23000')respond(['ok'=>false,'error'=>'No se pudo guardar el tutor porque uno de sus datos ya está registrado.'],409);respond(['ok'=>false,'error'=>'No se pudo guardar el tutor y su INE.'],500);}
    }

    if(!$id)respond(['ok'=>false,'error'=>'Falta el id del tutor.'],422);
    $oldStmt=$pdo->prepare('SELECT * FROM tutores_autorizados WHERE id=? AND activo=1 LIMIT 1');$oldStmt->execute([$id]);$oldTutor=$oldStmt->fetch();if(!$oldTutor)respond(['ok'=>false,'error'=>'Tutor no encontrado.'],404);
    if((int)$oldTutor['alumno_id']!==$alumnoId)respond(['ok'=>false,'error'=>'El tutor no pertenece al alumno seleccionado.'],422);
    $slot=findTutorSlot($pdo,$alumnoId,$id);if($slot<0||$slot>3)respond(['ok'=>false,'error'=>'No se pudo determinar la posición del tutor.'],422);
    if(!$ine && !$keep)respond(['ok'=>false,'error'=>'Cada tutor debe tener una INE registrada.'],422);
    try{
        if($ine){$created=storeTutorIne($ine,(string)$student['matricula'],$slot);$paths[$slot]=$created;}
        else{$keep=basename($keep);if(!preg_match('/^[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)$/i',$keep))respond(['ok'=>false,'error'=>'La INE conservada no es válida.'],422);$paths[$slot]=$keep;}
        $pdo->beginTransaction();saveStudentInePaths($pdo,$alumnoId,$paths);
        $stmt=$pdo->prepare('UPDATE tutores_autorizados SET alumno_id=?,alumno_nombre=?,nombres=?,apellidos=?,parentesco=?,telefono=? WHERE id=? AND activo=1');
        $stmt->execute([$alumnoId,$alumnoNombre,$d['nombres'],$d['apellidos'],$d['parentesco'],$d['telefono'],$id]);
        $pdo->commit();
        if($ine && !empty($studentInePaths($student)[$slot]) && $studentInePaths($student)[$slot]!==$paths[$slot])deleteTutorIne($studentInePaths($student)[$slot]);
        respond(['ok'=>true]);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($created)deleteTutorIne($created);if($e instanceof PDOException&&$e->getCode()==='23000')respond(['ok'=>false,'error'=>'No se pudo guardar el tutor porque uno de sus datos ya está registrado.'],409);respond(['ok'=>false,'error'=>'No se pudo modificar el tutor y su INE.'],500);}
}

if($method==='DELETE'){
    if(!$id)respond(['ok'=>false,'error'=>'Falta el id del tutor.'],422);
    $stmt=$pdo->prepare('SELECT * FROM tutores_autorizados WHERE id=? AND activo=1 LIMIT 1');$stmt->execute([$id]);$tutor=$stmt->fetch();if(!$tutor)respond(['ok'=>false,'error'=>'Tutor no encontrado.'],404);
    $studentId=(int)$tutor['alumno_id'];
    $studentStmt=$pdo->prepare('SELECT * FROM alumnos WHERE id=? AND activo=1 LIMIT 1');$studentStmt->execute([$studentId]);$student=$studentStmt->fetch();
    if(!$student){$pdo->prepare('UPDATE tutores_autorizados SET activo=0 WHERE id=?')->execute([$id]);respond(['ok'=>true]);}
    $slot=findTutorSlot($pdo,$studentId,$id);if($slot<0||$slot>3)respond(['ok'=>false,'error'=>'No se pudo determinar la posición del tutor.'],422);
    $paths=studentInePaths($student);$removed=$paths[$slot]??null;
    for($i=$slot;$i<3;$i++)$paths[$i]=$paths[$i+1]??null;$paths[3]=null;
    try{
        $pdo->beginTransaction();saveStudentInePaths($pdo,$studentId,$paths);$pdo->prepare('UPDATE tutores_autorizados SET activo=0 WHERE id=?')->execute([$id]);$pdo->commit();
        if($removed && !in_array($removed,array_filter($paths),true))deleteTutorIne($removed);
        respond(['ok'=>true]);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();respond(['ok'=>false,'error'=>'No se pudo dar de baja al tutor.'],500);}
}
respond(['ok'=>false,'error'=>'Método no soportado.'],405);
