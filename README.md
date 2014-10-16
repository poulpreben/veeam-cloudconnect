veeam-cloudconnect
==================

Veeam RESTful API demo for Cloud Connect

## Dependencies
Make sure you download dependencies using `composer`. This project depends on `GuzzleHTTP` which by the way is pretty awesome.

## Usage
The script requires access to the Veeam RESTful API, currently via HTTP (default port 9399). Adjust the default settings in the first section of the script, and specify your runtime code in the `$veeam->run()` function.

Just execute the script via browser or php-cli and it should create your first Cloud Connect tenant and report username and password in JSON format.

