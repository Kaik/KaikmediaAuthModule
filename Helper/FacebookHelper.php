<?php

/**
 * KaikMedia AuthModule
 *
 * @package    KaikmediaAuthModule
 * @author     Kaik <contact@kaikmedia.com>
 * @copyright  KaikMedia
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @link       https://github.com/Kaik/KaikmediaAuthModule.git
 */

namespace Kaikmedia\AuthModule\Helper;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\AppSecretProof;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Provider\FacebookUser;
use League\OAuth2\Client\Provider\Exception\FacebookProviderException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;

use Psr\Http\Message\ResponseInterface;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Zikula\ExtensionsModule\Api\VariableApi;
use Zikula\OAuthModule\Entity\Repository\MappingRepository;
use Zikula\OAuthModule\Exception\InvalidProviderConfigException;
use Zikula\OAuthModule\OAuthConstant;

/**
 * Facebook Helper
 * 
 * Handle facebook api via JSSDK
 * or PHPSDK
 * 
 */
class FacebookHelper extends AbstractProvider
{
    /**
     * Production Graph API URL.
     *
     * @const string
     */
    const BASE_FACEBOOK_URL = 'https://www.facebook.com/';

    /**
     * Beta tier URL of the Graph API.
     *
     * @const string
     */
    const BASE_FACEBOOK_URL_BETA = 'https://www.beta.facebook.com/';

    /**
     * Production Graph API URL.
     *
     * @const string
     */
    const BASE_GRAPH_URL = 'https://graph.facebook.com/';

    /**
     * Beta tier URL of the Graph API.
     *
     * @const string
     */
    const BASE_GRAPH_URL_BETA = 'https://graph.beta.facebook.com/';

    /**
     * Regular expression used to check for graph API version format
     *
     * @const string
     */
    const GRAPH_API_VERSION_REGEX = '~^v\d+\.\d+$~';
    
    /**
     * The Graph API version to use for requests.
     *
     * @var string
     */
    protected $graphApiVersion;

    /**
     * A toggle to enable the beta tier URL's.
     *
     * @var boolean
     */
    private $enableBetaMode = false;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var ResourceOwnerInterface
     */
    protected $user;

    /**
     * @var MappingRepository
     */
    protected $repository;

    /**
     * @var VariableApi
     */
    protected $variableApi;

    /**
     * @var AccessToken
     */
    protected $token;

    /**
     * FacebookHelper constructor.
     * @param Session $session
     * @param RequestStack $requestStack
     * @param RouterInterface $router
     * @param MappingRepository $repository
     * @param VariableApi $variableApi
     */
    public function __construct(
        Session $session, 
        RequestStack $requestStack, 
        RouterInterface $router, 
        MappingRepository $repository, 
        VariableApi $variableApi
    ) {

        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->repository = $repository;
        $this->variableApi = $variableApi;

        $settings = $this->variableApi->get('KaikmediaAuthModule', OAuthConstant::ALIAS_FACEBOOK);

        if (!isset($settings['clientId']) || !isset($settings['secretId'])) {
            throw new InvalidProviderConfigException('Invalid settings for Facebook OAuth provider.');
        }
        // todo ad id and secret check and error
        $options = [
            'clientId' => $settings['clientId'],
            'clientSecret' => $settings['secretId']
        ];

        if (empty($settings['api_version'])) {
            $message = 'The "graphApiVersion" option not set. Please set a default Graph API version.';
            throw new \InvalidArgumentException($message);
        } elseif (!preg_match(self::GRAPH_API_VERSION_REGEX, $settings['api_version'])) {
            $message = 'The "graphApiVersion" must start with letter "v" followed by version number, ie: "v2.4".';
            throw new \InvalidArgumentException($message);
        }

        $this->graphApiVersion = $settings['api_version'];

        // @todo - add beta tier to settings
        if (!empty($settings['enableBetaTier']) && $settings['enableBetaTier'] === true) {
            $this->enableBetaMode = true;
        }

        $collaborators = [];
        parent::__construct($options, $collaborators);

        $this->loadSessionData();
    }

    public function getAlias()
    {
        return OAuthConstant::ALIAS_FACEBOOK;
    }

    public function getBaseAuthorizationUrl()
    {
        return $this->getBaseFacebookUrl().$this->graphApiVersion.'/dialog/oauth';
    }

    public function getBaseAccessTokenUrl(array $params)
    {
        return $this->getBaseGraphUrl().$this->graphApiVersion.'/oauth/access_token';
    }

