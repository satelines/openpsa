<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_contacts_personTest extends openpsa_testcase
{
    public function testCRUD()
    {
        midcom::get('auth')->request_sudo('org.openpsa.contacts');
        $person = new org_openpsa_contacts_person_dba();
        $person->lastname = 'TEST PERSON ' . __CLASS__;
        $person->_use_activitystream = false;
        $person->_use_rcs = false;

        $stat = $person->create();
        $this->assertTrue($stat);
        $this->register_object($person);
        $this->assertEquals(org_openpsa_contacts_person_dba::TYPE_PERSON, $person->orgOpenpsaObtype);
        $this->assertEquals('rname', $person->get_label_property());

        $person->firstname = 'FIRSTNAME';
        $stat = $person->update();
        $this->assertTrue($stat);
        $this->assertEquals('TEST PERSON ' . __CLASS__ . ', FIRSTNAME', $person->get_label());

        $stat = $person->delete();
        $this->assertTrue($stat);

        midcom::get('auth')->drop_sudo();
    }
}
?>