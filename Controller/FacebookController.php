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
}

// return $this->render('@KaikmediaAuthModule/Facebook/default.html.twig', []);