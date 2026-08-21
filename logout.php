<?php
session_start();
session_destroy();
header('Location: /gym/login.php');
exit;
