<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programa 3 - Perimetro del triangulo</title>
</head>
<body>
    <form action="" method="post">
        <label for="lado1">Lado 1:</label>
        <input type="number" name="lado1" placeholder="Lado 1"><hr>
        <label for="lado2">Lado 2:</label>
        <input type="number" name="lado2" placeholder="Lado 2"><hr>
        <label for="lado3">Lado 3:</label>
        <input type="number" name="lado3" placeholder="Lado 3"><hr>
        <button type="submit">Calcular perimetro</button>
    </form>

        <?php
                $lado1 = null;
                $lado2 = null;
                $lado3 = null;
                $lado1 = $_POST['lado1'];
                $lado2 = $_POST['lado2'];
                $lado3 = $_POST['lado3'];
                $perimetro = $lado1 + $lado2 + $lado3;
                echo "<h2>Perimetro del triangulo: " . $perimetro . "</h2>";
        ?>
</body>
</html>