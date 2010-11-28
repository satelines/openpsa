<?php
/**
 * @package midcom
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: group.php 26507 2010-07-06 13:31:06Z rambo $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM group implementation supporting Midgard Groups.
 *
 * @package midcom
 */
class midcom_core_group
{
    /**
     * The storage object on which we are based. This is usually a midgard_group
     * directly, as this class has to work outside of the ACLs. It must not be used
     * from the outside.
     *
     * Access to this member is restricted to the ACL user/group core. In case you
     * need a real Storage object for this group, call get_storage() instead.
     *
     * @var midgard_group
     * @access protected
     */
    var $_storage = null;

    /**
     * Name of the group
     *
     * The variable is considered to be read-only.
     *
     * @var string
     */
    public $name = '';

    /**
     * The identification string used to internally identify the group uniquely
     * in the system. This is usually some kind of group:$guid string combination.
     *
     * The variable is considered to be read-only.
     *
     * @var string
     */
    public $id = '';

    /**
     * The scope value, which must be set during the _load callback, indicates the "depth" of the
     * group in the inheritance tree. This is used during privilege merging in the content
     * privilege code, which needs a way to determine the proper ordering. Top level groups
     * start with a scope of 1.
     *
     * The variable is considered to be read-only.
     *
     * @var integer
     */
    public $scope = MIDCOM_PRIVILEGE_SCOPE_ROOTGROUP;

    /**
     * Contains the parent of the current group, cached for repeated accesses.
     *
     * @var midcom_core_group
     */
    private $_cached_parent_group = null;

    /**
     * The constructor retrieves the group identified by its name from the database and
     * prepares the object for operation.
     *
     * It will use the Query Builder to retrieve a group by its name and populate the
     * $storage, $name and $id members accordingly.
     *
     * Any error will call midcom_application::generate_error().
     *
     * @param mixed $id This is a valid identifier for the group to be loaded. Usually this is either
     *     a database ID or GUID for Midgard Groups or a valid complete MidCOM group identifier, which
     *     will work for all subclasses.
     */
    function __construct($id = null)
    {
        if (is_null($id))
        {
            $_MIDCOM->generate_error(MIDCOM_ERRCRIT, 'The class midcom_core_group is not default constructible.');
            // This will exit.
        }

        if (   is_string($id)
            && substr($id, 0, 6) == 'group:')
        {
            $this->_storage = new midgard_group();
            $id = substr($id, 6);
        }

        if (mgd_is_guid($id))
        {
            try
            {
                $this->_storage = new midgard_group($id);
            }
            catch (Exception $e)
            {
                debug_add("Failed to retrieve the group GUID {$id}: " . midcom_connection::get_error_string(), MIDCOM_LOG_INFO);
                return false;
            }
            if (!$this->_storage->guid)
            {
                debug_add("Failed to retrieve the group GUID {$id}: " . midcom_connection::get_error_string(), MIDCOM_LOG_INFO);
                return false;
            }
        }
        else if (is_numeric($id))
        {
            if ($id == 0)
            {
                return false;
            }

            try
            {
                $this->_storage = new midgard_group($id);
            }
            catch (Exception $e)
            {
                debug_add("Failed to retrieve the group ID {$id}: " . midcom_connection::get_error_string(), MIDCOM_LOG_INFO);
                return false;
            }
            if (!$this->_storage->guid)
            {
                debug_add("Failed to retrieve the group ID {$id}: " . midcom_connection::get_error_string(), MIDCOM_LOG_INFO);
                return false;
            }
        }
        else if (   is_object($id)
                 && (   is_a($id, 'midcom_db_group')
                     || is_a($id, 'midgard_group')))
        {
            $this->_storage = $id;
        }
        else
        {
            debug_add('Tried to load a midcom_core_group, but $id was of unknown type.', MIDCOM_LOG_ERROR);
            debug_print_r('Passed argument was:', $id);
            return false;
        }

        if ($this->_storage->official != '')
        {
            $this->name = $this->_storage->official;
        }
        else if ($this->_storage->name != '')
        {
            $this->name = $this->_storage->name;
        }
        else
        {
            $this->name = "Group #{$this->_storage->id}";
        }
        $this->id = "group:{$this->_storage->guid}";

        // Determine scope
        $parent = $this->get_parent_group();
        if (is_null($parent))
        {
            $this->scope = MIDCOM_PRIVILEGE_SCOPE_ROOTGROUP;
        }
        else
        {
            $this->scope = $parent->scope + 1;
        }
    }

    /**
     * Retrieves a list of groups owned by this group.
     *
     * @return Array A list of midcom_core_group objects in which are owned by the current group, false on failure.
     */
    function list_subordinate_groups()
    {
        $qb = new midgard_query_builder('midgard_group');
        $qb->add_constraint('owner', '=', $this->_storage->id);
        $result = $qb->execute();
        return $result;
    }

