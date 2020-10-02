<?php

/**
 * KaikMedia AuthModule
 *
 * @package    KaikmediaAuthModule
 * @author     Kaik <contact@kaikmedia.com>
 * @copyright  KaikMedia
 * @link       https://github.com/Kaik/KaikmediaAuthModule.git
 */

namespace Kaikmedia\AuthModule;

/**
 * Module-wide constants for the Auth Module module.
 *
 * NOTE: Do not define anything other than constants in this class!
 */
class Constant
{
    /**
     * The official internal module name.
     *
     * @var string
     */
    const MODNAME = 'KaikmediaAuthModule';
    
    const ADMIN_FORM_PREFERENCES = 'kaikmedia_auth_admin_form_preferences';
    const ADMIN_FORM_FACEBOOK = 'kaikmedia_auth_admin_form_facebook';

    const ALIAS_KMFACEBOOK = 'kaikmedia_auth_facebook'; // need this to distingusig auth methods
    const ALIAS_FACEBOOK = 'facebook';

}
