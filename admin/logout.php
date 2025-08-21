<?php
require_once '../config.php';
require_once '../function.php';

// Destroy session
session_destroy();

// Redirect to login page
redirect(BASE_URL . '/admin/login.php');
?>