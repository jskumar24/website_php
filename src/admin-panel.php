<?php
session_start();
require 'database/db.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #66aafa;
            padding-top: 20px;
        }

        .sidebar a {
            padding: 10px 15px;
            text-decoration: none;
            font-size: 18px;
            color: #f8f9fa;
            display: block;
            cursor: pointer;
        }

        .sidebar a:hover {
            background-color: #f9f9f9;
        }

        .content {
            margin-left: 260px;
            padding: 20px;
        }

        .section {
            display: none;
        }

        .section.active {
            display: block;
        }
    </style>
</head>

<body>

    <div class="sidebar">
        <h2 class="text-center text-white">Admin Panel</h2>
        <a onclick="showSection('dashboard')">Dashboard</a>
        <a onclick="showSection('products')">Products</a>
        <a onclick="showSection('main_content')">Main Content</a>
    </div>

    <div class="content">
        <div class="container">
            <div id="dashboard" class="section active">
                <h1>Dashboard</h1>
                <p>Overview of the system.</p>
            </div>

            <div id="products" class="section">
                <h1>Products</h1>
                <p>Manage product listings here.</p>

                <!-- Products Table -->
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        global $pdo;

                        $stmt = $pdo->query("SELECT * FROM products");
                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($result as $row) {
                            echo "<tr>
                                    <td>" . $row['id'] . "</td>
                                    <td>" . $row['name'] . "</td>
                                    <td>" . $row['price'] . "</td>
                                    <td>
                                        <button class='btn btn-primary btn-sm' onclick='editProduct(" . $row['id'] . ")'>Edit</button>
                                        <button class='btn btn-danger btn-sm' onclick='deleteProduct(" . $row['id'] . ")'>Delete</button>
                                    </td>
                                  </tr>";
                        }

                        ?>
                    </tbody>
                </table>

                <!-- Add Product Form -->
                <h2>Add Product</h2>
                <form action="add_product.php" method="POST">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Price</label>
                        <input type="text" class="form-control" id="price" name="price" required>
                    </div>
                    <button type="submit" class="btn btn-success">Add Product</button>
                </form>
            </div>

            <div id="main_content" class="section">
                <h1>Main Content</h1>
                <p>Adjust the main content displayed on the site here.</p>

                <!-- Main Content Table -->
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Content</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php

                        global $pdo;

                        $stmt = $pdo->query("SELECT * FROM main_content");
                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($result as $row) {
                            echo "<tr>
                                    <td>" . $row['id'] . "</td>
                                    <td>" . $row['title'] . "</td>
                                    <td>" . $row['content'] . "</td>
                                    <td>
                                        <button class='btn btn-primary btn-sm' onclick='editContent(" . $row['id'] . ")'>Edit</button>
                                        <button class='btn btn-danger btn-sm' onclick='deleteContent(" . $row['id'] . ")'>Delete</button>
                                    </td>
                                  </tr>";
                        }

                        ?>
                    </tbody>
                </table>

                <!-- Add Content Form -->
                <h2>Add Content</h2>
                <form action="add_content.php" method="POST">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="content" class="form-label">Content</label>
                        <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Add Content</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showSection(sectionId) {
            // Hide all sections
            const sections = document.querySelectorAll('.section');
            sections.forEach(section => section.classList.remove('active'));

            // Show the selected section
            const selectedSection = document.getElementById(sectionId);
            selectedSection.classList.add('active');
        }

        function editProduct(productId) {
            // Redirect to edit page
            window.location.href = `edit_product.php?id=${productId}`;
        }

        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product?')) {
                // Redirect to delete page
                window.location.href = `delete_product.php?id=${productId}`;
            }
        }

        function editContent(contentId) {
            // Redirect to edit page
            window.location.href = `edit_content.php?id=${contentId}`;
        }

        function deleteContent(contentId) {
            if (confirm('Are you sure you want to delete this content?')) {
                // Redirect to delete page
                window.location.href = `delete_content.php?id=${contentId}`;
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>