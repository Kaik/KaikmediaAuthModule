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

namespace Kaikmedia\AuthModule\Controller;

use Gedmo\Mapping\Annotation\Uploadable;
use Kaikmedia\AuthModule\Constant;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Zikula\Bundle\HookBundle\Hook\ProcessHook;
use Zikula\Bundle\HookBundle\Hook\ValidationHook;
use Zikula\Core\Event\GenericEvent;
use Zikula\Core\Controller\AbstractController;
use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Provider\FacebookUser;
use Zikula\OAuthModule\OAuthConstant;
use League\OAuth2\Client\Token\AccessToken;
use phpDocumentor\Reflection\Types\Integer;
use PhpParser\Node\Expr\Cast\Int_;
use Zikula\UsersModule\AccessEvents;
use Zikula\UsersModule\Entity\UserEntity;
use Zikula\UsersModule\HookSubscriber\LoginUiHooksSubscriber;
use Zikula\UsersModule\Constant as UsersConstant;
use Zikula\OAuthModule\Entity\MappingEntity;
use Zikula\UsersModule\RegistrationEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class FacebookController
 * 
 * @Route("/facebook")
 * 
 */
class FacebookController extends AbstractController
{
    /**
     * Facebook helper 
     * @var Zikula\OAuthModule\Helper\FacebookHelper
     */
    protected $facebookHelper;

    /**
     *
     * @param Request $request Current request instance
     * @param Bolean $checkMode mode swich
     *
     * @return void
     **/
    public function setFacebookHelper($checkMode = true)
    {
        $this->facebookHelper = $this->get('kaikmedia_auth_module.helper.facebook_helper');
        if ($checkMode) {
            $this->facebookHelper->checkRequestAccessToken();
        } else {
            $this->facebookHelper->startFacebookSession(true);
        }
    }

    /**
     * @Route("/start", methods = {"POST", "GET"}, options={"expose"=true})
     *
     * @param Request $request Current request instance
     *
     * @return JsonResponse
     **/
    public function startAction(Request $request)
    {
        if (!$this->hasPermission('KaikmediaAuthModule', 'facebok:start:', ACCESS_OVERVIEW)) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Access Denied')], 401);
        }

        try {
            $this->setFacebookHelper(false);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        return new JsonResponse(['status' => 'success']);
    }

    /**
     * @Route("/iszikulauser", methods = {"POST", "GET"}, options={"expose"=true})
     *
     * @return JsonResponse
     **/
    public function isZikulaUserAction()
    {
        if (!$this->hasPermission('KaikmediaAuthModule', 'facebok:iszikulauser:', ACCESS_OVERVIEW)) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Access Denied')], 401);
        }

        try {
            $this->setFacebookHelper();
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        $fbUserId = $this->facebookHelper->getFacebookUserId();
        if (!$fbUserId) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Missing facebook user id!.')], 400);
        }
        $mappingRepository = $this->get('zikula_oauth_module.mapping_repository');
        $uid = $mappingRepository->getZikulaId('facebook', $fbUserId);
        if ($uid) {
            // mapping already exist
            return new JsonResponse(['status' => 'found', 'account' => $uid]);
        }

