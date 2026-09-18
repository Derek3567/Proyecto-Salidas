<?php
require_once __DIR__.'/../config/database.php';
$m=$_SERVER['REQUEST_METHOD']; $d=body(); $id=(int)($_GET['id']??0);
try {
 if($m==='GET'){ $q=$pdo->query("SELECT * FROM alumnos ORDER BY apellidos,nombres"); out(['success'=>true,'data'=>$q->fetchAll()]);}
 if($m==='POST'||$m==='PUT'){
  foreach(['nombres','apellidos','matricula','semestre','grupo','especialidad','tutor'] as $k) if(trim((string)($d[$k]??''))==='') out(['success'=>false,'message'=>"Falta el campo $k."],422);
  if($m==='POST'){
   $s=$pdo->prepare("INSERT INTO alumnos(nombres,apellidos,matricula,semestre,grupo,especialidad,tutor,ine) VALUES(?,?,?,?,?,?,?,?)");
   $s->execute([$d['nombres'],$d['apellidos'],$d['matricula'],$d['semestre'],$d['grupo'],$d['especialidad'],$d['tutor'],$d['ine']??null]);
  } else {
   if(!$id) out(['success'=>false,'message'=>'Falta id del alumno.'],400);
   $s=$pdo->prepare("UPDATE alumnos SET nombres=?,apellidos=?,matricula=?,semestre=?,grupo=?,especialidad=?,tutor=?,ine=? WHERE id_alumno=?");
   $s->execute([$d['nombres'],$d['apellidos'],$d['matricula'],$d['semestre'],$d['grupo'],$d['especialidad'],$d['tutor'],$d['ine']??null,$id]);
  } out(['success'=>true,'message'=>'Alumno guardado.']);
 }
 if($m==='DELETE'){if(!$id)out(['success'=>false,'message'=>'Falta id.'],400);$s=$pdo->prepare("DELETE FROM alumnos WHERE id_alumno=?");$s->execute([$id]);out(['success'=>true]);}
 out(['success'=>false,'message'=>'Método no permitido.'],405);
} catch(PDOException $e){out(['success'=>false,'message'=>$e->getCode()==='23000'?'La matrícula ya existe o el alumno tiene registros asociados.':'Error de base de datos.'],409);}
