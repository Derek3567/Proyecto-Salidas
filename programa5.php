<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programa 5 - Celsius a Fahrenheit y viceversa</title>
</head>
    <h3>Celsius a Fahrenheit</h3>
    <form action="" method="post">
        <label for="celsius">Grados Celsius:</label>
        <input type="number" name="celsius" placeholder="Celsius" step="any"><hr>
        <button type="submit">Convertir a Fahrenheit</button>
    </form>

    <h3>Fahrenheit a Celsius</h3>
    <form action="" method="post">
        <label for="fahrenheit">Grados Fahrenheit:</label>
        <input type="number" name="fahrenheit" placeholder="Fahrenheit" step="any"><hr>
        <button type="submit">Convertir a Celsius</button>
    </form>

<body>
        <?php
                $celsius = null;
                $fahrenheit = null;
                $celsius = $_POST['celsius'];
                $fahrenheit = $_POST['fahrenheit'];

                if ($celsius !== null) {
                    $f = ($celsius * 9 / 5) + 32;
                    echo "<h2>" . $celsius . " grados Celsius = " . $f . " grados Fahrenheit</h2>";
                }

                if ($fahrenheit !== null) {
                    $c = ($fahrenheit - 32) * 5 / 9;
                    echo "<h2>" . $fahrenheit . " grados Fahrenheit = " . $c . " grados Celsius</h2>";
                }

                /* 
                F = (C * 9/5) + 32
                C = (F - 32) * 5/9
                */
        ?>
</body>
</html>