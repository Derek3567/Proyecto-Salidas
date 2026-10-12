<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <form action="" method="post">
        <label for="base">Base:</label>
        <input type="number" name="base" placeholder="Base"><hr>
        <label for="altura">Altura:</label>
        <input type="number" name="altura" placeholder="Altura"><hr>
        <button type="submit">Calcular area del rectángulo</button>
    </form>

        <?php
                $base = null;
                $altura = null;
                $base = $_POST['base'];
                $altura = $_POST['altura'];
                $area = $base * $altura;
                echo "<h2>Área del rectángulo: " . $area . "</h2>";

                /* Anotaciones por si se me olvida xd
                
                method="post" sirve para conseguir los datos para usarlos en el php
                name="base" asi identifico el input que usare en el php
                $base = null; si no es null se bugea al inicio xd
                $base = $_POST['base']; gracias al name lo uso para identificarlo en el php
                */
        ?>
</body>
</html>