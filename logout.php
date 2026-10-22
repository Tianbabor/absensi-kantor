<?php require 'config.php';
if (me()) logact('Logout');
session_destroy();
header('Location: index.php');
