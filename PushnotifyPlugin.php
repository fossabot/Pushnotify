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
 * Push notify plugin
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Kromnos <statusnet@kromonos.net>
 * @copyright 2011 Kromonos
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://kromonos.net/
 */
class PushnotifyPlugin extends Plugin {

    public $service = 'pushalot';
    public $apikey = null;
    public $logo = 'http://bka.li/gnu-social-logo.png';

    /**
     * Set up email_reminder table
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema() {
        $schema = Schema::get();
        $schema->ensureTable('user_push_settings', User_pushnotify_prefs::schemaDef());

        return true;
    }

    /**
     * Add Pushnotify-related paths to the router table
     *
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m URL mapper
     *
     * @return boolean hook return
     */
    function onStartInitializeRouter($m) {
        $m->connect('settings/pushnotify', array('action' => 'pushnotifysettings'));

        return true;
    }

    /**
     * Show the CSS necessary for this plugin
     *
     * @param Action $action the action being run
     *
     * @return boolean hook value
     */
    function onEndShowStyles($action) {
        $action->cssLink($this->path('css/pushnotify.css'));
        return true;
    }

    /**
     * Menu item for push notify settings
     *
     * @param Action $action Action being executed
     *
     * @return boolean hook return
     */
    function onEndAccountSettingsNav($action)
    {
        $action_name = $action->trimmed('action');

        $action->menuItem(common_local_url('pushnotifysettings'),
                          _m('MENU', 'Push notifications'),
                          _m('Configure push notifications.'),
                          $action_name === 'pushnotifysettings');

        return true;
    }


    /**
     * Check if a given notice object should be handled by this micro-app
     * plugin.
     *
     * The default implementation checks against the activity type list
     * returned by $this->types(). You can override this method to expand
     * your checks, but follow the execution chain to get it right.
     *
     * @param Notice $notice
     * @return boolean
     */
    function isMyNotice(Notice $notice) {
        return $this->isMyVerb($notice->verb) && $this->isMyType($notice->object_type);
    }

    function isMyVerb($verb) {
        $verb = $verb ?: ActivityVerb::POST;    // post is the default verb
        return ActivityUtils::compareTypes($verb, $this->verbs());
    }

    function isMyType($type) {
        return count($this->types())===0 || ActivityUtils::compareTypes($type, $this->types());
    }

    /**
     * @param Notice $stored            The notice being distributed
     * @param array  &$mentioned_ids    List of profiles (from $stored->getReplies())
     */
    public function onStartNotifyMentioned(Notice $stored, array &$mentioned_ids) {
        $verb = explode('/', $stored->verb);
        common_debug(LOG_DEBUG, '###########'.end($verb).'############');
        foreach ($mentioned_ids as $id) {
            $mentioned = User::getKV('id', $id);
            common_debug(LOG_DEBUG, 'Push notify for ID ' . $id);
            if ($mentioned instanceof User && end($verb) == 'post') {
                $this->pushnotifyattn($mentioned, $stored);
            }

        }
    }

    function pushnotifyattn($user, $notice) {
        $sender = $notice->getProfile();
        $curPrefs = User_pushnotify_prefs::getKV('user_id', $user->id);

        common_debug(LOG_DEBUG, 'started pushnotify for attn');

        if ($sender->id == $user->id) {
            return;
        }

        if ( $curPrefs->enabled == 0 ) {
            common_debug(LOG_DEBUG, 'user disabled push notify. Returning...');
            return;
        }


        if ($user->hasBlocked($sender)) {
            // If the author has blocked us, don't spam them with a notification.
            return;
        }

        $bestname = $sender->getBestName();

        common_switch_locale($user->language);

        if ($notice->hasConversation()) {
            $conversationUrl = common_local_url('conversation', array('id' => $notice->conversation)).'#notice-'.$notice->id;
            $conversationEmailText = sprintf(_("The full conversation can be read here:\n"."\t%s"), $conversationUrl) . "\n\n";
        } else {
            $conversationEmailText = '';
        }

        $subject = sprintf(_('%1$s sent a notice to your attention'), $bestname);
        $link = common_local_url('shownotice', array('notice' => $notice->id));

        $body = sprintf(_("%1\$s just sent a notice to your attention (an '@-reply') on %2\$s.\n\n".
                      "The notice is here:\n".
                      "\t%3\$s\n\n" .
                      "It reads:\n".
                      "\t%4\$s\n\n" .
                      "%5\$s" .
                      "You can reply back here:\n".
                      "\t%6\$s\n\n" .
                      "The list of all @-replies for you here:\n" .
                      "%7\$s"),
                    $sender->getFancyName(),//%1
                    common_config('site', 'name'),//%2
                    $link,//%3
                    $notice->content,//%4
                    $conversationEmailText,//%5
                    common_local_url('newnotice', array('replyto' => $sender->nickname, 'inreplyto' => $notice->id)),//%6
                    common_local_url('replies', array('nickname' => $user->nickname))
                );

        common_switch_locale();
        $this->sendPush($user, $bestname, $subject, $body, $link);

    }

