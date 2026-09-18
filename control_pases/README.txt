CONTROL DE PASES — PHP + MySQL
1) Instala/abre XAMPP e inicia Apache y MySQL.
2) Copia la carpeta completa a C:\xampp\htdocs\control_pases
3) En phpMyAdmin importa database/control_pases.sql
4) Verifica config/database.php (usuario root y contraseña según tu XAMPP).
5) Abre http://localhost/control_pases/index(4).html
   Puedes renombrar index(4).html a index.html y style(3).css a style.css; revisa que el HTML apunte a app.js/style(3).css.
6) Los endpoints se encuentran en api/. La app usa fetch a api/.
NOTA: el archivo original trae recursos visuales embebidos en app.js. Se conserva su diseño.
