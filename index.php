<?php
// Dynamic variables can be defined here
$title = "Admin Panel";
$userName = "Server NextGen";
$notificationsCount = 4;
$unreadMails = 7;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $title; ?></title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="src/assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="src/assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="src/assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="src/assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="src/assets/vendors/typicons/typicons.css">
    <link rel="stylesheet" href="src/assets/vendors/simple-line-icons/css/simple-line-icons.css">
    <link rel="stylesheet" href="src/assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="src/assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.css">
    <!-- Plugin css for this page -->
    <link rel="stylesheet" href="src/assets/vendors/datatables.net-bs4/dataTables.bootstrap4.css">
    <link rel="stylesheet" type="text/css" href="src/assets/js/select.dataTables.min.css">
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <link rel="stylesheet" href="src/assets/css/style.css">
    <!-- endinject -->
    <link rel="shortcut icon" href="src/assets/images/favicon.png" />
</head>

<body class="with-welcome-text">
    <div class="container-scroller">
        <div class="row p-0 m-0 proBanner" id="proBanner">
            <div class="col-md-12 p-0 m-0">
                <div class="card-body card-body-padding px-3 d-flex align-items-center justify-content-between">
                    <!-- <div class="ps-lg-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <p class="mb-0 fw-medium me-3 buy-now-text">Free 24/7 customer support, updates, and more
                                with this template!</p>
                            <a href="https://www.bootstrapdash.com/product/star-admin-pro/" target="_blank"
                                class="btn me-2 buy-now-btn border-0">Buy Now</a>
                        </div>
                    </div> -->
                    <!-- <div class="d-flex align-items-center justify-content-between">
                        <a href="https://www.bootstrapdash.com/product/star-admin-pro/"><i
                                class="ti-home me-3 text-white"></i></a>
                        <button id="bannerClose" class="btn border-0 p-0">
                            <i class="ti-close text-white"></i>
                        </button>
                    </div> -->
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
                    <a class="navbar-brand brand-logo" href="index.php">
                        <img src="src/assets/images/logo.svg" alt="logo" />
                    </a>
                    <a class="navbar-brand brand-logo-mini" href="index.php">
                        <img src="src/assets/images/logo-mini.svg" alt="logo" />
                    </a>
                </div>
            </div>
            <div class="navbar-menu-wrapper d-flex align-items-top">
                <ul class="navbar-nav">
                    <li class="nav-item fw-semibold d-none d-lg-block ms-0">
                        <h1 class="welcome-text">Welcome, <span
                                class="text-black fw-bold"><?php echo $userName; ?></span></h1> 
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link count-indicator" id="notificationDropdown" href="#"
                            data-bs-toggle="dropdown">
                            <i class="icon-bell"></i>
                            <span class="count"><?php echo $notificationsCount; ?></span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link count-indicator" id="countDropdown" href="#" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <i class="icon-mail icon-lg"></i>
                            <span class="count"><?php echo $unreadMails; ?></span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
        <!-- partial -->
        <div class="container-fluid page-body-wrapper">
            <!-- Sidebar and main content -->
            <?php
            // Include additional sections or logic here
            ?>
        </div>
    </div>
    <!-- plugins:js -->
    <script src="src/assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="src/assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
    <script src="src/assets/vendors/chart.js/chart.umd.js"></script>
    <script src="src/assets/vendors/progressbar.js/progressbar.min.js"></script>
    <script src="src/assets/js/template.js"></script>
    <script src="src/assets/js/dashboard.js"></script>
</body>

</html>