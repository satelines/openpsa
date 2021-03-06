<?php
/**
 * @package midgard.admin.asgard
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use midcom\datamanager\schemadb;
use midcom\datamanager\datamanager;
use Symfony\Component\HttpFoundation\Request;

/**
 * Component configuration handler
 *
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_handler_component_configuration extends midcom_baseclasses_components_handler
{
    use midgard_admin_asgard_handler;

    private $_controller;

    public function _on_initialize()
    {
        $this->add_stylesheet(MIDCOM_STATIC_URL . '/midgard.admin.asgard/libconfig.css');
    }

    private function _prepare_toolbar($handler_id)
    {
        $view_url = $this->router->generate('components_configuration', ['component' => $this->_request_data['name']]);
        $edit_url = $this->router->generate('components_configuration_edit', ['component' => $this->_request_data['name']]);
        $buttons = [
            [
                MIDCOM_TOOLBAR_URL => $view_url,
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('view'),
                MIDCOM_TOOLBAR_GLYPHICON => 'eye',
            ],
            [
                MIDCOM_TOOLBAR_URL => $edit_url,
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('edit'),
                MIDCOM_TOOLBAR_GLYPHICON => 'pencil',
            ]
        ];
        $this->_request_data['asgard_toolbar']->add_items($buttons);

        switch ($handler_id) {
            case 'components_configuration_edit':
                $this->_request_data['asgard_toolbar']->disable_item($edit_url);
                break;
            case 'components_configuration':
                $this->_request_data['asgard_toolbar']->disable_item($view_url);
                break;
        }
    }

    /**
     * Set the breadcrumb data
     */
    private function _prepare_breadcrumbs($handler_id)
    {
        $this->add_breadcrumb($this->router->generate('welcome'), $this->_l10n->get($this->_component));
        $this->add_breadcrumb($this->router->generate('components'), $this->_l10n->get('components'));

        $this->add_breadcrumb(
            $this->router->generate('components_component', ['component' => $this->_request_data['name']]),
            midcom::get()->i18n->get_string($this->_request_data['name'], $this->_request_data['name'])
        );
        $this->add_breadcrumb(
            $this->router->generate('components_configuration', ['component' => $this->_request_data['name']]),
            $this->_l10n_midcom->get('component configuration')
        );

        if ($handler_id == 'components_configuration_edit') {
            $this->add_breadcrumb(
                $this->router->generate('components_configuration_edit', ['component' => $this->_request_data['name']]),
                $this->_l10n_midcom->get('edit')
            );
        }
    }

    private function _load_configs($component, $object = null)
    {
        $config = midcom_baseclasses_components_configuration::get($component, 'config');

        if ($object) {
            $topic_config = new midcom_helper_configuration($object, $component);
            $config->store($topic_config->_local, false);
        }

        return $config;
    }

    /**
     * @return \midcom\datamanager\controller
     */
    private function load_controller()
    {
        // Load SchemaDb
        $schemadb_config_path = midcom::get()->componentloader->path_to_snippetpath($this->_request_data['name']) . '/config/config_schemadb.inc';
        $schemaname = 'default';

        if (file_exists($schemadb_config_path)) {
            $schemadb = schemadb::from_path('file:/' . str_replace('.', '/', $this->_request_data['name']) . '/config/config_schemadb.inc');
            if ($schemadb->has('config')) {
                $schemaname = 'config';
            }
            // TODO: Log error on deprecated config schema?
        } else {
            // Create dummy schema. Naughty component would not provide config schema.
            $schemadb = schemadb::from_path("file:/midgard/admin/asgard/config/schemadb_libconfig.inc");
        }
        $schema = $schemadb->get($schemaname);
        $schema->set('l10n_db', $this->_request_data['name']);
        $fields = $schema->get('fields');

        foreach ($this->_request_data['config']->_global as $key => $value) {
            // try to sniff what fields are missing in schema
            if (!array_key_exists($key, $fields)) {
                $fields[$key] = $this->_detect_schema($key, $value);
            }

            if (   !isset($this->_request_data['config']->_local[$key])
                || $this->_request_data['config']->_local[$key] == $this->_request_data['config']->_global[$key]) {
                // No local configuration setting, note to user that this is the global value
                $fields[$key]['title'] = $schema->get_l10n()->get($fields[$key]['title']);
                $fields[$key]['title'] .= " <span class=\"global\">(" . $this->_l10n->get('global value') .")</span>";
            }
        }

        // Prepare defaults
        $config = array_intersect_key($this->_request_data['config']->get_all(), $fields);
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $fields[$key]['default'] = var_export($value, true);
            } else {
                if ($fields[$key]['widget'] == 'checkbox') {
                    $value = (boolean) $value;
                }
                $fields[$key]['default'] = $value;
            }
        }
        $schema->set('fields', $fields);

        $dm = new datamanager($schemadb);
        return $dm->get_controller();
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param string $component The component name
     * @param array $data The local request data.
     */
    public function _handler_view($handler_id, $component, array &$data)
    {
        $data['name'] = $component;
        if (!midcom::get()->componentloader->is_installed($data['name'])) {
            throw new midcom_error_notfound("Component {$data['name']} was not found.");
        }

        $data['config'] = $this->_load_configs($data['name']);

        $data['view_title'] = sprintf($this->_l10n->get('configuration for %s'), $data['name']);
        $this->_prepare_toolbar($handler_id);
        $this->_prepare_breadcrumbs($handler_id);
        return $this->get_response();
    }

    /**
     * @param string $handler_id Name of the used handler
     * @param array $data Data passed to the show method
     */
    public function _show_view($handler_id, array &$data)
    {
        midcom_show_style('midgard_admin_asgard_component_configuration_header');

        foreach ($data['config']->_global as $key => $value) {
            $data['key'] = $this->_i18n->get_string($key, $data['name']);
            $data['global'] = $this->_detect($value);

            if (isset($data['config']->_local[$key])) {
                $data['local'] = $this->_detect($data['config']->_local[$key]);
            } else {
                $data['local'] = $this->_detect(null);
            }

            midcom_show_style('midgard_admin_asgard_component_configuration_item');
        }
        midcom_show_style('midgard_admin_asgard_component_configuration_footer');
    }

    private function _detect($value)
    {
        $type = gettype($value);

        switch ($type) {
            case 'boolean':
                $result = '<i class="fa fa-' . ($value === true ? 'check' : 'times') . '"></i>';
                break;
            case 'array':
                $content = '<ul>';
                foreach ($value as $key => $val) {
                    $content .= "<li>{$key} => " . $this->_detect($val) . ",</li>\n";
                }
                $content .= '</ul>';
                $result = "<ul>\n<li>array</li>\n<li>(\n{$content}\n)</li>\n</ul>\n";
                break;
            case 'object':
                $result = '<strong>Object</strong>';
                break;
            case 'NULL':
                $result = '<strong>N/A</strong>';
                break;
            default:
                $result = $value;
        }

        return $result;
    }

    /**
     * Ensure the configuration is valid
     *
     * @throws midcom_error
     */
    private function _check_config($config)
    {
        $tmpfile = tempnam(midcom::get()->config->get('midcom_tempdir'), 'midgard_admin_asgard_handler_component_configuration_');
        file_put_contents($tmpfile, "<?php\n\$data = array({$config}\n);\n?>");

        exec("php -l {$tmpfile} 2>&1", $parse_results, $retval);
        debug_print_r("'php -l {$tmpfile}' returned:", $parse_results);
        unlink($tmpfile);

        if ($retval !== 0) {
            $parse_results = array_shift($parse_results);

            if (strstr($parse_results, 'Parse error')) {
                $line = preg_replace('/^.+?on line (\d+?)$/', '\1', $parse_results);
                return sprintf($this->_i18n->get_string('type php: parse error in line %s', 'midcom.datamanager'), $line);
            }
        }
    }

    /**
     * Save configuration values to a topic as "serialized" array
     *
     * @return boolean
     */
    private function _save_snippet($config)
    {
        $basedir = midcom::get()->config->get('midcom_sgconfig_basedir');
        $sg_snippetdir = new midcom_db_snippetdir();
        if (!$sg_snippetdir->get_by_path($basedir)) {
            // Create config snippetdir
            $sg_snippetdir = new midcom_db_snippetdir();
            $sg_snippetdir->name = $basedir;
            // remove leading slash from name
            $sg_snippetdir->name = preg_replace("/^\//", "", $sg_snippetdir->name);
            if (!$sg_snippetdir->create()) {
                throw new midcom_error("Failed to create snippetdir {$basedir}: " . midcom_connection::get_error_string());
            }
        }

        $lib_snippetdir = new midcom_db_snippetdir();
        if (!$lib_snippetdir->get_by_path("{$basedir}/{$this->_request_data['name']}")) {
            $lib_snippetdir = new midcom_db_snippetdir();
            $lib_snippetdir->up = $sg_snippetdir->id;
            $lib_snippetdir->name = $this->_request_data['name'];
            if (!$lib_snippetdir->create()) {
                throw new midcom_error("Failed to create snippetdir {$basedir}/{$lib_snippetdir->name}: " . midcom_connection::get_error_string());
            }
        }

        $snippet = new midcom_db_snippet();
        if (!$snippet->get_by_path("{$basedir}/{$this->_request_data['name']}/config")) {
            $sn = new midcom_db_snippet();
            $sn->snippetdir = $lib_snippetdir->id;
            $sn->name = 'config';
            $sn->code = $config;
            return $sn->create();
        }

        $snippet->code = $config;
        return $snippet->update();
    }

    /**
     * Save configuration values to a topic as parameters
     */
    private function _save_topic(midcom_db_topic $topic, $config)
    {
        foreach ($this->_request_data['config']->_global as $global_key => $global_value) {
            if (   isset($config[$global_key])
                && $config[$global_key] != $global_value) {
                continue;
                // Skip the ones we will set next
            }

            // Clear unset params
            if ($topic->get_parameter($this->_request_data['name'], $global_key)) {
                $topic->delete_parameter($this->_request_data['name'], $global_key);
            }
        }

        foreach ($config as $key => $value) {
            if (   is_array($value)
                || is_object($value)) {
                /**
                 * See http://trac.midgard-project.org/ticket/1442
                $topic->set_parameter($this->_request_data['name'], var_export($value, true));
                 */
                 continue;
            }

            if ($value === false) {
                $value = '0';
            }
            $topic->set_parameter($this->_request_data['name'], $key, $value);
        }
    }

    private function _get_config_from_controller()
    {
        $post = $this->_controller->get_datamanager()->get_content_raw();
        $config_array = [];

        foreach ($this->_request_data['config']->get_all() as $key => $val) {
            if (isset($post[$key])) {
                $newval = $post[$key];
            } else {
                continue;
            }

            if ($newval === '') {
                continue;
            }

            if (is_array($val)) {
                //try make sure entries have the same format before deciding if there was a change
                eval("\$newval = $newval;");
            }

            if ($newval != $val) {
                $config_array[$key] = $newval;
            }
        }

        return $config_array;
    }

    /**
     * @param Request $request The request object
     * @param mixed $handler_id The ID of the handler.
     * @param array $data The local request data.
     * @param string $component The component name
     * @param string $folder The topic GUID
     */
    public function _handler_edit(Request $request, $handler_id, array &$data, $component, $folder = null)
    {
        $data['name'] = $component;
        if (!midcom::get()->componentloader->is_installed($data['name'])) {
            throw new midcom_error_notfound("Component {$data['name']} was not found.");
        }

        if ($handler_id == 'components_configuration_edit_folder') {
            $data['folder'] = new midcom_db_topic($folder);
            if ($data['folder']->component != $data['name']) {
                throw new midcom_error_notfound("Folder {$folder} not found for configuration.");
            }

            $data['folder']->require_do('midgard:update');

            $data['config'] = $this->_load_configs($data['name'], $data['folder']);
        } else {
            $data['config'] = $this->_load_configs($data['name']);
        }

        $this->_controller = $this->load_controller();

        switch ($this->_controller->handle($request)) {
            case 'save':
                $this->_save_configuration($data);
                // *** FALL-THROUGH ***

            case 'cancel':
                if ($handler_id == 'components_configuration_edit_folder') {
                    return new midcom_response_relocate($this->router->generate('object_view', ['guid' => $data['folder']->guid]));
                }
                return new midcom_response_relocate($this->router->generate('components_configuration', ['component' => $data['name']]));
        }

        $data['controller'] = $this->_controller;

        if ($handler_id == 'components_configuration_edit_folder') {
            midgard_admin_asgard_plugin::bind_to_object($data['folder'], $handler_id, $data);
            $data['view_title'] = sprintf($this->_l10n->get('edit configuration for %s folder %s'), $data['name'], $data['folder']->extra);
        } else {
            $this->_prepare_toolbar($handler_id);
            $data['view_title'] = sprintf($this->_l10n->get('edit configuration for %s'), $data['name']);
            $this->_prepare_breadcrumbs($handler_id);
        }

        return $this->get_response();
    }

    private function _save_configuration(array $data)
    {
        $config_array = $this->_get_config_from_controller();

        $config = $this->_draw_array($config_array);

        if ($error = $this->_check_config($config)) {
            midcom::get()->uimessages->add(
                $this->_l10n_midcom->get('component configuration'),
                sprintf($this->_l10n->get('configuration save failed: %s'), $error),
                'error'
            );
            return;
            // Get back to form
        }

        if ($data['handler_id'] == 'components_configuration_edit_folder') {
            // Editing folder configuration
            $this->_save_topic($data['folder'], $config_array);
            midcom::get()->uimessages->add(
                $this->_l10n_midcom->get('component configuration'),
                $this->_l10n->get('configuration saved successfully')
            );
            $url = $this->router->generate('components_configuration_edit_folder', [
                'component' => $data['name'],
                'folder' => $data['folder']->guid
            ]);

            midcom::get()->relocate($url);
            // This will exit
        }

        if ($this->_save_snippet($config)) {
            midcom::get()->uimessages->add(
                $this->_l10n_midcom->get('component configuration'),
                $this->_l10n->get('configuration saved successfully')
            );
        } else {
            midcom::get()->uimessages->add(
                $this->_l10n_midcom->get('component configuration'),
                sprintf($this->_l10n->get('configuration save failed: %s'), midcom_connection::get_error_string()),
                'error'
            );
        }
    }

    /**
     * @param string $handler_id Name of the used handler
     * @param array $data Data passed to the show method
     */
    public function _show_edit($handler_id, array &$data)
    {
        midcom_show_style('midgard_admin_asgard_component_configuration_edit');
    }

    private function _detect_schema($key, $value)
    {
        $result = [
            'title'       => $key,
            'type'        => 'text',
            'widget'      => 'text',
        ];

        $type = gettype($value);
        switch ($type) {
            case "boolean":
                $result['type'] = 'boolean';
                $result['widget'] = 'checkbox';
                break;
            case "array":
                $result['widget'] = 'textarea';

                if (isset($this->_request_data['folder'])) {
                    // Complex Array fields should be readonly for topics as we cannot store and read them properly with parameters
                    $result['readonly'] = true;
                }

                break;
            default:
                if (preg_match("/\n/", $value)) {
                    $result['widget'] = 'textarea';
                }
        }

        return $result;
    }

    private function _draw_array($array)
    {
        $data = var_export($array, true);
        // Remove opening and closing array( ) lines, because that's the way midcom likes it
        $data = preg_replace('/^.*?\n/', '', $data);
        return preg_replace('/(\n.*?|\))$/', '', $data);
    }
}
