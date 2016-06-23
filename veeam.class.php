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
  private $hardware_plan_urn;
  private $backup_create;
  private $backup_resource;
  private $replication_create;
  private $replication_resource;

  // Specify default values
  private $backup_server          = "REPLACE_BACKUP_SERVER";
  private $backup_repository      = "REPLACE_BACKUP_REPOSITORY";

  private $hardware_plan          = "REPLACE_HARDWARE_PLAN";
  private $lease_expiration       = "+3 months"; // see http://php.net/manual/en/function.strtotime.php

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
  public function __construct($host, $port, $username, $password, $backup, $replication) {
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

    $response = $this->client->post('sessionMngr/?v=v1_2');

    $this->backup_create = $backup;
    $this->replication_create = $replication;

    $this->session_id = (string) $response->getHeader('X-RestSvcSessionId');
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

  private function veeam_get_hardware_plan($hardware_plan_name) {
    $response = $this->client->get('cloud/hardwarePlans');

    foreach ($response->xml()->Ref as $hardware_plan) {
      if (strtolower($hardware_plan_name) == strtolower($hardware_plan['Name'])) {
        if (array_pop(explode("/", $hardware_plan->Links->Link[0]['Href'])) == $this->backup_server_id) {
          return (string) $hardware_plan['UID'];
        }
      }
    }
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
   * @param $username
   *
   * @return bool
   */
  private function veeam_check_username($username) {
    $response = $this->client->get('cloud/tenants?format=Entity');

    foreach ($response->xml()->CloudTenant as $tenant) {
      if (strtolower($username) == strtolower($tenant['Name'])) {
        return true;
      }
    }

    return false;
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
  Function from http://stackoverflow.com/a/5965940
  **/

  private function array_to_xml( $data, $xml_data ) {
    foreach( $data as $key => $value ) {
      if( is_array($value)) {
        if( is_numeric($key)) {
          $key = 'item'.$key; //dealing with <0/>..<n/> issues
        }
        $subnode = $xml_data->addChild($key);
        $this->array_to_xml($value, $subnode);
      } else {
        $xml_data->addChild("$key",htmlspecialchars("$value"));
      }
    }

    return $xml_data;
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
    $xml->addAttribute('xmlns', 'http://www.veeam.com/ent/v1.0');
    $xml->addAttribute('xmlns:xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $xml->addAttribute('Href', $this->client->getBaseUrl() . "{$href}");

    $xml = $this->array_to_xml($content, $xml);

    return $xml->asXml();
  }

  /**
   * @param string $tenant_name
   * @param string $tenant_description
   * @param int $tenant_resource_quota
   * @param bool $enabled
   * @return string $tenant_result JSON encoded
   */
  private function veeam_create_cloud_tenant($tenant_name = FALSE, $tenant_description = FALSE, $tenant_resource_quota = FALSE, $enabled = 0) {
    // Create tenant XML request
    // Refer to helpcenter.veeam.com for more information
    $url = 'cloud/tenants';
    $xml_data = $this->create_xml(
      'CreateCloudTenantSpec',
      'CloudTenant',
      $url,
      array(
        'Name'                  => $tenant_name,
        'Description'           => $tenant_description,
        'Password'              => $this->tenant_password,
        'Enabled'               => (int) $enabled,
        'LeaseExpirationDate'   => date('c', strtotime($this->lease_expiration)),
        'Resources'             => $this->backup_resource,
        'ComputeResources'      => $this->replication_resource,
        'ThrottlingEnabled'     => 'true',
        'ThrottlingSpeedLimit'  => 1,
        'ThrottlingSpeedUnit'   => 'MBps',
        'PublicIpCount'         => 0,
        'BackupServerUid'  => $this->backup_server_urn
      )
    );

    // POST XML request to RESTful API
    $response = $this->client->post($url, array('body' => $xml_data, "headers" => array('Content-Type' => 'text/xml')));

    // Wait for tenant create task to finish
    $tenant_task_id = (string) $response->xml()->TaskId;
    $tenant_id = $this->veeam_task_subscriber($tenant_task_id, 'CloudTenant');

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

    if ($this->backup_create) {
      $this->backup_repository_urn = $this->veeam_get_backup_repository($this->backup_server_id, $this->backup_repository);
      $this->backup_repository_id = $this->veeam_get_id_from_urn($this->backup_repository_urn);

      $this->backup_resource = array(
        'BackupResource' =>  array(
          'Name'            => 'cloud-' . $this->tenant_name . '-01',
          'RepositoryUid'   => $this->backup_repository_urn,
          'QuotaMb'         => $this->tenant_resource_quota
        )
      );
    }

    if ($this->replication_create) {
      $this->hardware_plan_urn = $this->veeam_get_hardware_plan($this->hardware_plan);
      $this->replication_resource = array(
        'ComputeResource' =>  array(
          'CloudHardwarePlanUid'          => $this->hardware_plan_urn,
          'PlatformType'                  => 'VMware',
          'UseNetworkFailoverResources'   => 'false'
        )
      );
    }

    echo $this->veeam_create_cloud_tenant($tenant_name, $tenant_description, $tenant_resource_quota, $enabled);
  }
}
