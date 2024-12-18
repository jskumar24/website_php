<?php
session_start();
require 'database/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $content = $_POST['content'];

    try {
        // Insert main content query
        $sql = "INSERT INTO main_content (title, content) VALUES (:title, :content)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':content', $content, PDO::PARAM_STR);
        $stmt->execute();

        // Redirect after successful insertion
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        echo "Error adding content: " . $e->getMessage();
    }
}
?>
