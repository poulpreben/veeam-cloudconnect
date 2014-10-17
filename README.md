veeam-cloudconnect
==================

Veeam RESTful API demo for Cloud Connect

## Dependencies
Make sure you download dependencies using `composer`. This project depends on `GuzzleHTTP` which by the way is pretty awesome.

## Installation
  1. Download and install composer: https://getcomposer.org/download/
  2. Clone this repository
  3. Initialize with `composer init`

## Usage
Point your web browser to `index.php` and you should see something like this:
![Screenshot](http://i.imgur.com/CisdICL.png "Screenshot")

## Configuration
There are a few variables that need be changed before these sample scripts will work.
### veeam.class.php
This script contains the functionality for interacting with Veeam RESTful API.
```php
// Specify backup server name and repository too look for
private $backup_server          = "veeam-vbr01";
private $backup_repository      = "cc-repo";
// Set default values
private $tenant_name            = "default-tenant-name"; // This should never happen. If so, you need to sanitize your input
private $tenant_description     = "Veeam RESTful API demo - default description";
private $tenant_resource_quota  = 102400;
```
### veeam.php
This script handles the request from the web form. It has not received too much attention at this point, so it is highly recommended to add in additional santiy checks and form verification before sending it off to the controller.

Make sure to change these values to fit your environment.

```php
$veeam = new Veeam('10.0.0.7', 9399, 'VEEAM-VBR01\\Administrator', '***');
```
**Note:** There is currently only added support for HTTP. If you want to use HTTPS, please change settings accordingly in `__construct()` in `veeam.class.php`.
