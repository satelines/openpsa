<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

if (!defined('OPENPSA_TEST_ROOT'))
{
    define('OPENPSA_TEST_ROOT', dirname(dirname(dirname(dirname(dirname(__FILE__))))) . DIRECTORY_SEPARATOR);
    require_once(OPENPSA_TEST_ROOT . 'rootfile.php');
}

require_once OPENPSA_TEST_ROOT . 'org/openpsa/directmarketing/__helper/campaign.php';

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_directmarketing_campaign_messageTest extends openpsa_testcase
{
    public function testCRUD()
    {
        $helper = new openpsa_test_campaign_helper($this);
        $campaign = $helper->get_campaign();

        midcom::get('auth')->request_sudo('org.openpsa.directmarketing');

        $message = new org_openpsa_directmarketing_campaign_message_dba();

        $stat = $message->create();
        $this->assertFalse($stat);

        $message = new org_openpsa_directmarketing_campaign_message_dba();
        $message->campaign = $campaign->id;
        $stat = $message->create();
        $this->assertTrue($stat);

        $this->register_object($message);

        $message->title = 'TEST';

        $stat = $message->update();
        $this->assertTrue($stat);
        $message->refresh();

        $this->assertEquals('TEST', $message->title);

        $stat = $message->delete();
        $this->assertTrue($stat);

        midcom::get('auth')->drop_sudo();
    }
}
?>
