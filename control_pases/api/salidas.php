<?php
require_once __DIR__.'/../config/database.php';
$m=$_SERVER['REQUEST_METHOD'];$d=body();
try{
 if($m==='GET'){$s=$pdo->query("SELECT * FROM salidas ORDER BY creado_en DESC");out(['success'=>true,'data'=>$s->fetchAll()]);}
 if($m==='POST'){
  foreach(['alumno','tutor','parentesco','date','time','group','reason','folio'] as $k)if(trim((string)($d[$k]??''))==='')out(['success'=>false,'message'=>"Falta el campo $k."],422);
  $s=$pdo->prepare("INSERT INTO salidas(alumno,tutor,parentesco,fecha,hora,grupo,motivo,folio) VALUES(?,?,?,?,?,?,?,?)");
  $s->execute([$d['alumno'],$d['tutor'],$d['parentesco'],$d['date'],$d['time'],$d['group'],$d['reason'],$d['folio']]);out(['success'=>true,'id'=>$pdo->lastInsertId()]);
 }
 out(['success'=>false,'message'=>'Método no permitido.'],405);
}catch(PDOException $e){out(['success'=>false,'message'=>$e->getCode()==='23000'?'El folio ya existe.':'Error de base de datos.'],409);}
