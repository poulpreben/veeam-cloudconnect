<?php
/**
 * FILE
 */
error_reporting(E_ALL || E_STRICT);

require 'vendor/autoload.php';
?>
<html>
  <head>
    <title>Veeam RESTful demo</title>
    <script type="text/javascript" src="vendor/components/jquery/jquery.min.js"></script>
    <script type="text/javascript" src="vendor/components/jqueryui/jquery-ui.min.js"></script>
    <script type="text/javascript">
    $(document).ready(function() {
      $(document).ajaxStart(function(){
        $('#loading').show();
      });
      
      $(document).ajaxComplete(function(){
        $('#loading').hide();
      });
    
      $('#veeam_cloud_connect_form').submit(function() {
        $.post($(this).attr('action'), $(this).serialize(), function(json) {
          $('#result').html('<h1>Username: ' + json.username + '</h1><br /><h1>Password: ' + json.password + '</h1>')
        }, 'json');
        return false;
      });
    });
    </script>
    
    <link rel="stylesheet" 
  </head>
  <body>
  
  <form id="veeam_cloud_connect_form" method="POST" action="/veeam.php">
    <input type="hidden" name="action" value="create_tenant" />
    <label for="username">Username</label>
    <input type="text" name="username" value="preben" />
    <label for="full_name">Full name</label>
    <input type="text" name="full_name" value="Preben Berg" />
    <label for="company_name">Company name</label>
    <input type="text" name="company_name" value="Veeam" />
    <input type="submit" value="Create account" />
  </form>
  
  <div id="loading" style="display:none;"><h1>LOADING</h1></div>
  
  <div id="result"></div>
  
  </body>  
</html>