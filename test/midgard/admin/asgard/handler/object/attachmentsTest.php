<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

if (!defined('OPENPSA_TEST_ROOT'))
{
    define('OPENPSA_TEST_ROOT', dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . DIRECTORY_SEPARATOR);
    require_once(OPENPSA_TEST_ROOT . 'rootfile.php');
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class midgard_admin_asgard_handler_object_attachmentsTest extends openpsa_testcase
{
    protected static $_object;

    public static function setUpBeforeClass()
    {
        self::$_object = self::create_class_object('midcom_db_topic');
    }

    public function testHandler_create()
    {
        $this->create_user(true);
        midcom::get('auth')->request_sudo('midgard.admin.asgard');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard', 'object', 'attachments', self::$_object->guid));
        $this->assertEquals('____mfa-asgard-object_attachments', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }
}
?>