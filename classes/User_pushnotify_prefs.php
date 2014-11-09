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
 * Store preferences for OpenID use in StatusNet
 *
 * @category  Settings
 * @package   StatusNet
 * @author    Kromnos <statusnet@kromonos.net>
 * @copyright 2014 Kromonos
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://kromonos.net/
 *
 * @see      DB_DataObject
 */

class User_pushnotify_prefs extends Managed_DataObject
{
    public $__table = 'user_push_settings'; // table name

    public $user_id;            // The User with the prefs
    public $enabled;            // Hide the link on the profile block?
    public $apikey;
    public $apiuser;
    public $service;
    public $notify_fav;
    public $notify_attn;
    public $created;            // datetime
    public $modified;           // datetime

    /**
     * The One True Thingy that must be defined and declared.
     */

    public static function schemaDef()
    {
        return array(
                     'description' => 'Per-user preferences for push notify',
                     'fields' => array('user_id'      => array('type' => 'integer',
                                                            'not null' => true,
                                                            'description' => 'User whose prefs we are saving'),
                                       'enabled'      => array('type' => 'int',
                                                            'not null' => true,
                                                            'default' => 0,
                                                            'description' => 'Push enabled or disabled'),
                                       'apikey'       => array('type' => 'varchar', 
                                                            'length' => 255, 
                                                            'not null' => true, 
                                                            'default' => '',
                                                            'description' => 'api key for service'),
                                       'apiuser'      => array('type' => 'varchar', 
                                                            'length' => 255, 
                                                            'not null' => true, 
                                                            'default' => '',
                                                            'description' => 'Some services needs an extra api user'),
                                       'devicename'  => array('type' => 'varchar', 
                                                            'length' => 255, 
                                                            'not null' => true, 
                                                            'default' => '',
                                                            'description' => 'Some services needs an extra api devicename to send pushes to'),
                                       'service'      => array('type' => 'varchar', 
                                                            'length' => 255, 
                                                            'not null' => true, 
                                                            'default' => 'pushalot',
                                                            'description' => 'Service i.e. pushalot, nma, pushover'),
                                       'created'      => array('type' => 'datetime',
                                                            'not null' => true,
                                                            'description' => 'date this record was created'),
                                       'modified'     => array('type' => 'datetime',
                                                            'not null' => true,
                                                            'description' => 'date this record was modified'),
                                       ),
                     'primary key'  => array('user_id'),
                     'foreign keys' => array('user_pushnotify_prefs_user_id_fkey' => array('user', array('user_id' => 'id')),
                                             ),
                     'indexes'      => array(),
                     );
    }
}
