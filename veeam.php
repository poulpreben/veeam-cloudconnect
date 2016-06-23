<?php
/**
 * FILE
 */
error_reporting(E_ALL || E_STRICT);

require 'vendor/autoload.php';
require 'veeam.class.php';

header('Content-Type: application/json');

if (isset($_POST['action']) && (isset($_POST['create_backup']) || isset($_POST['create_replication'])) && $_POST['action'] == "create_tenant") {


  $rest_server = 'REPLACE_SERVER';
  $rest_port   = 'REPLACE_PORT';
  $rest_user   = 'REPLACE_USER';
  $rest_pass   = 'REPLACE_PASS';
  
  // Not very clean, but it works(tm)
  $username = strtolower($_POST['username']);
  $description = $_POST['full_name'] . " - " . $_POST['company_name'];
  
  $veeam = new Veeam($rest_server, $rest_port, $rest_user, $rest_pass, $_POST['create_backup'], $_POST['create_replication']);

  // Create a user with 10 GB quota and leave them enabled.
  $veeam->run($username, $description, 10240, TRUE);
}
