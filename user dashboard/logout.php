<?php

session_start();
session_unset();
session_destroy();

header("Location: /food-system/food-system-php/Log-in Form/login.php");

die();