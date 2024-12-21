<?php
session_start();
require '../database/db.php';
// Dynamic variables can be defined here
$title = "Admin Panel";
$userName = "Server NextGen";
$notificationsCount = 4;
$unreadMails = 7;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_name = isset($_POST['plan_name']) ? trim($_POST['plan_name']) : null;
    $plan_price = isset($_POST['plan_price']) ? trim($_POST['plan_price']) : null;
    $website_count = isset($_POST['website_count']) ? ($_POST['website_count']) : null;
    $disk_space = isset($_POST['disk_space']) ? trim($_POST['disk_space']) : null;
    $bandwidth = isset($_POST['bandwidth']) ? trim($_POST['bandwidth']) : null;
    $databases_count = isset($_POST['databases_count']) ? trim($_POST['databases_count']) : null;
    $users_count = isset($_POST['users_count']) ? trim($_POST['users_count']) : null;
    $email_accounts_count = isset($_POST['email_accounts_count']) ? trim($_POST['email_accounts_count']) : null;

    try {
        // Prepare and execute the insertion query
        $sql = "INSERT INTO tbl_plans (plan_name, plan_price, website_count, disk_space, bandwidth, databases_count, users_count, email_accounts_count) VALUES (:plan_name, :plan_price, :website_count, :disk_space, :bandwidth, :databases_count, :users_count, :email_accounts_count)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':plan_name', $plan_name, PDO::PARAM_STR);
        $stmt->bindParam(':plan_price', $plan_price, PDO::PARAM_INT);
        $stmt->bindParam(':website_count', $website_count, PDO::PARAM_INT);
        $stmt->bindParam(':disk_space', $disk_space, PDO::PARAM_STR);
        $stmt->bindParam(':bandwidth', $bandwidth, PDO::PARAM_STR);
        $stmt->bindParam(':databases_count', $databases_count, PDO::PARAM_INT);
        $stmt->bindParam(':users_count', $users_count, PDO::PARAM_INT);
        $stmt->bindParam(':email_accounts_count', $email_accounts_count, PDO::PARAM_INT);
        // $message = "values: " . strval($stmt);
        // var_dump($_POST);
        // exit;
        $stmt->execute();

        $message = "Plan added successfully!";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $title; ?></title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="../assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendors/typicons/typicons.css">
    <link rel="stylesheet" href="../assets/vendors/simple-line-icons/css/simple-line-icons.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.css">
    <!-- Plugin css for this page -->
    <link rel="stylesheet" href="../assets/vendors/datatables.net-bs4/dataTables.bootstrap4.css">
    <link rel="stylesheet" type="text/css" href="../assets/js/select.dataTables.min.css">
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- endinject -->
    <link rel="shortcut icon" href="../assets/images/favicon.png" />
</head>

<body class="with-welcome-text">
    <div class="container-scroller">
        <div class="row p-0 m-0 proBanner" id="proBanner">
            <div class="col-md-12 p-0 m-0">
                <div class="card-body card-body-padding px-3 d-flex align-items-center justify-content-between">
                    <div class="ps-lg-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <p class="mb-0 fw-medium me-3 buy-now-text"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- partial:partials/_navbar.html -->
        <nav class="navbar default-layout col-lg-12 col-12 p-0 fixed-top d-flex align-items-top flex-row">
            <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
                <div class="me-3">
                    <button class="navbar-toggler navbar-toggler align-self-center" type="button"
                        data-bs-toggle="minimize">
                        <span class="icon-menu"></span>
                    </button>
                </div>
                <div>
                    <a class="navbar-brand brand-logo" href="../admin_panel.php">
                        <img src="../assets/images/logo.svg" alt="logo" />
                    </a>
                    <a class="navbar-brand brand-logo-mini" href="../admin_panel.php">
                        <img src="../assets/images/logo-mini.svg" alt="logo" />
                    </a>
                </div>
            </div>
            <div class="navbar-menu-wrapper d-flex align-items-top">
                <ul class="navbar-nav">
                    <li class="nav-item fw-semibold d-none d-lg-block ms-0">
                        <h1 class="welcome-text">Good Morning, <span class="text-black fw-bold">Server NextGen</span>
                        </h1>
                    </li>
                </ul>
                <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button"
                    data-bs-toggle="offcanvas">
                    <span class="mdi mdi-menu"></span>
                </button>
            </div>
        </nav>
        <!-- partial -->
        <div class="container-fluid page-body-wrapper">
            <!-- partial:partials/_sidebar.html -->
            <nav class="sidebar sidebar-offcanvas" id="sidebar">
                <ul class="nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../admin_panel.php">
                            <i class="mdi mdi-grid-large menu-icon"></i>
                            <span class="menu-title">Dashboard</span>
                        </a>
                    </li>
                    <!-- <li class="nav-item nav-category">Plans</li> -->
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="collapse" href="#ui-basic" aria-expanded="false"
                            aria-controls="ui-basic">
                            <i class="menu-icon mdi mdi-floor-plan"></i>
                            <span class="menu-title">Plans</span>
                            <i class="menu-arrow"></i>
                        </a>
                        <div class="collapse" id="ui-basic">
                            <ul class="nav flex-column sub-menu">
                                <li class="nav-item"> <a class="nav-link" href="add_plan.php">Add
                                        Plan</a></li>
                                <li class="nav-item"> <a class="nav-link" href="edit_plan.php">Edit
                                        Plan</a></li>
                                <li class="nav-item"> <a class="nav-link" href="delete_plan.php">Delete Plan</a></li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="collapse" href="#auth" aria-expanded="false"
                            aria-controls="auth">
                            <i class="menu-icon mdi mdi-file-document"></i>
                            <span class="menu-title">Documentation</span>
                            <i class="menu-arrow"></i>
                        </a>
                        <div class="collapse" id="auth">
                            <ul class="nav flex-column sub-menu">
                                <li class="nav-item"> <a class="nav-link" href="pages/samples/blank-page.html"> Blank
                                        Page </a></li>
                                <li class="nav-item"> <a class="nav-link" href="pages/samples/login.html"> Login </a>
                                </li>
                                <li class="nav-item"> <a class="nav-link" href="pages/samples/register.html"> Register
                                    </a></li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </nav>
            <!-- partial -->
            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="home-tab">
                                <!-- <div class="d-sm-flex align-items-center justify-content-between border-bottom">
                                    <ul class="nav nav-tabs" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active ps-0" id="home-tab" data-bs-toggle="tab"
                                                href="#dashboard" role="tab" aria-controls="dashboard"
                                                aria-selected="true">Dashboard</a>
                                        </li>
                                    </ul>
                                </div> -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- partial -->
                <div class="main-panel">
                    <div class="content-wrapper">
                        <div class="row">
                            <div class="col-md-6 grid-margin stretch-card">
                                <div class="card">
                                    <div class="card-body">
                                        <h4 class="card-title">Plans</h4>
                                        <p class="card-description"> Add Plan </p>
                                        <!-- Display message -->
                                        <?php if (!empty($message)): ?>
                                            <p><?php echo htmlspecialchars($message); ?></p>
                                        <?php endif; ?>
                                        <form class="forms-sample" action="" method="POST">
                                            <div class="form-group row">
                                                <label for="plan_name" class="col-sm-3 col-form-label">Plan Name</label>
                                                <div class="col-sm-9">
                                                    <input type="text" class="form-control" id="plan_name" name="plan_name"
                                                        placeholder="Plan name">
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label for="plan_price" class="col-sm-3 col-form-label">Plan
                                                    Price</label>
                                                <div class="col-sm-9">
                                                    <input type="text" class="form-control" id="plan_price" name="plan_price"
                                                        placeholder="Plan price">
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label for="website_count"
                                                    class="col-sm-3 col-form-label">Website</label>
                                                <div class="col-sm-9">
                                                    <input type="text" class="form-control" id="website_count" name="website_count"
                                                        placeholder="Website count">
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label for="disk_space" class="col-sm-3 col-form-label">Disk
                                                    Space</label>
                                                <div class="col-sm-9">
                                                    <input type="text" class="form-control" id="disk_space" name="disk_space"
                                                        placeholder="Disk space">
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label for="bandwidth" class="col-sm-3 col-form-label">Bandwidth</label>
                                                <div class="col-sm-9">
                                                    <input type="text" class="form-control" id="bandwidth" name="bandwidth"
                                                        placeholder="bandwidth">
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label for="databases_count"
                                                    class="col-sm-3 col-form-label">Databases</label>
                                                <div class="col-sm-9">
                                                    <input type="text" class="form-control" id="databases_count" name="databases_count"
                                                        placeholder="Databases count">
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label for="users_count" class="col-sm-3 col-form-label">Users</label>
                                                <div class="col-sm-9">
                                                    <input type="text" class="form-control" id="users_count" name="users_count"
                                                        placeholder="Users count">
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label for="email_accounts_count" class="col-sm-3 col-form-label">Email
                                                    Accounts</label>
                                                <div class="col-sm-9">
                                                    <input type="text" class="form-control" id="email_accounts_count" name="email_accounts_count"
                                                        placeholder="Email accounts count">
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary me-2">Submit</button>
                                            <button class="btn btn-light">Cancel</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- content-wrapper ends -->
                    <!-- partial:../../partials/_footer.html -->
                    <footer class="footer">
                        <div class="d-sm-flex justify-content-center justify-content-sm-between">
                            <!-- <span class="text-muted text-center text-sm-left d-block d-sm-inline-block">Premium <a
                                    href="https://www.bootstrapdash.com/" target="_blank">Bootstrap admin template</a>
                                from BootstrapDash.</span> -->
                            <!-- <span class="float-none float-sm-end d-block mt-1 mt-sm-0 text-center">Copyright © 2023. All
                                rights reserved.</span> -->
                        </div>
                    </footer>
                    <!-- partial -->
                </div>
            </div>
            <!-- main-panel ends -->
        </div>
        <!-- page-body-wrapper ends -->
    </div>
    <!-- container-scroller -->
    <!-- plugins:js -->
    <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="../assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
    <!-- endinject -->
    <!-- Plugin js for this page -->
    <script src="../assets/vendors/chart.js/chart.umd.js"></script>
    <script src="../assets/vendors/progressbar.js/progressbar.min.js"></script>
    <!-- End plugin js for this page -->
    <!-- inject:js -->
    <script src="../assets/js/off-canvas.js"></script>
    <script src="../assets/js/template.js"></script>
    <script src="../assets/js/settings.js"></script>
    <script src="../assets/js/hoverable-collapse.js"></script>
    <script src="../assets/js/todolist.js"></script>
    <!-- endinject -->
    <!-- Custom js for this page-->
    <script src="../assets/js/jquery.cookie.js" type="text/javascript"></script>
    <script src="../assets/js/dashboard.js"></script>
    <!-- <script src="../assets/js/Chart.roundedBarCharts.js"></script> -->
    <!-- End custom js for this page-->
</body>

</html>