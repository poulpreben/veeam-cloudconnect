<?php
/**
 * FILE
 */
error_reporting(E_ALL || E_STRICT);

require 'vendor/autoload.php';
require 'veeam.class.php';

header('Content-Type: application/json');

if (isset($_POST['action']) && (isset($_POST['create_backup']) || isset($_POST['create_replication'])) && $_POST['action'] == "create_tenant") {
  
  // Not very clean, but it works(tm)
  $username = strtolower($_POST['username']);
  $description = $_POST['full_name'] . " - " . $_POST['company_name'];
  
  $veeam = new Veeam('10.0.0.11', 9399, 'VEEAM-VBR01\\Administrator', '***', $_POST['create_backup'], $_POST['create_replication']);
  
  // Create a user with 10 GB quota and leave them enabled.
  $veeam->run($username, $description, 10240, TRUE);  
} 
