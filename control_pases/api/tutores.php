<?php
require_once __DIR__.'/../config/database.php';
$m=$_SERVER['REQUEST_METHOD'];$d=body();$id=(int)($_GET['id']??0);$action=$_GET['action']??'';
try{
 if($action==='verify'&&$m==='POST'){
  foreach(['tutor','student','relationship','code'] as $k)if(trim((string)($d[$k]??''))==='')out(['success'=>false,'message'=>'Completa todos los datos para verificar.'],422);
  $s=$pdo->prepare("SELECT * FROM tutores WHERE LOWER(TRIM(CONCAT(nombres,' ',apellidos)))=LOWER(TRIM(?)) AND LOWER(TRIM(alumno))=LOWER(TRIM(?)) AND LOWER(TRIM(parentesco))=LOWER(TRIM(?)) AND LOWER(TRIM(codigo))=LOWER(TRIM(?)) LIMIT 1");
  $s->execute([$d['tutor'],$d['student'],$d['relationship'],$d['code']]);$r=$s->fetch();out(['success'=>true,'authorized'=>(bool)$r,'tutor'=>$r]);
 }
 if($m==='GET'){ $s=$pdo->query("SELECT * FROM tutores ORDER BY nombres,apellidos");$rows=$s->fetchAll();foreach($rows as &$r){$r['tutor']=trim($r['nombres'].' '.$r['apellidos']);$r['student']=$r['alumno'];$r['relationship']=$r['parentesco'];$r['code']=$r['codigo'];}out(['success'=>true,'data'=>$rows]);}
 if($m==='POST'){
  foreach(['tutor','student','relationship','code'] as $k)if(trim((string)($d[$k]??''))==='')out(['success'=>false,'message'=>'Completa todos los campos.'],422);
  $parts=preg_split('/\s+/',trim($d['tutor']),2);$n=$parts[0];$a=$parts[1]??'';
  $s=$pdo->prepare("INSERT INTO tutores(nombres,apellidos,alumno,parentesco,codigo) VALUES(?,?,?,?,?)");$s->execute([$n,$a,$d['student'],$d['relationship'],$d['code']]);out(['success'=>true]);
 }
 if($m==='DELETE'){if(!$id)out(['success'=>false,'message'=>'Falta id.'],400);$s=$pdo->prepare("DELETE FROM tutores WHERE id_tutor=?");$s->execute([$id]);out(['success'=>true]);}
 out(['success'=>false,'message'=>'Método no permitido.'],405);
}catch(PDOException $e){out(['success'=>false,'message'=>$e->getCode()==='23000'?'El código ya existe.':'Error de base de datos.'],409);}
