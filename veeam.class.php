<?php
/**
 * FILE
 */
error_reporting(E_ALL || E_STRICT);

require 'vendor/autoload.php';

/**
 * Class Veeam
 */
class Veeam {
  private $client;
  private $session_id;
  private $backup_server_urn;
  private $backup_server_id;
  private $backup_repository_urn;
  private $backup_repository_id;
  
  // Specify backup server name and repository too look for
  private $backup_server          = "veeam-vbr01";
  private $backup_repository      = "cc-repo";
  
  // Cloud connect magic.
  private $tenant_name            = "default-tenant-name"; // This should never happen. If so, you need to sanitize your input better.
  private $tenant_description     = "Veeam RESTful API demo - default description";
  private $tenant_resource_quota  = 102400;
  private $tenant_password;       // Will be randomized in __construct();

  /**
   * @param $host
   * @param $port
   * @param $username
   * @param $password
   */
  public function __construct($host, $port, $username, $password) {
    $this->client = new GuzzleHttp\Client(array(
      "base_url" => "http://" . $host . ":" . $port . "/api/",
      "defaults" => array(
        "auth" => array(
          $username,
          $password
        ),
        "headers" => array(
          "Content-Type" => "text/xml"
        ),
      )
    ));

    $response = $this->client->post('sessionMngr/?v=v1_1');

    $this->session_id = $response->getHeader('X-RestSvcSessionId');
    $this->client->setDefaultOption('headers', array('X-RestSvcSessionId' => $this->session_id));
    
    $this->tenant_password = $this->veeam_generate_password(12);
  }
  
  public function __destruct() {
    $this->veeam_delete_session();
  }
  
