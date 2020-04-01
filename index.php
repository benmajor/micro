<?php

define('MICRO_INSTALL_DIR', '_install');

# Check if the installer folder exists:
if( !isset($_GET['installed']) && file_exists(MICRO_INSTALL_DIR) && is_dir(MICRO_INSTALL_DIR) && file_exists(MICRO_INSTALL_DIR.'/index.php') )
{
    http_response_code(302);
    header('Location: /'.basename(dirname(__FILE__)).'/'.MICRO_INSTALL_DIR.'/index.php');
    exit();
}

require 'app/load.php';

$app->run();