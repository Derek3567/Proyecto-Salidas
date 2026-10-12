<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programa 4 - Promedio de 5 evaluaciones</title>
</head>
<body>
    <form action="" method="post">
        <label for="eval1">Evaluacion 1:</label>
        <input type="number" name="eval1" placeholder="Evaluacion 1" required><hr>
        <label for="eval2">Evaluacion 2:</label>
        <input type="number" name="eval2" placeholder="Evaluacion 2" required><hr>
        <label for="eval3">Evaluacion 3:</label>
        <input type="number" name="eval3" placeholder="Evaluacion 3" required><hr>
        <label for="eval4">Evaluacion 4:</label>
        <input type="number" name="eval4" placeholder="Evaluacion 4" required><hr>
        <label for="eval5">Evaluacion 5:</label>
        <input type="number" name="eval5" placeholder="Evaluacion 5" required><hr>
        <button type="submit">Calcular promedio</button>
    </form>

        <?php
                $eval1 = null;
                $eval2 = null;
                $eval3 = null;
                $eval4 = null;
                $eval5 = null;
                $eval1 = $_POST['eval1'];
                $eval2 = $_POST['eval2'];
                $eval3 = $_POST['eval3'];
                $eval4 = $_POST['eval4'];
                $eval5 = $_POST['eval5'];
                $promedio = ($eval1 + $eval2 + $eval3 + $eval4 + $eval5) / 5;
                echo "<h2>Promedio: " . $promedio . "</h2>";

                if ($promedio >= 60) {
                    echo "<h2>Aprobo</h2>";
                } else {
                    echo "<h2>No acredito</h2>";
                }
        ?>
</body>
</html>