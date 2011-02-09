<?php
/**
 * Setup file for running unit tests
 *
 * Usage: phpunit --no-globals-backup ./
 */

// Check that the environment is a working one
if (!extension_loaded('midgard2'))
{
    throw new Exception("OpenPSA requires Midgard2 PHP extension to run");
}
if (!ini_get('midgard.superglobals_compat'))
{
    throw new Exception('You need to set midgard.superglobals_compat=On in your php.ini to run OpenPSA with Midgard2');
}
if (!class_exists('midgard_topic'))
{
    throw new Exception('You need to install OpenPSA MgdSchemas from the "schemas" directory to the Midgard2 schema directory');
}

ini_set('memory_limit', '68M');

// Path to the MidCOM environment
define('MIDCOM_ROOT', realpath(dirname(__FILE__)) . '/../lib');
define('OPENPSA2_PREFIX', dirname($_SERVER['SCRIPT_NAME']) . '/..');

// Initialize the $_MIDGARD superglobal
$_MIDGARD = array
(
    'argv' => array(),

    'user' => 0,
    'admin' => false,
    'root' => false,

    'auth' => false,
    'cookieauth' => false,

    // General host setup
    'page' => 0,
    'debug' => false,

    'host' => 0,
    'style' => 0,
    'author' => 0,
    'config' => array
    (
        'prefix' => '',
        'quota' => false,
        'unique_host_name' => 'openpsa',
        'auth_cookie_id' => 1,
    ),

    'schema' => array
    (
        'types' => array(),
    ),
);

$_MIDGARD_CONNECTION =& midgard_connection::get_instance();

$GLOBALS['midcom_config_local'] = array();
$GLOBALS['midcom_config_local']['person_class'] = 'openpsa_person';
$GLOBALS['midcom_config_local']['theme'] = 'OpenPsa2';

if (file_exists(MIDCOM_ROOT . '/../config.inc.php'))
{
    include(MIDCOM_ROOT . '/../config.inc.php');
}
else
{
    include(MIDCOM_ROOT . '/../config-default.inc.php');
}

if (! defined('MIDCOM_STATIC_URL'))
{
    define('MIDCOM_STATIC_URL', '/openpsa2-static');
}

$_SERVER = Array();
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['REMOTE_ADDR'] = 'unittest dummy connection';
$_SERVER['REQUEST_URI'] = '/midcom-test-init';

// Include the MidCOM environment for running OpenPSA
require(MIDCOM_ROOT . '/midcom.php');

class test_helper
{
    public static function create_user($login = false)
    {
        $_MIDCOM->auth->request_sudo('midcom.core');
        $person = new midcom_db_person();
        $password = 'password_' . time();
        $username = __CLASS__ . ' user ' . time();

        $_MIDCOM->auth->request_sudo('midcom.core');
        $person->create();

        $account = midcom_core_account::get($person);
        $account->set_password($password);
        $account->set_username($username);
        $account->save();
        $_MIDCOM->auth->drop_sudo();
        if ($login)
        {
            $_MIDCOM->auth->login($username, $password);
            $_MIDCOM->auth->_sync_user_with_backend();
        }
        return $person;
    }
}
?>