    public function getDefaultScopes()
    {
        return ['public_profile', 'email'];
    }

    /**
     * Returns the URL for requesting the resource owner's details.
     *
     * @param AccessToken $token
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        $fields = [
            'id', 'name', 'first_name', 'last_name',
            'email', 'hometown', 'picture.type(large){url,is_silhouette}',
            'cover{source}', 'gender', 'locale', 'link', 'timezone', 'age_range'
        ];

        // backwards compatibility less than 2.8
        if (version_compare(substr($this->graphApiVersion, 1), '2.8') < 0) {
            $fields[] = 'bio';
        }

        $appSecretProof = AppSecretProof::create($this->clientSecret, $token->getToken());

        return $this->getBaseGraphUrl().$this->graphApiVersion.'/me?fields='.implode(',', $fields)
                        .'&access_token='.$token.'&appsecret_proof='.$appSecretProof;
    }

    /**
     * Returns the URL for requesting the resource owner's details.
     *
     * @param AccessToken $token
     * @return string
     */
    public function getUserPermissionsUrl(AccessToken $token)
    {
        $appSecretProof = AppSecretProof::create($this->clientSecret, $token->getToken());

        return $this->getBaseGraphUrl().$this->graphApiVersion.'/me/permissions?access_token='.$token.'&appsecret_proof='.$appSecretProof;
    }

    /**
     * 
     *  PHP Access Token call
     * 
     */

    /**
     * Requests an access token using a specified grant and option set.
     *
     * @param  mixed $grant
     * @param  array $options
     * @return AccessToken
     */
    public function getAccessToken($grant = 'authorization_code', array $params = [])
    {
        if (isset($params['refresh_token'])) {
            throw new FacebookProviderException('Facebook does not support token refreshing.');
        }

        return parent::getAccessToken($grant, $params);
    }

