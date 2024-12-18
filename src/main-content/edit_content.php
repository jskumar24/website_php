<?php
session_start();
require 'database/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $content = $_POST['content'];

    try {
        // Update query
        $sql = "UPDATE main_content SET title = :title, content = :content WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':content', $content, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        // Redirect to index after successful update
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        echo "Error updating content: " . $e->getMessage();
    }
} else {
    $id = $_GET['id'];

    try {
        // Fetch content details
        $sql = "SELECT * FROM main_content WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $contentData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contentData) {
            echo "Content not found!";
            exit;
        }
    } catch (PDOException $e) {
        echo "Error fetching content: " . $e->getMessage();
    }
}
?>

<!-- Edit Content Form -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Content</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>

<body>
    <div class="container mt-5">
        <h2>Edit Content</h2>
        <form action="edit_content.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($contentData['id']); ?>">
            <div class="mb-3">
                <label for="title" class="form-label">Title</label>
                <input type="text" class="form-control" id="title" name="title"
                    value="<?php echo htmlspecialchars($contentData['title']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="content" class="form-label">Content</label>
                <textarea class="form-control" id="content" name="content" rows="5"
                    required><?php echo htmlspecialchars($contentData['content']); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>