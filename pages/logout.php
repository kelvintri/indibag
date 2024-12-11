<?php
$auth = new Auth();
$auth->logout();
header('Location: /login');
exit;
?> 