<?php
session_start();
require 'database/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $price = $_POST['price'];

    try {
        // Insert product query
        $sql = "INSERT INTO products (name, price) VALUES (:name, :price)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':price', $price, PDO::PARAM_STR);
        $stmt->execute();

        // Redirect after successful insertion
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        echo "Error adding product: " . $e->getMessage();
    }
}
?>