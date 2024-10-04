<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Configure provider instances.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$id = optional_param('id', null, PARAM_INT);
$provider = optional_param('proivder', null, PARAM_PLUGIN);
$returnurl = optional_param('returnurl', null, PARAM_LOCALURL);
$title = get_string('createnewprovider', 'core_ai');

$data = [];
$urlparams = [];

if ($id) {
    $urlparams['id'] = $id;
}

if ($provider) {
    $urlparams['provider'] = $provider;
}

if (!empty($provider)) {
    $configs = new stdClass();
    $configs->aiprovider = $provider;
    $data = [
            'gatewayconfigs' => $configs,
    ];
}

if (!empty($id)) {
    $manager = \core\di::get(\core_ai\manager::class);
    $providerrecord = $manager->get_provider_records(['id' => $id]);
    $providerrecord = reset($providerrecord);
    $plugin = explode('\\', $providerrecord->provider);
    $plugin = $plugin[0];
    $configs = json_decode($providerrecord->config, true, 512, JSON_THROW_ON_ERROR);
    $configs = (object) $configs;
    $configs->aiprovider = $plugin;
    $configs->id = $providerrecord->id;
    $configs->name = $providerrecord->name;
    $data = [
            'gatewayconfigs' => $configs,
    ];

    $a = ['provider' => $providerrecord->name];
    $title = get_string('configureprovider', 'core_ai');
}

$PAGE->set_context($context);
$PAGE->set_url('/ai/configure.php', $urlparams);
$PAGE->set_title($title);
$PAGE->set_heading($title);

if (empty($returnurl)) {
    $returnurl = new moodle_url(
        url: '/admin/settings.php',
        params: ['section' => 'aiprovider']
    );
} else {
    $returnurl = new moodle_url($returnurl);
}
$data['returnurl'] = $returnurl;

$mform = new \core_ai\form\ai_provider_form(customdata: $data);

if ($mform->is_cancelled()) {
    $data = $mform->get_data();
    if (isset($data->returnurl)) {
        redirect($data->returnurl);
    } else {
        redirect($returnurl);
    }
}

if ($data = $mform->get_data()) {
    $manager = \core\di::get(\core_ai\manager::class);
    $aiprovider = $data->aiprovider;
    $providername = $data->name;
    unset($data->aiprovider, $data->name, $data->id);
    if (!empty($id)) {
        $gatewayinstance = $manager->get_provider_instances(['id' => $id]);
        $gatewayinstance = reset($gatewayinstance);
        $gatewayinstance->name = $providername;

        $manager->update_provider_instance($gatewayinstance, $data);
    } else {
        $classname = $aiprovider . '\\' . 'provider';
        $manager->create_provider_instance(
                classname: $classname,
                name: $providername,
                config: $data,
        );
    }
    redirect($returnurl);
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
