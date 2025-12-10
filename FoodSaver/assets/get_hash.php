<?php
// Use a different password just in case the old one has issues
$admin_password = 'NewAdmin123!'; 
$new_hash = password_hash($admin_password, PASSWORD_DEFAULT);
echo $new_hash; 
// COPY THIS ENTIRE NEW HASH STRING!
?>