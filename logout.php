<?php
declare(strict_types=1);

include 'auth.php';
auth_logout();
header('Location: login.php');
exit();

