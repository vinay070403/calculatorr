<?php
session_start();

// DB connection
$host = 'localhost';
$dbname = 'calculator_dbd';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// Function to perform the calculation
function calculate($a, $op, $b)
{
    switch ($op) {
        case '+':
            return $a + $b;
        case '-':
            return $a - $b;
        case '*':
            return $a * $b;
        case '/':
            return $b == 0 ? "Error: Cannot divide by zero" : $a / $b;
        default:
            return "Invalid operation";
    }
}

// --- Handle POST requests ---

// Delete entry 
// here calculations is table name ohk
if (isset($_POST['delete_id'])) {
    $deleteId = (int) $_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM calculations WHERE id = ?"); //database in tabelname = calculations
    $stmt->execute([$deleteId]);
    header("Location: " . $_SERVER['PHP_SELF']); //PHP_SELF = form on start same page 
    exit;
}

// Update entry
if (isset($_POST['update_entry'])) {
    $editId = (int)$_POST['edit_id'];
    $num1 = floatval($_POST['edit_num1']);
    $num2 = floatval($_POST['edit_num2']);
    $operation = $_POST['edit_operation'];
    $allowed_ops = ['+', '-', '*', '/'];

    if (in_array($operation, $allowed_ops)) {
        $result = calculate($num1, $operation, $num2);

        // Optional: prevent saving if result is error string
        if (is_numeric($result)) {
            $stmt = $pdo->prepare("UPDATE calculations SET num1=?, operation=?, num2=?, result=? WHERE id=?");
            $stmt->execute([$num1, $operation, $num2, $result, $editId]);
        }
    }

    $_SESSION['calculation_done'] = true;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}



// New calculation

$result = null;
if (isset($_POST['calculate'])) {
    $num1 = floatval($_POST['num1']);
    $num2 = floatval($_POST['num2']);
    $operation = $_POST['operation'];

    $allowed_ops = ['+', '-', '*', '/'];

    if (!in_array($operation, $allowed_ops)) {
        $result = "Invalid operation";
    } else {
        $result = calculate($num1, $operation, $num2);

        if (is_numeric($result)) {
            $stmt = $pdo->prepare("INSERT INTO calculations (num1, operation, num2, result) VALUES (?, ?, ?, ?)");
            $stmt->execute([$num1, $operation, $num2, $result]);

            // Set session flag that a calculation was done
            $_SESSION['calculation_done'] = true;

            // Redirect to avoid form resubmission and show latest result/history
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Clear history on GET (page load / refresh) only if no recent calculation done

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['calculation_done'])) {
        $pdo->exec("TRUNCATE TABLE calculations");
    } else {
        // Clear flag so next refresh clears
        unset($_SESSION['calculation_done']);
    }
}

// Fetch history (last 10, newest last)
$stmt = $pdo->query("SELECT * FROM calculations ORDER BY id ASC LIMIT 10");
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Simple Calculator</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9f9;
            padding: 30px;
            max-width: 600px;
            margin: auto;
        }

        h2,
        h3 {
            text-align: center;
            color: #333;
        }

        form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        input[type="number"],
        input[type="text"] {
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        button {
            padding: 10px;
            font-size: 16px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background-color: #0056b3;
        }

        .result {
            text-align: center;
            font-size: 20px;
            color: green;
            margin-top: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        table th,
        table td {
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }

        table th {
            background-color: #007bff;
            color: white;
        }

        table tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        /* Edit form styling */
        .edit-form {
            background: #f0f0f0;
            padding: 10px;
            margin-top: 5px;
        }

        .edit-form input {
            margin-right: 5px;
            width: 80px;
        }

        .edit-form button {
            margin-left: 5px;
        }
    </style>
    <script>
        function toggleEditForm(id) {
            const form = document.getElementById('edit-form-' + id);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'table-row';
            } else {
                form.style.display = 'none';
            }
        }
    </script>
</head>

<body>

    <h2>Simple PHP Calculator</h2>

    <form method="post" action="">
        <input type="number" step="any" name="num1" placeholder="First number" required>
        <input type="text" name="operation" placeholder="+, -, *, /" required maxlength="1" pattern="[+\-*/]">
        <input type="number" step="any" name="num2" placeholder="Second number" required>
        <button type="submit" name="calculate">Calculate</button>
    </form>

    <?php if ($result !== null): ?>
        <div class="result">Result: <?= htmlspecialchars($result) ?></div>
    <?php endif; ?>

    <h3>Calculation History (last 10):</h3>
    <table>
        <tr>
            <th>#</th>
            <th>First Number</th>
            <th>Operation</th>
            <th>Second Number</th>
            <th>Result</th>
            <th>Calculated At</th>
            <th>Actions</th>
        </tr>
        <?php if ($history): ?>
            <?php foreach ($history as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['num1']) ?></td>
                    <td><?= htmlspecialchars($row['operation']) ?></td>
                    <td><?= htmlspecialchars($row['num2']) ?></td>
                    <td><?= htmlspecialchars($row['result']) ?></td>
                    <td><?= htmlspecialchars($row['calculated_at']) ?></td>
                    <td>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this entry?yes');">
                            <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                            <button type="submit">Delete</button>
                        </form>
                        <button onclick="toggleEditForm(<?= $row['id'] ?>)">Edit</button>
                    </td>
                </tr>
                <tr id="edit-form-<?= $row['id'] ?>" style="display:none;">
                    <form method="post">
                        <td><?= $row['id'] ?></td>
                        <td><input type="number" step="any" name="edit_num1" value="<?= $row['num1'] ?>" required></td>
                        <td><input type="text" name="edit_operation" value="<?= $row['operation'] ?>" required maxlength="1"
                                pattern="[+\-*/]"></td>
                        <td><input type="number" step="any" name="edit_num2" value="<?= $row['num2'] ?>" required></td>
                        <td colspan="2">
                            <input type="hidden" name="edit_id" value="<?= $row['id'] ?>">
                            <button type="submit" name="update_entry">Update</button>
                        </td>
                    </form>
                </tr>

            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7">No history found.</td>
            </tr>
        <?php endif; ?>
    </table>
</body>

</html>