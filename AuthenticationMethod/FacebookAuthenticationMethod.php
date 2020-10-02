<?php

/**
 * KaikMedia AuthModule
 *
 * @package    KaikmediaAuthModule
 * @author     Kaik <contact@kaikmedia.com>
 * @copyright  KaikMedia
 * @link       https://github.com/Kaik/KaikmediaAuthModule.git
 */

namespace Kaikmedia\AuthModule\AuthenticationMethod;

use Kaikmedia\AuthModule\Constant;
use Zikula\ExtensionsModule\Api\VariableApi;
use Zikula\UsersModule\AuthenticationMethodInterface\ReEntrantAuthenticationMethodInterface;

class FacebookAuthenticationMethod implements ReEntrantAuthenticationMethodInterface
{
    /**
     * @var VariableApi
     */
    protected $variableApi;

    /**
     * AbstractAuthenticationMethod constructor.
     * @param VariableApi $variableApi
     */
    public function __construct(
        VariableApi $variableApi
    ){
        $this->variableApi = $variableApi;
    }

    public function getId()
    {
        return false;
    }

    public function register(array $data)
    {
        return false;
    }

    public function authenticate(array $data = [])
    {
        return false;
    }

    public function getAlias()
    {
        return Constant::ALIAS_KMFACEBOOK;
    }

    public function getDisplayName()
    {
        $settings = $this->variableApi->get(Constant::MODNAME, 'facebook');

        // https://developers.facebook.com/docs/facebook-login/web/login-button/
        //     data-size="large" 
        //     data-button-type="continue_with" 
        //     data-layout="default" 
        //     data-auto-logout-link="false" 
        //     data-use-continue-as="true" 

        //     data-width=""
        //     scope="public_profile,email" 
        //     onlogin="KaikMedia.Auth.facebook.register();"                                        

        $size = array_key_exists('button_size', $settings) ? $settings['button_size'] : 'large';
        $button_type = array_key_exists('button_type', $settings) ? $settings['button_type'] : 'continue_with';
        $button_layout = array_key_exists('button_layout', $settings) ? $settings['button_layout'] : 'default';
        $auto_logout_link = array_key_exists('auto_logout_link', $settings) && $settings['auto_logout_link'] ? 'yes' : 'no';
        $use_continue_as = array_key_exists('use_continue_as', $settings) && $settings['use_continue_as'] ? 'yes' : 'no';
        
        return 'kaikmedia_auth_facebook_button_' . $size .'-'. $button_type .'-'. $button_layout .'-'. $auto_logout_link .'-'. $use_continue_as;
    }

    public function getDescription()
    {
        return 'Login using Facebook via OAuth.';
    }

    public function getUname()
    {
        return false;
    }

    public function getEmail()
    {
        return false;
    }
}