        return new JsonResponse(['status' => 'notfound']);
    }

    /**
     * @Route("/checkemail", methods = {"POST", "GET"}, options={"expose"=true})
     *
     * @param Request $request Current request instance
     *
     * @return JsonResponse
     **/
    public function checkEmailAction(Request $request)
    {
        if (!$this->hasPermission('KaikmediaAuthModule', 'facebok:checkemail:', ACCESS_OVERVIEW)) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Access Denied')], 401);
        }

        try {
            $this->setFacebookHelper();
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        $userEmail = $this->facebookHelper->getFacebookUserEmail();
        if (!$userEmail) {
            // check why
            if ($this->facebookHelper->areEmailPermissionGranted()) {
                return new JsonResponse(['status' => 'missing']);
            } 
            
            return new JsonResponse(['status' => 'prohibited']);
        }

        // check if email is registered
        $userRepository = $this->get('zikula_users_module.user_repository');
        $foundUsers = $userRepository->getUsersByEmail($userEmail);
        $data = ['status' => count($foundUsers) > 0 ? 'present-registered' : 'present-unregistered',
                'count' => count($foundUsers),
        ];

        $profileModule = $this->get('zikula_users_module.internal.profile_module_collector')->getSelected();
        foreach($foundUsers as $key => $user) {
            $data['accounts'][$key] = ['uid' => $user->getUid(),
                                       'uname' => $user->getUname(),
                                       'avatar' => $profileModule->getAvatar($user->getUid(), ['rating' => 'g'])
                                        ];

        }

        return new JsonResponse($data);
    }

    /**
     * @Route("/connectaccount", methods = {"POST", "GET"}, options={"expose"=true})
     *
     * @param Request $request Current request instance
     *
     * @return JsonResponse
     **/
    public function connectAccountAction(Request $request)
    {
        if (!$this->hasPermission('KaikmediaAuthModule', 'facebok:connect:', ACCESS_OVERVIEW)) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Access Denied')], 401);
        }

        try {
            $this->setFacebookHelper();
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        $account = (int) $request->request->get('account', null);
        if (!$account) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Missing account id.')], 400);
        }

        $userRepository = $this->get('zikula_users_module.user_repository');
        $user = $userRepository->find($account);
        if (!$user) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Account not found')], 400);
        }

        $fbUserId = $this->facebookHelper->getFacebookUserId();
        if (!$fbUserId) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Facebook user id is missing!')], 400);
        }

        $mappingRepository = $this->get('zikula_oauth_module.mapping_repository');
        $mapping = new MappingEntity();
        $mapping->setMethod($this->facebookHelper->getAlias());
        $mapping->setMethodId($fbUserId);
        $mapping->setZikulaId($user->getUid());
        $mappingRepository->persistAndFlush($mapping);

        return new JsonResponse(['status' => 'connected']);
    }

    /**
     * @Route("/disconnectaccount", methods = {"POST", "GET"}, options={"expose"=true})
     *
     * @param Request $request Current request instance
     *
     * @return JsonResponse
     **/
    public function disconnectAccountAction(Request $request)
    {
        if (!$this->hasPermission('KaikmediaAuthModule', 'facebok:disconnectaccount:', ACCESS_OVERVIEW)) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Access Denied')], 401);
        }

        try {
            $this->setFacebookHelper();
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        $fbUserId = $this->facebookHelper->getFacebookUserId();
        if (!$fbUserId) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Facebook user id is missing!')], 400);
        }

        $mappingRepository = $this->get('zikula_oauth_module.mapping_repository');
        $uid = $mappingRepository->getZikulaId(Constant::ALIAS_FACEBOOK, $fbUserId);
        if (!$uid) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Account is not connected')], 400);
        }

        $mappingRepository->removeByZikulaId($uid);

        return new JsonResponse(['status' => 'disconnected']);
    }

    /**
     * @Route("/login", methods = {"POST", "GET"}, options={"expose"=true})
     *
     * @param Request $request Current request instance
     *
     * @return JsonResponse
     **/
    public function logInAction(Request $request)
    {
        if (!$this->hasPermission('KaikmediaAuthModule', 'facebok:login:', ACCESS_OVERVIEW)) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Access Denied')], 401);
        }

        try {
            $this->setFacebookHelper();
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        $account = (int) $request->request->get('account', null);
        if (!$account) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Missing account id.')], 400);
        }

        $userRepository = $this->get('zikula_users_module.user_repository');
        $user = $userRepository->find($account);
        if (!$user) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Account not found')], 400);
        }

        $rememberMe = true;
        $dispatcher = $this->get('event_dispatcher');
        $dispatcher->dispatch(AccessEvents::LOGIN_STARTED, new GenericEvent());

        $hook = new ValidationHook();
        $this->get('hook_dispatcher')->dispatch(LoginUiHooksSubscriber::LOGIN_VALIDATE, $hook);
        $validators = $hook->getValidators();
        if ($validators->hasErrors()) {
            $errors = $validators->getErrors();

            return new JsonResponse(['status' => 'error', 'message' => implode(' ', $errors)], 400);
        } else if (!$this->get('zikula_users_module.helper.access_helper')->loginAllowed($user)) {

            return new JsonResponse(['status' => 'error', 'message' => $this->__('Login is not allowed')], 400);
        } else {
            $this->get('hook_dispatcher')->dispatch(LoginUiHooksSubscriber::LOGIN_PROCESS, new ProcessHook($user));
            $event = new GenericEvent($user, ['authenticationMethod' => $this->facebookHelper->getAlias()]);
            $dispatcher->dispatch(AccessEvents::LOGIN_VETO, $event);
            if (!$event->isPropagationStopped()) {
                $this->get('zikula_users_module.helper.access_helper')->login($user, $rememberMe);
                $eventArgs = [
                    'authenticationMethod' => $this->facebookHelper->getAlias(),
                ];
                $defaultLastLogin = new \DateTime("1970-01-01 00:00:00");
                $actualLastLogin = $user->getLastlogin();
                if (empty($actualLastLogin) || $actualLastLogin == $defaultLastLogin) {
                    $eventArgs['isFirstLogin'] = true;
                }
                $event = new GenericEvent($user, $eventArgs);
                $event = $this->get('event_dispatcher')->dispatch(AccessEvents::LOGIN_SUCCESS, $event);

                return new JsonResponse(['status' => 'loggedin']);

            } else {
                $message  = $event->hasArgument('flash') ? $event->getArgument('flash') : $this->__('Log in stopped.') ;

                return new JsonResponse(['status' => 'error', 'message' => $message], 400);
            }
        }
    }

    /**
     * @Route("/register", methods = {"POST", "GET"}, options={"expose"=true})
     *
     * @param Request $request Current request instance
     *
     * @return JsonResponse
     **/
    public function registerAction(Request $request)
    {
        if (!$this->hasPermission('KaikmediaAuthModule', 'facebok:register:', ACCESS_OVERVIEW)) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Access Denied')], 401);
        }

        $variableApi = $this->get('zikula_extensions_module.api.variable');

        if (!$variableApi->get('ZikulaUsersModule', UsersConstant::MODVAR_REGISTRATION_ENABLED, UsersConstant::DEFAULT_REGISTRATION_ENABLED)) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Registration is disabled.')], 400);
        }

        try {
            $this->setFacebookHelper();
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        $dispatcher = $this->get('event_dispatcher');
        // $hookDispatcher = $this->get('hook_dispatcher');

        $dispatcher->dispatch(RegistrationEvents::REGISTRATION_INITIATED, new GenericEvent());        

        // not allowed atm
        $multipleSameAccountsAllowed = $this->getVar('multipleSameAccountsAllowed', false);

        $facebookUser = $this->facebookHelper->getUser();
        if (!$facebookUser) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Facebook user is missing!')], 400);
        }

        $userRegistrationHelper = $this->get('kaikmedia_auth_module.helper.user_registration_helper');

        $dispatcher->dispatch(RegistrationEvents::REGISTRATION_STARTED, new GenericEvent());

        $userEntity = $userRegistrationHelper->createUserAccountFromFacebook($facebookUser);

        $validationErrors = $this->get('validator')->validate($userEntity);
        if (count($validationErrors) > 0) {
            $errorsTxt = '';
            foreach ($validationErrors as $validationError) {
                $errorsTxt .= $validationError->getMessage(). ' ';
            }

            return new JsonResponse(['status' => 'error', 'message' => $errorsTxt], 400);
        }

        $this->get('zikula_users_module.helper.registration_helper')->registerNewUser($userEntity);
        $data = [
            'uid' => $userEntity->getUid(),
            'uname' => $userEntity->getUname(),
            'email' => $userEntity->getEmail(),
        ];

        try {
            $userRegistrationHelper->mapUserAccount($data);
        } catch(\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        $dispatcher->dispatch(RegistrationEvents::REGISTRATION_SUCCEEDED, new GenericEvent($userEntity));

        return new JsonResponse(['status' => 'registered', 'account' => $data]);
    }

    /**
     * @Route("/getaccount", methods = {"POST", "GET"}, options={"expose"=true})
     *
     * @param Request $request Current request instance
     *
     * @return JsonResponse
     **/
    public function getAccountAction(Request $request)
    {
        if (!$this->hasPermission('KaikmediaAuthModule', 'facebok:getaccount:', ACCESS_OVERVIEW)) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Access Denied')], 401);
        }

        try {
            $this->setFacebookHelper();
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        $facebookUser = $this->facebookHelper->getUser();
        if (!$facebookUser) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Facebook user is missing!')], 400);
        }

        return new JsonResponse(['status' => 'success', 'fbUser' => $facebookUser->toArray()]);
    }

    /**
     * @Route("/updateavatar", methods = {"POST", "GET"}, options={"expose"=true})
     *
     * @param Request $request Current request instance
     *
     * @return JsonResponse
     **/
    public function updateAvatarAction(Request $request)
    {
        if (!$this->hasPermission('KaikmediaAuthModule', 'facebok:updateavatar:', ACCESS_OVERVIEW)) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Access Denied')], 401);
        }

        try {
            $this->setFacebookHelper();
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        $facebookUser = $this->facebookHelper->getUser();
        if (!$facebookUser) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Facebook user is missing!')], 400);
        }

        $mappingRepository = $this->get('zikula_oauth_module.mapping_repository');
        $uid = $mappingRepository->getZikulaId(Constant::ALIAS_FACEBOOK, $facebookUser->getId());
        if (!$uid) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Account is not connected')], 400);
        }

        $userRepository = $this->get('zikula_users_module.user_repository');
        $user = $userRepository->find($uid);
        if (!$user) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Account not found')], 400);
        }

        try {
            $avatarHelper = $this->get('kaikmedia_auth_module.helper.avatar_helper');
            $avatarFileName = $avatarHelper->handleDownload($facebookUser->getPictureUrl() , $uid);
            $user->setAttribute('zpmpp:avatar', $avatarFileName);
            $this->getDoctrine()->getManager()->flush();
            $avatarSrc = $avatarHelper->getAvatarSrc($avatarFileName);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        return new JsonResponse(['status' => 'success', 'src' => $request->getSchemeAndHttpHost() . $request->getBasePath() . '/' . $avatarSrc ]);
    }

    /**
     * @Route("/updatename", methods = {"POST", "GET"}, options={"expose"=true})
     *
     * @param Request $request Current request instance
     *
     * @return JsonResponse
     **/
    public function updateNameAction(Request $request)
    {
        if (!$this->hasPermission('KaikmediaAuthModule', 'facebok:updatename:', ACCESS_OVERVIEW)) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Access Denied')], 401);
        }

        try {
            $this->setFacebookHelper();
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        $facebookUser = $this->facebookHelper->getUser();
        if (!$facebookUser) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Facebook user is missing!')], 400);
        }

        $mappingRepository = $this->get('zikula_oauth_module.mapping_repository');
        $uid = $mappingRepository->getZikulaId(Constant::ALIAS_FACEBOOK, $facebookUser->getId());
        if (!$uid) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Account is not connected')], 400);
        }

        $userRepository = $this->get('zikula_users_module.user_repository');
        $user = $userRepository->find($uid);
        if (!$user) {
            return new JsonResponse(['status' => 'error', 'message' => $this->__('Account not found')], 400);
        }

        $user->setAttribute('zpmpp:realname', $facebookUser->getName());
        $this->getDoctrine()->getManager()->flush();

        return new JsonResponse(['status' => 'success', 'name' => $facebookUser->getName()]);
    }

    /**
     * @Route("/preferences", methods = {"GET"})
     *
     * @param Request $request Current request instance
     *
     * @return JsonResponse
     **/
    public function preferencesAction(Request $request)
    {
        // hasPermission($level = ACCESS_READ, $throw = true, $component = null, $instance = null, $user = null, $loggedIn = false)
        $this->get('zikula_intercom_module.access_manager')
                ->hasPermission(ACCESS_READ, true, 'facebok:preferences:', null, null, true);

        $currentUserUid = $this->get('zikula_users_module.current_user')->get('uid');
        $userEntity = $this->get('zikula_users_module.user_repository')->find($currentUserUid);
        $mappingRepository = $this->get('zikula_oauth_module.mapping_repository');
        // idea 1
        // to save fb calls we will just check if user is connected
        // if not we will show connect button
        // if yes we will show name and avatar with button to load user data
        // everything via js

        // there is a problem with idea 1
        // user can have multiple zikula accounts
        // only one account shoudl be connected to fb
        // so if this uid is not conected the only way to check is to look for facebook id in mapping table
        // but again if connection will be checked here it might end up with blank api calls
        // it is better to check this when user clicks connect instead of prechecking here...

        $isConnected = $mappingRepository->findOneBy(['zikulaId' => $currentUserUid]);

        return $this->render('@KaikmediaAuthModule/Facebook/user.preferences.html.twig', [
            'user' => $userEntity,
            'isConnected' => $isConnected,
        ]);
    }
}

// return $this->render('@KaikmediaAuthModule/Facebook/default.html.twig', []);