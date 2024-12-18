<?php
session_start();
require 'database/db.php';

$id = $_GET['id'];

try {
    // Delete main content query
    $sql = "DELETE FROM main_content WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // Redirect after deletion
    header("Location: index.php");
    exit;
} catch (PDOException $e) {
    echo "Error deleting content: " . $e->getMessage();
}
?>