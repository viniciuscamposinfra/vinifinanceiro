<?php

require "config.php";

if (isset($_SESSION["id"])) {

    header("Location: pages/dashboard.php");
    exit;

}

header("Location: login.php");
exit;