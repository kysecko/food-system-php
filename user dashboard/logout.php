<?php

session_start();
session_unset();
session_destroy();

header("Location: /Food_System/Log-in Form/login.php");

die();