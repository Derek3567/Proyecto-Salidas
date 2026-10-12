<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programa 6 - Tipo de triangulo</title>
</head>
<body>
    <form action="" method="post">
        <label for="lado1">Lado 1:</label>
        <input type="number" name="lado1" placeholder="Lado 1"><hr>
        <label for="lado2">Lado 2:</label>
        <input type="number" name="lado2" placeholder="Lado 2"><hr>
        <label for="lado3">Lado 3:</label>
        <input type="number" name="lado3" placeholder="Lado 3"><hr>
        <button type="submit">Determinar tipo de triangulo</button>
    </form>

        <?php
                $lado1 = null;
                $lado2 = null;
                $lado3 = null;
                $lado1 = $_POST['lado1'];
                $lado2 = $_POST['lado2'];
                $lado3 = $_POST['lado3'];

                if (($lado1 + $lado2) > $lado3 && ($lado1 + $lado3) > $lado2 && ($lado2 + $lado3) > $lado1) {
                    if ($lado1 == $lado2 && $lado2 == $lado3) {
                        echo "<h2>Triangulo equilatero (los 3 lados son iguales)</h2>";
                    } else if ($lado1 == $lado2 || $lado1 == $lado3 || $lado2 == $lado3) {
                        echo "<h2>Triangulo isosceles (2 lados iguales)</h2>";
                    } else {
                        echo "<h2>Triangulo escaleno (todos los lados distintos)</h2>";
                    }
                } else {
                    echo "<h2>No es un triangulo</h2>";
                }
        ?>
</body>
</html>