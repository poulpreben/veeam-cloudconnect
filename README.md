veeam-cloudconnect
==================

Veeam RESTful API demo for Cloud Connect

## Dependencies
Make sure you download dependencies using `composer`. This project depends on [GuzzleHTTP](https://github.com/guzzle/guzzle) and [Twitter Bootstrap](http://getbootstrap.com/) for CSS.

## Installation
### 1. Download and install composer
    curl -sS https://getcomposer.org/installer | /usr/bin/php && /bin/mv -f composer.phar /usr/local/bin/composer

### 2. Clone this repository
    git clone https://github.com/poulpreben/veeam-cloudconnect.git

### 3. Initialize Composer
    composer install

## Usage
Point your web browser to `index.php` and you should see something like this:
![Screenshot](http://i.imgur.com/tcZqcwp.png "Screenshot")

## Configuration
There are a few variables that need be changed before these sample scripts will work.
### veeam.class.php
This script contains the functionality for interacting with Veeam RESTful API.

    // Specify default values
    private $backup_server          = "vbr9.vclass.local";
    private $backup_repository      = "Default Backup Repository";
  
    private $hardware_plan          = "hwplan-default";
    private $lease_expiration       = "+3 months"; // see http://php.net/manual/en/function.strtotime.php
  
    private $tenant_name            = "default-tenant-name"; // This should never happen. If so, you need to sanitize your input better.
    private $tenant_description     = "Veeam RESTful API demo - default description";
    private $tenant_resource_quota  = 102400;

### veeam.php
This script handles the request from the web form. It has not received too much attention at this point, so it is highly recommended to add in additional santiy checks and form verification before sending it off to the controller.

Make sure to change these values to fit your environment.

    $veeam = new Veeam('10.0.0.7', 9399, 'VEEAM-VBR01\\Administrator', '***');

**Note:** There is currently only added support for HTTP. If you want to use HTTPS, please change settings accordingly in `__construct()` in `veeam.class.php`.