    protected function sendPush($rcpt, $sender, $subject, $body, $link) {
        $dir = dirname(__FILE__);
        $libdir = $dir . '/lib';
        $title = _m('Notice from %s', $sender);
        $curPrefs = User_pushnotify_prefs::getKV('user_id', $rcpt->id);
        $service = strtolower($curPrefs->service);

        common_debug(LOG_DEBUG, 'Push notification using "'.$service.'" for user "'.$rcpt->nickname.'"');

        switch ($service) {
            case 'pushalot':
                require_once $libdir . '/pushalot_api.php';
                // Because Pushalot does not speak UTF-8, do conversions
                common_debug(LOG_DEBUG, 'Converting body string from ' . mb_detect_encoding($body, mb_detect_order(), true) . ' to Windows-1252.');
                $_body = iconv(mb_detect_encoding($body, mb_detect_order(), true) . '//TRANSLIT', "Windows-1252", $body);
                common_debug(LOG_DEBUG, 'Converting subject string from ' . mb_detect_encoding($subject, mb_detect_order(), true) . ' to Windows-1252.');
                $_subject = iconv(mb_detect_encoding($subject, mb_detect_order(), true) . '//TRANSLIT', "Windows-1252", $subject);
                common_debug(LOG_DEBUG, 'Converting title string from ' . mb_detect_encoding($title, mb_detect_order(), true) . ' to Windows-1252.');
                $_title = iconv(mb_detect_encoding($title, mb_detect_order(), true) . '//TRANSLIT', "Windows-1252", $title);

                common_debug(LOG_DEBUG, 'Title: ' . print_r($_title));

                $title = ( $_title != false ? $_title : $title );
                $subject = ( $_subject != false ? $_subject : $subject );
                $body = ( $_body == false ? $_body : $body );

                $pushalot = new Pushalot($curPrefs->apikey);
                //$pushalot->setProxy('http://localhost:12345','user:pass');
                $success = $pushalot->sendMessage(array(
                    'Title' => $subject,
                    'Body' => $body,
                    'LinkTitle' => $title,
                    'Link' => $link,
                    'IsImportant' => false,
                    'IsSilent' => false,
                    'Image' => $this->logo,
                    'Source' => common_config('site', 'name')
                ));
                if( $success == true ) {
                    common_debug(LOG_DEBUG, 'Pushalot notifcation sent.');
                }
                else {
                    common_debug(LOG_ERR, $pushalot->getError());
                }
                break;

            case 'nma':
                require_once $libdir . '/nmaApi.class.php';

                $nma = new nmaApi(array('apikey' => $curPrefs->apikey));

                if($nma->verify()) {
                    if($nma->notify($title, $subject, $body)) {
                        common_debug(LOG_DEBUG, 'NMA notifcation sent.');
                    }
                    else {
                        common_debug(LOG_ERR, 'NMA notification not sent.');
                    }
                }
                break;

            case 'pushover':
                require_once $libdir . '/pushover.class.php';
                $push = new Pushover();
                $push->setToken($curPrefs->apikey);
                $push->setUser($curPrefs->apiuser);
                $push->setTitle($title);
                $push->setMessage($body);
                if( strlen($curPrefs->devicename) > 0 ) {
                    $push->setDevice($curPrefs->devicename);
                }
                $push->setPriority(0);
                $push->setTimestamp(time());
                $go = $push->send();
                break;

            default:
                break;
        }

    }

    /**
     *
     * @param type $versions
     * @return type
     */
    function onPluginVersion(&$versions) {
        $versions[] = array(
            'name'           => 'Pushnotify',
            'version'        => '0.1',
            'author'         => 'Kromonos',
            'homepage'       => 'http://kromonos.net/',
            'rawdescription' => _m('Send push notifications via Pushalot or NotifyMyAndroid')
        );
        return true;
    }

}
