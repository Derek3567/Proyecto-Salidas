<?php
require __DIR__ . '/_bootstrap.php';
requireAuth();

function validPersonNameServer(string $v): bool { $v=trim($v); return mb_strlen($v,'UTF-8')<=75 && (bool)preg_match("/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:[ '-][A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u", $v); }
function validGroupServer(string $v): bool { return (bool)preg_match('/^[A-Za-z]$/', trim($v)); }

function storeNewIne(string $dataUrl,string $matricula,int $slot): string {
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
function deleteIneFile(?string $path): void {
    if(!$path)return;
    $full=dirname(__DIR__).'/private_uploads/ines/'.basename($path);
    if(is_file($full))@unlink($full);
}
function rowWithInes(array $row): array {
    $files=[];$urls=[];
    foreach(['ine_1_path','ine_2_path','ine_3_path','ine_4_path'] as $key){
        if(!empty($row[$key])){$f=basename($row[$key]);$files[]=$f;$urls[]='api/ine.php?file='.rawurlencode($f);}
    }
    $row['ine_files']=$files;$row['ines']=$urls;$row['ine_count']=count($files);
    return $row;
}
function generateInternalTutorCode(PDO $pdo): string {
    do {
        $code='SYS-'.bin2hex(random_bytes(12));
        $check=$pdo->prepare('SELECT id FROM tutores_autorizados WHERE codigo=? LIMIT 1');
        $check->execute([$code]);
    } while($check->fetch());
    return $code;
}
function validateTutorsPayload(array $tutors): array {
    if(count($tutors)<1 || count($tutors)>4)respond(['ok'=>false,'error'=>'Cada alumno debe tener de 1 a 4 tutores autorizados.'],422);
    $seenSlots=[];$clean=[];
    foreach($tutors as $index=>$t){
        if(!is_array($t))respond(['ok'=>false,'error'=>'Hay un tutor con datos inválidos.'],422);
        $slot=isset($t['slot'])?(int)$t['slot']:$index;
        if($slot<0 || $slot>3 || isset($seenSlots[$slot]))respond(['ok'=>false,'error'=>'La posición de los tutores no es válida.'],422);
        $seenSlots[$slot]=true;
        foreach(['nombres','apellidos','parentesco','telefono'] as $key){
            if(!isset($t[$key]) || trim((string)$t[$key])==='')respond(['ok'=>false,'error'=>"Falta el campo $key del tutor ".($index+1).'.'],422);
        }
        if(!validPersonNameServer((string)$t['nombres']) || !validPersonNameServer((string)$t['apellidos']))respond(['ok'=>false,'error'=>'Los nombres y apellidos de los tutores solo pueden contener letras, espacios, guiones o apóstrofes y tener como máximo 75 caracteres.'],422);
        $ine=isset($t['ine']) && is_string($t['ine']) && str_starts_with($t['ine'],'data:image/') ? $t['ine'] : null;
        $keep=isset($t['keep_ine_file']) && is_string($t['keep_ine_file']) ? basename($t['keep_ine_file']) : '';
        if(!$ine && !$keep)respond(['ok'=>false,'error'=>'Cada tutor debe tener una INE registrada.'],422);
        $clean[]=['id'=>!empty($t['id'])?(int)$t['id']:0,'slot'=>$slot,'nombres'=>trim((string)$t['nombres']),'apellidos'=>trim((string)$t['apellidos']),'parentesco'=>trim((string)$t['parentesco']),'telefono'=>trim((string)$t['telefono']),'ine'=>$ine,'keep_ine_file'=>$keep];
    }
    usort($clean,fn($a,$b)=>$a['slot']<=>$b['slot']);
    return $clean;
}

$method=$_SERVER['REQUEST_METHOD'];$id=isset($_GET['id'])?(int)$_GET['id']:0;
if($method==='GET'){
    $stmt=$pdo->query('SELECT * FROM alumnos WHERE activo=1 ORDER BY apellidos,nombres');
    respond(['ok'=>true,'data'=>array_map('rowWithInes',$stmt->fetchAll())]);
}

if($method==='POST' || $method==='PUT'){
    $d=jsonInput();
    foreach(['nombres','apellidos','matricula','semestre','grupo','especialidad'] as $key){if(!isset($d[$key])||trim((string)$d[$key])==='')respond(['ok'=>false,'error'=>"Falta el campo: $key"],422);}
    foreach(['nombres'=>'Nombres','apellidos'=>'Apellidos'] as $key=>$label){if(!validPersonNameServer((string)$d[$key]))respond(['ok'=>false,'error'=>"$label contiene caracteres no válidos o supera los 75 caracteres."],422);}
    if(!validGroupServer((string)$d['grupo']))respond(['ok'=>false,'error'=>'El grupo debe ser una sola letra de A a Z.'],422);
    $d['grupo']=strtoupper(trim((string)$d['grupo']));
    $tutors=validateTutorsPayload(is_array($d['tutores']??null)?$d['tutores']:[]);

    $allowedTutorIds=[];$old=null;
    if($method==='PUT'){
        if(!$id)respond(['ok'=>false,'error'=>'Falta el id del alumno.'],422);
        $oldStmt=$pdo->prepare('SELECT * FROM alumnos WHERE id=? AND activo=1');$oldStmt->execute([$id]);$old=$oldStmt->fetch();if(!$old)respond(['ok'=>false,'error'=>'Alumno no encontrado.'],404);
        $existingTutorStmt=$pdo->prepare('SELECT id FROM tutores_autorizados WHERE alumno_id=? AND activo=1 ORDER BY id');$existingTutorStmt->execute([$id]);
        $allowedTutorIds=array_map('intval',$existingTutorStmt->fetchAll(PDO::FETCH_COLUMN));
        foreach($tutors as $t){if($t['id'] && !in_array($t['id'],$allowedTutorIds,true))respond(['ok'=>false,'error'=>'Uno de los tutores seleccionados no pertenece a este alumno.'],422);}
    }else{
        $check=$pdo->prepare('SELECT id FROM alumnos WHERE matricula=? LIMIT 1');$check->execute([trim($d['matricula'])]);if($check->fetch())respond(['ok'=>false,'error'=>'La matrícula ya está registrada.'],409);
    }

    $oldPaths=[];foreach(['ine_1_path','ine_2_path','ine_3_path','ine_4_path'] as $key)$oldPaths[]=$old[$key]??null;
    $all=array_fill(0,4,null);$createdFiles=[];
    try{
        foreach($tutors as $t){
            if($t['ine']){$path=storeNewIne($t['ine'],(string)$d['matricula'],$t['slot']);$createdFiles[]=$path;$all[$t['slot']]=$path;}
            else{$keep=$t['keep_ine_file'];if(!preg_match('/^[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)$/i',$keep))respond(['ok'=>false,'error'=>'La INE conservada no es válida.'],422);$all[$t['slot']]=$keep;}
        }
        $alumnoNombre=trim((string)$d['nombres']).' '.trim((string)$d['apellidos']);
        $firstTutor=$tutors[0];
        $pdo->beginTransaction();
        if($method==='POST'){
            $stmt=$pdo->prepare('INSERT INTO alumnos(nombres,apellidos,matricula,semestre,grupo,especialidad,tutor_nombres,tutor_apellidos,ine_1_path,ine_2_path,ine_3_path,ine_4_path) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$d['nombres'],$d['apellidos'],trim((string)$d['matricula']),(int)$d['semestre'],$d['grupo'],$d['especialidad'],$firstTutor['nombres'],$firstTutor['apellidos'],$all[0],$all[1],$all[2],$all[3]]);
            $alumnoId=(int)$pdo->lastInsertId();
            $insertTutor=$pdo->prepare('INSERT INTO tutores_autorizados(alumno_id,alumno_nombre,nombres,apellidos,parentesco,telefono,codigo) VALUES(?,?,?,?,?,?,?)');
            foreach($tutors as $t)$insertTutor->execute([$alumnoId,$alumnoNombre,$t['nombres'],$t['apellidos'],$t['parentesco'],$t['telefono'],generateInternalTutorCode($pdo)]);
            $pdo->commit();respond(['ok'=>true,'id'=>$alumnoId,'tutores_registrados'=>count($tutors)]);
        }
        $stmt=$pdo->prepare('UPDATE alumnos SET nombres=?,apellidos=?,semestre=?,grupo=?,especialidad=?,tutor_nombres=?,tutor_apellidos=?,ine_1_path=?,ine_2_path=?,ine_3_path=?,ine_4_path=? WHERE id=? AND activo=1');
        $stmt->execute([$d['nombres'],$d['apellidos'],(int)$d['semestre'],$d['grupo'],$d['especialidad'],$firstTutor['nombres'],$firstTutor['apellidos'],$all[0],$all[1],$all[2],$all[3],$id]);
        $updateTutor=$pdo->prepare('UPDATE tutores_autorizados SET alumno_id=?,alumno_nombre=?,nombres=?,apellidos=?,parentesco=?,telefono=? WHERE id=? AND activo=1');
        $insertTutor=$pdo->prepare('INSERT INTO tutores_autorizados(alumno_id,alumno_nombre,nombres,apellidos,parentesco,telefono,codigo) VALUES(?,?,?,?,?,?,?)');
        $keptIds=[];
        foreach($tutors as $t){
            if($t['id']){$updateTutor->execute([$id,$alumnoNombre,$t['nombres'],$t['apellidos'],$t['parentesco'],$t['telefono'],$t['id']]);$keptIds[]=$t['id'];}
            else{$insertTutor->execute([$id,$alumnoNombre,$t['nombres'],$t['apellidos'],$t['parentesco'],$t['telefono'],generateInternalTutorCode($pdo)]);$keptIds[]=(int)$pdo->lastInsertId();}
        }
        $deactivate=$pdo->prepare('UPDATE tutores_autorizados SET activo=0 WHERE alumno_id=? AND activo=1'.(count($keptIds)?' AND id NOT IN ('.implode(',',array_fill(0,count($keptIds),'?')).')':''));
        $params=[$id];foreach($keptIds as $keepId)$params[]=$keepId;$deactivate->execute($params);
        $pdo->commit();
        foreach($oldPaths as $oldPath){if($oldPath && !in_array(basename($oldPath),array_filter($all),true))deleteIneFile($oldPath);}
        respond(['ok'=>true,'tutores_registrados'=>count($tutors)]);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        foreach($createdFiles as $file)deleteIneFile($file);
        if($e instanceof PDOException && $e->getCode()==='23000')respond(['ok'=>false,'error'=>'La matrícula o un identificador interno de tutor ya está registrado.'],409);
        respond(['ok'=>false,'error'=>'No se pudo guardar el alumno y sus tutores.'],500);
    }
}

if($method==='DELETE'){
    if(!$id)respond(['ok'=>false,'error'=>'Falta el id del alumno.'],422);
    $stmt=$pdo->prepare('SELECT * FROM alumnos WHERE id=? AND activo=1');$stmt->execute([$id]);$row=$stmt->fetch();if(!$row)respond(['ok'=>false,'error'=>'Alumno no encontrado.'],404);
    foreach(['ine_1_path','ine_2_path','ine_3_path','ine_4_path'] as $key)deleteIneFile($row[$key]??null);
    $stmt=$pdo->prepare('UPDATE alumnos SET activo=0 WHERE id=?');$stmt->execute([$id]);
    $pdo->prepare('UPDATE tutores_autorizados SET activo=0 WHERE alumno_id=?')->execute([$id]);
    respond(['ok'=>true]);
}
respond(['ok'=>false,'error'=>'Método no soportado.'],405);
