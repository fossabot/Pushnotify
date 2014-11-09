<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Plugin for sending push notifications
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  OnDemand
 * @package   StatusNet
 * @author    Kromonos <statusnet@kromonos.net>
 * @copyright 2014 Kromonos
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://kromonos.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Settings for push notify plugin
 *
 * @category  Settings
 * @package   StatusNet
 * @author    Kromnos <statusnet@kromonos.net>
 * @copyright 2014 Kromonos
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://kromonos.net/
 */

class PushnotifysettingsAction extends SettingsAction {
    /**
     * Title of the page
     *
     * @return string Page title
     */
    function title()
    {
        return _m('TITLE','Push notification settings');
    }

    /**
     * Instructions for use
     *
     * @return string Instructions for use
     */
    function getInstructions()
    {
        return _m('Configure, which service to use for push notifications');
    }

    /**
     * Show the form for OpenID management
     *
     * We have one form with a few different submit buttons to do different things.
     *
     * @return void
     */
    function showContent()
    {
        $user = common_current_user();
        $prefs = User_pushnotify_prefs::getKV('user_id', $user->id);
        $nma_array = array('type' => 'radio', 'name' => 'service', 'value' => 'nma');
        $pa_array = array('type' => 'radio', 'name' => 'service', 'value' => 'pushalot');
        $po_array = array('type' => 'radio', 'name' => 'service', 'value' => 'pushover');

        switch ($prefs->service) {
            case 'nma':
                $nma_array['checked'] = 'checked';
                break;
            case 'pushalot':
                $pa_array['checked'] = 'checked';
                break;
            case 'pushover':
                $po_array['checked'] = 'checked';
                break;

            default:
                break;
        }

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_pushnotify',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('pushnotifysettings')
                                         )
                            );


            $this->elementStart('fieldset', array('class' => 'settings_push_preferences'));
                $this->elementStart('fieldset', array('class' => 'settings_push_preferences'));
                    $this->element('legend', null, _m('LEGEND','Enable/Disable'));
                    $this->elementStart('ul');
                        $this->elementStart('li');
                            $this->checkBox('enabled', "Enable/disable push notifications", !empty($prefs) && $prefs->enabled);
                        $this->elementEnd('li');
                    $this->elementEnd('ul');
                $this->elementEnd('fieldset');
                $this->elementStart('fieldset', array('class' => 'settings_push_preferences'));
                    $this->element('legend', null, _m('LEGEND','Service'));
                    $this->elementStart('ul', 'form_data');
                        $this->elementStart('li');
                            $this->element('input', $pa_array, _m('Pushalot'));
                        $this->elementEnd('li');
                        $this->elementStart('li');
                            $this->element('input', $nma_array, _m('NotifyMyAndroid'));
                        $this->elementEnd('li');
                        $this->elementStart('li');
                            $this->element('input', $po_array, _m('Pushover'));
                        $this->elementEnd('li');

                        
                        // First implementations for pushover. It needs a username and token
                        $this->elementStart('li');
                           $this->element('label', array('for' => 'push_apiuser'),
                                          _m('API User*'));
                           $this->element('input', array('name' => 'push_apiuser',
                                                         'type' => 'text',
                                                         'id' => 'push_apiuser',
                                                         'value' => $prefs->apiuser));
                        $this->elementEnd('li');
                        
                        
                        $this->elementStart('li');
                            $this->element('label', array('for' => 'push_apikey'),
                                           _m('API Key/Token'));
                            $this->element('input', array('name' => 'push_apikey',
                                                          'type' => 'text',
                                                          'id' => 'push_apikey',
                                                          'value' => $prefs->apikey));
                        $this->elementEnd('li');
                        $this->elementStart('li');
                            $this->element('label', array('for' => 'push_devicename'),
                                           _m('Devicename'));
                            $this->element('input', array('name' => 'push_devicename',
                                                          'type' => 'text',
                                                          'id' => 'push_devicename',
                                                          'value' => $prefs->devicename));
                        $this->elementEnd('li');
                        $this->element('p', 'form_guide',
                                     // TRANS: Form guide.
                                     _m('* If not needed, keep it empty.'));
                        $this->elementStart('li');
                            $this->element('input', array('type' => 'submit',
                                                          'id' => 'settings_pushnotify_prefs_save',
                                                          'name' => 'save_prefs',
                                                          'class' => 'submit',
                                                          'value' => _m('BUTTON','Save')));
                        $this->elementEnd('li');
                    $this->elementEnd('ul');
                $this->elementEnd('fieldset');
            $this->elementEnd('fieldset');
        $this->elementEnd('form');


    }

    /**
     * Handle a POST request
     *
     * Muxes to different sub-functions based on which button was pushed
     *
     * @return void
     */
    function handlePost()
    {
        if ($this->arg('save_prefs')) {
            $this->savePrefs();
        } else {
            $this->showForm(_m('Something weird happened.'));
        }
    }

    /**
     * Handles a request to save preferences
     *
     * Validates input and, if everything is OK, deletes the OpenID.
     * Reloads the form with a success or error notification.
     *
     * @return void
     */
    function savePrefs()
    {
        $cur = common_current_user();


        if (empty($cur)) {
            throw new ClientException(_("Not logged in."));
        }

        $orig  = null;
        $prefs = User_pushnotify_prefs::getKV('user_id', $cur->id);

        if (empty($prefs)) {
            $prefs          = new User_pushnotify_prefs();
            $prefs->user_id = $cur->id;
            $prefs->created = common_sql_now();
        } else {
            $orig = clone($prefs);
            $prefs->modified = common_sql_now();
        }

        $prefs->enabled = $this->boolean('enabled');
        $prefs->apikey  = $this->arg('push_apikey');
        $prefs->apiuser = $this->arg('push_apiuser');
        $prefs->service = $this->arg('service');

        if (empty($orig)) {
            $prefs->insert();
        } else {
            $prefs->update($orig);
        }

        $this->showForm(_m('Push preferences saved.'), true);
        return;
    }

}