    /**
     * Exchanges a short-lived access token with a long-lived access-token.
     *
     * @param string $accessToken
     *
     * @return \League\OAuth2\Client\Token\AccessToken
     *
     * @throws FacebookProviderException
     */
    public function getLongLivedAccessToken($accessToken)
    {
        $params = [
            'fb_exchange_token' => (string) $accessToken,
        ];

        return $this->getAccessToken('fb_exchange_token', $params);
    }

    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new FacebookUser($response);
    }

    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (!empty($data['error'])) {
            $message = $data['error']['type'].': '.$data['error']['message'];
            throw new IdentityProviderException($message, $data['error']['code'], $data);
        }
    }

    /**
     * @inheritdoc
     */
    protected function getContentType(ResponseInterface $response)
    {
        $type = parent::getContentType($response);

        // Fix for Facebook's pseudo-JSONP support
        if (strpos($type, 'javascript') !== false) {
            return 'application/json';
        }

        // Fix for Facebook's pseudo-urlencoded support
        if (strpos($type, 'plain') !== false) {
            return 'application/x-www-form-urlencoded';
        }

        return $type;
    }

    /**
     * Get the base Facebook URL.
     *
     * @return string
     */
    private function getBaseFacebookUrl()
    {
        return $this->enableBetaMode ? static::BASE_FACEBOOK_URL_BETA : static::BASE_FACEBOOK_URL;
    }

    /**
     * Get the base Graph API URL.
     *
     * @return string
     */
    private function getBaseGraphUrl()
    {
        return $this->enableBetaMode ? static::BASE_GRAPH_URL_BETA : static::BASE_GRAPH_URL;
    }

    // BC
    public function getProvider()
    {
        return $this;
    }

    /**
     * 
     *  JS Access Token
     * 
     */

    /**
     * Check request for accessToken string
     *
     * @return string
     */
    public function getAccessTokenStringFromRequest()
    {
        // we might want to get all access token data from reguest
        $request = $this->requestStack->getCurrentRequest();
        $accessToken = $request->request->get('accessToken', null);
        return $accessToken;
    }

    /**
     * Check if access token in request matches session 
     *  for accessToken string
     *
     * @return string
     */
    public function checkRequestAccessToken()
    {
        $requestTokenString = $this->getAccessTokenStringFromRequest();
        if (!$requestTokenString || !$this->isSameAccessTokenString($requestTokenString)) {
            $message = 'Wrong or missing access token';
            throw new \InvalidArgumentException($message);
        }
    }

    public function newAccessTokenFromString(String $newToken, $force = false)
    {
        $currentTokenString = $this->getAccessTokenAsStringOrFalse();      
        if (!$currentTokenString || !$this->isSameAccessTokenString($newToken) || $force) {
            $this->setAccessTokenFromString($newToken);
            $this->loadResourceOwnerData(true);
            $this->setSessionToken();
            $this->setSessionResourceOwnerData();
        }
    }
    public function setToken(AccessToken $token)
    {
        $this->token = $token;
    }
    public function getAccessTokenFromString(String $token)
    {
        return new AccessToken(['access_token' => $token]);
    }
    public function setAccessTokenFromString(String $token)
    {
        $this->setToken($this->getAccessTokenFromString($token));
    }
    
    public function isSameAccessTokenString(String $token)
    {
        return $this->getAccessTokenAsStringOrFalse() ? $this->getAccessTokenAsStringOrFalse() == $token : false;
    }

    public function getAccessTokenAsStringOrFalse()
    {
        return $this->token instanceof AccessToken ? $this->token->getToken() : false;
    }

    /**
     *  Facebook user 
     * 
     */
    public function getUser()
    {
        return $this->user;
    }

    public function loadResourceOwnerData($force = false)
    {
        if ($this->user instanceof FacebookUser && !$force) {
            return true;
        }

        $this->setUser($this->getResourceOwner($this->token));
    }
    public function setUser(FacebookUser $user)
    {
        $this->user = $user;
    }
    public function getUserArray()
    {
        return $this->user instanceof FacebookUser ? $this->user->toArray() : [];
    }
    public function getFacebookUserFormArray(Array $array)
    {
        return new FacebookUser($array);
    }
    public function setFacebookUserFormArray(Array $array)
    {
        $this->user = $this->getFacebookUserFormArray($array);
    }
    public function getFacebookUserId()
    {
        return $this->user instanceof FacebookUser ? $this->user->getId() : false;
    }
    public function getFacebookUserEmail()
    {
        return $this->user instanceof FacebookUser ? $this->user->getEmail() : false;
    }
    public function areEmailPermissionGranted()
    {
        $permissions  = $this->getFacebookUserPermissions();
        foreach ($permissions as $element) {
            if ($element['permission'] == 'email') {
                return $element['status'] == 'granted';
            }
        }

        return false;
    }

    public function getFacebookUserPermissions()
    {
        if (!$this->token) {
            $message = 'Missing access token';
            throw new \InvalidArgumentException($message);
        }

        $url = $this->getUserPermissionsUrl($this->token);
        $request = $this->getAuthenticatedRequest(self::METHOD_GET, $url, $this->token);
        $response = $this->getResponse($request);

        return array_key_exists('data', $response) ? $response['data'] : [];
    }

    /**
     * Start session
     * 
     */
    public function startFacebookSession($force = true)
    {
        // true (force) clean all previous sessions - tokens etc..
        if ($force) {
            $this->clearSessionResourceOwnerData();
            $this->clearSessionToken();
        }

        // get access token form request
        $accessTokenStringOrNull = $this->getAccessTokenStringFromRequest();
        if (!$accessTokenStringOrNull) {
            $message = 'Missing access token';
            throw new \InvalidArgumentException($message);
        }
        // as local variable
        $this->setAccessTokenFromString($accessTokenStringOrNull);

        // confirm access token with facebook by loading user data
        // as $this->user
        $this->loadResourceOwnerData(true);

        // no errors - actual starting local session by storing token 
        $this->setSessionToken();
        $this->setSessionResourceOwnerData();
    }
    // session
    private function loadSessionData()
    {
        if ($this->session->has('fb_access_token')) {
           $this->setAccessTokenFromString($this->session->get('fb_access_token'));
        }
        
        $sessionUser = $this->getSessionResourceOwnerData();
        if ($this->session->has('fb_user_data') && is_array($sessionUser)) {
            $this->setFacebookUserFormArray($sessionUser);
        }
    }
    public function getSessionToken()
    {
        return $this->session->get('fb_access_token', false);
    }
    public function setSessionToken()
    {
        $this->session->set('fb_access_token', $this->token->getToken());
    }
    public function getSessionResourceOwnerData()
    {
        return $this->session->get('fb_user_data', false);
    }
    public function setSessionResourceOwnerData()
    {
        $this->session->set('fb_user_data', $this->getUserArray());
    }
    public function clearSessionResourceOwnerData()
    {
        $this->session->remove('fb_user_data');
    }
    public function clearSessionToken()
    {
        $this->session->remove('fb_access_token');
    }
}