    /**
     * Retrieves a list of users for which are a member in this group.
     *
     * @return Array A list of midcom_core_user objects in which are members of the current group, false on failure, indexed by their ID.
     */
    function list_members()
    {
        if (   !is_object($this->_storage)
            || empty($this->_storage->id))
        {
            debug_add('$this->storage is not object or id is empty', MIDCOM_LOG_ERROR);
            return array();
        }

        $qb = new midgard_query_builder('midgard_member');
        $qb->add_constraint('gid', '=', $this->_storage->id);
        $result = @$qb->execute();
        if (! $result)
        {
            $result = Array();
        }

        $return = Array();
        foreach ($result as $member)
        {
            $user = new midcom_core_user($member->uid);
            if (! $user)
            {
                debug_add("The membership record {$member->id} is invalid, the user {$member->uid} is unknown, skipping it.", MIDCOM_LOG_ERROR);
                debug_add('Last Midgard error was: ' . midcom_connection::get_error_string());
                debug_print_r('Membership record was:', $member);
                continue;
            }
            $return[$user->id] = $user;
        }

        return $return;
    }

    /**
     * This method returns a list of all groups in which the
     * MidCOM user passed is a member.
     *
     * This function is always called statically.
     *
     * @param midcom_core_user $user The user that should be looked-up.
     * @return Array An array of member groups or false on failure, indexed by their ID.
     * @static
     */
    function list_memberships($user)
    {
        $mc = new midgard_collector('midgard_member', 'uid', $user->_storage->id);
        $mc->add_constraint('gid', '<>', 0);
        $mc->set_key_property('gid');
        @$mc->execute();
        $result = $mc->list_keys();
        if (empty($result))
        {
            return $result;
        }

        $return = Array();
        foreach ($result as $gid => $empty)
        {
            try
            {
                $group = new midcom_core_group($gid);
            }
            catch (Exception $e)
            {
                debug_add("The group {$gid} is unknown, skipping the membership record.", MIDCOM_LOG_ERROR);
                debug_add('Last Midgard error was: ' . midcom_connection::get_error_string());
                continue;
            }
            if (   !$group
                || !$group->id)
            {
                debug_add("The membership record is invalid, the group {$gid} is unknown, skipping it.", MIDCOM_LOG_ERROR);
                debug_add('Last Midgard error was: ' . midcom_connection::get_error_string());
                continue;
            }
            $return[$group->id] = $group;
        }

        return $return;
    }

    /**
     * Returns the parent group.
     *
     * You must adhere the reference that is returned, otherwise the internal caching
     * and runtime state strategy will fail.
     *
     * @return midcom_core_group The parent group of the current group or NULL if there is none.
     */
    function get_parent_group()
    {
        if (is_null($this->_cached_parent_group))
        {
            if ($this->_storage->owner == 0)
            {
                return null;
            }

            if ($this->_storage->id == $this->_storage->owner)
            {
                debug_add('WARNING: A group was its own parent, this is critical as it will result in an infinite loop. See debug log for more info.',
                    MIDCOM_LOG_CRIT);
                debug_print_r('Current group', $this);
                return null;
            }

            $parent = new midgard_group();
            $parent->get_by_id($this->_storage->owner);

            if (! $parent->id)
            {
                debug_add("Could not load Group ID {$this->_storage->owner} from the database, aborting, this should not happen. See the debug level log for details. ("
                    . midcom_connection::get_error_string() . ')',
                    MIDCOM_LOG_ERROR);
                debug_print_r('Group that we started from is:', $this->_storage);
                return null;
            }

            $this->_cached_parent_group = $_MIDCOM->auth->get_group($parent);
        }
        return $this->_cached_parent_group;
    }

    /**
     * Return a list of privileges assigned directly to the group. The default implementation
     * queries the storage object directly using the get_privileges method of the
     * midcom_core_baseclasses_core_dbobject class, which should work fine on all MgdSchema
     * objects. If the storage object is null, an empty array is returned.
     *
     * @return Array A list of midcom_core_privilege objects.
     */
    function get_privileges()
    {
        if (is_null($this->_storage))
        {
            return Array();
        }
        return midcom_core_privilege::get_self_privileges($this->_storage->guid);
    }

    /**
     * This function will return a MidCOM DBA level storage object for the current group. Be aware,
     * that depending on ACL information, the retrieval of the user may fail.
     *
     * Also, as outlined in the member $_storage, not all groups may have a DBA object associated
     * with them, therefore this call may return null.
     *
     * The default implementation will return an instance of midcom_db_group based
     * on the member $this->_storage->id if that object is defined, or null otherwise.
     *
     * @return MidgardObject Any MidCOM DBA level object that holds the information associated with
     *     this group, or null if there is no storage object.
     */
    function get_storage()
    {
        if ($this->_storage === null)
        {
            return null;
        }
        return new midcom_db_group($this->_storage);
    }
}
?>