  /**
  * @param int $length
  * 
  * @return string
  */
  private function veeam_generate_password($length = 8) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    return substr(str_shuffle($chars),0,$length);
  }
  
  /**
   * @param bool $name
   *
   * @return string
   */
  private function veeam_get_backup_server($name = FALSE) {
    $response = $this->client->get('backupServers');

    if ($name == FALSE) {
      $backup_server_urn = (string) $response->xml()->Ref['UID'];
      return $backup_server_urn;
    }
    else {
      $name = strtolower($name);
      foreach ($response->xml()->Ref as $backup_server) {
        if (strtolower($backup_server['Name']) == $name) {
          $backup_server_urn = (string) $backup_server['UID'];
          return $backup_server_urn;
        }
      }

      return "Not found!";
    }
  }

  /**
   * @param $backup_server_id
   * @param $backup_repository_name
   *
   * @return string
   */
  private function veeam_get_backup_repository($backup_server_id, $backup_repository_name) {
    $response = $this->client->get('backupServers/' . $backup_server_id . '/repositories');

    foreach ($response->xml()->Ref as $backup_repository) {
      if (strtolower($backup_repository_name) == strtolower($backup_repository['Name'])) {
        return (string) $backup_repository['UID'];
      }
    }

    return "Not found!";
  }

  /**
   * @param $urn
   *
   * @return mixed
   */
  private function veeam_get_id_from_urn($urn) {
    $parts = explode(':', $urn);
    return array_pop($parts);
  }

  /**
   * @return bool
   */
  private function veeam_delete_session() {
    if (isset($this->session_id)) {
      $response = $this->client->delete('logonSessions/' . base64_decode($this->session_id));
      return $response->getStatusCode() == 204;
    }
    else {
      return FALSE;
    }
  }

  /**
   * @param $task_id
   * @param $link_type
   */
  private function veeam_task_subscriber($task_id, $link_type) {
    $sleep = 1;

    do {
      $response = $this->client->get('tasks/' . $task_id);
      $task_state = (string) $response->xml()->State;

      if ($task_state == 'Finished') {

        // Missing check to see if successful.
        foreach ($response->xml()->Links->Link as $link) {
          if ($link['Type'] == $link_type) {
            return array_pop(explode("/", str_replace("?format=Entity", '', $link['Href'])));
          }
        }
      }

      sleep($sleep);
      $sleep++;

    } while ($task_state == "Running");
  }

  /**
   * @param $root
   * @param $type
   * @param $href
   * @param $content
   *
   * @return mixed
   */
  private function create_xml($root, $type, $href, $content) {
    global $client;

    $xml = new SimpleXmlElement('<' . $root . ' />');
    $xml->addAttribute('Type', $type);
    $xml->addAttribute('xmlns', 'http://www.veeam.com/ent/v1.0');
    $xml->addAttribute('xmlns:xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $xml->addAttribute('Href', $this->client->getBaseUrl() . "{$href}");

    $content = array_flip($content);
    array_walk_recursive($content, array($xml, 'addChild'));

    return $xml->asXml();
  }

  /**
   * @param $xml_data
   */
  private function veeam_create_cloud_tenant($tenant_name = FALSE, $tenant_description = FALSE, $tenant_resource_quota = FALSE, $enabled = 0) {
    // Override default values with input parameters if set
    if (!empty($tenant_name)) {
      $this->tenant_name = $tenant_name;
    }
    
    if (!empty($tenant_description)) {
      $this->tenant_description = $tenant_description;
    }
    
    if (!empty($tenant_resource_quota)) {
      $this->tenant_resource_quota = $tenant_resource_quota;
    }
    
    // Create tenant XML request
    // Refer to helpcenter.veeam.com for more information
    $url = 'cloud/tenants';
    $xml_data = $this->create_xml(
      'CreateCloudTenantSpec',
      'CloudTenant',
      $url,
      array(
        'BackupServerIdOrName'  => $this->backup_server_id,
        'Name'                  => $this->tenant_name,
        'Description'           => $this->tenant_description,
        'Password'              => $this->tenant_password,
        'Enabled'               => (int) $enabled
      )
    );
    
    // POST XML request to RESTful API
    $response = $this->client->post($url, array('body' => $xml_data, "headers" => array('Content-Type' => 'text/xml')));

    // Wait for tenant create task to finish
    $tenant_task_id = (string) $response->xml()->TaskId;
    $tenant_id = $this->veeam_task_subscriber($tenant_task_id, 'CloudTenant');
    
    // Create tenant resource XML request
    // Refer to helpcenter.veeam.com for more information
    $url = 'cloud/tenants/' . $tenant_id . '/resources';
    $xml_data = $this->create_xml(
      'CreateCloudTenantResourceSpec',
      'CloudTenant',
      $url,
      array(
        'Name'          => 'cloud-' . $this->tenant_name . '-01',
        'RepositoryUid' => $this->backup_repository_urn,
        'QuotaMb'       => $this->tenant_resource_quota,
        'Folder'        => '\\' . $this->tenant_name
      )
    );
    
    // POST XML request to RESTful API
    $response = $this->client->post($url, array('body' => $xml_data, 'headers' => array('Content-Type' => 'text/xml')));
    
    // Wait for tenant resource task to finish    
    $tenant_resource_task_id = (string) $response->xml()->TaskId;
    $tenant_resource_id = $this->veeam_task_subscriber($tenant_resource_task_id, 'CloudTenantResource');
    
    // Send output to web frontend
    $result = array('username' => $this->tenant_name, 'password' => $this->tenant_password, 'quota' => $this->tenant_resource_quota);
    
    return json_encode($result);
  }

  /**
   *
   */
  public function run($tenant_name, $tenant_description = "", $tenant_resource_quota = FALSE, $enabled = 0) {
    // Speed up the script by setting these to static values.
    $this->backup_server_urn = $this->veeam_get_backup_server($this->backup_server);
    $this->backup_server_id  = $this->veeam_get_id_from_urn($this->backup_server_urn);

    $this->backup_repository_urn = $this->veeam_get_backup_repository($this->backup_server_id, $this->backup_repository);
    $this->backup_repository_id  = $this->veeam_get_id_from_urn($this->backup_repository_urn);

    echo $this->veeam_create_cloud_tenant($tenant_name, $tenant_description, $tenant_resource_quota, $enabled);
  }
}
