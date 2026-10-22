<?php

require_once __DIR__ . '/../includes/bootstrap.php';

header('Location: ' . (!empty($_SESSION['admin_id']) ? 'dashboard' : 'login'));

exit;
