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

use Kaikmedia\AuthModule\Constant;
use League\OAuth2\Client\Provider\FacebookUser;

use Symfony\Component\Validator\Validator\ValidatorInterface;

use Zikula\ExtensionsModule\Api\VariableApi;
use Zikula\OAuthModule\Entity\MappingEntity;
use Zikula\OAuthModule\Entity\Repository\MappingRepository;
use Zikula\OAuthModule\OAuthConstant;
use Zikula\ZAuthModule\Api\ApiInterface\PasswordApiInterface;
use Zikula\ZAuthModule\Entity\AuthenticationMappingEntity;
use Zikula\ZAuthModule\Entity\RepositoryInterface\AuthenticationMappingRepositoryInterface;
use Zikula\ZAuthModule\ZAuthConstant;
use Zikula\UsersModule\Entity\UserEntity;
use Zikula\UsersModule\Constant as UsersConstant;
use Zikula\UsersModule\Entity\RepositoryInterface\UserRepositoryInterface;

/**
 * UserRegistrationHelper
 * 
 */
class UserRegistrationHelper
{
    /**
     * @var AuthenticationMappingRepositoryInterface
     */
    private $mappingRepository;

    /**
     * @var UserRepositoryInterface
     */
    private $userRepository;

    /**
     * @var VariableApi
     */
    protected $variableApi;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var PasswordApiInterface
     */
    private $passwordApi;

    /**
     * UserRegistrationHelper constructor.
     * @param AuthenticationMappingRepositoryInterface $mappingRepository
     * @param UserRepositoryInterface $userRepository
     * @param VariableApi $variableApi
     * @param ValidatorInterface $validator
     * @param PasswordApiInterface $passwordApi
     */
    public function __construct(
        AuthenticationMappingRepositoryInterface $mappingRepository,
        UserRepositoryInterface $userRepository,
        VariableApi $variableApi,
        ValidatorInterface $validator,
        PasswordApiInterface $passwordApi
    ) {
        $this->mappingRepository = $mappingRepository;
        $this->userRepository = $userRepository;
        $this->variableApi = $variableApi;
        $this->validator = $validator;
        $this->passwordApi = $passwordApi;        
    }

    /**
     * 
     */
    public function createUserAccountFromFacebook(FacebookUser $FacebookUser)
    {
        // To Create account we have to check more things
        // first lets generate username 
        $username = $this->createUserNameFromFacebook($FacebookUser);
        $userEntity = new UserEntity();
        $userEntity->setUname($username);
        $userEntity->setEmail($FacebookUser->getEmail());

        // i'm not sure why this thing is set here...
        $userEntity->setAttribute(UsersConstant::AUTHENTICATION_METHOD_ATTRIBUTE_KEY, OAuthConstant::ALIAS_FACEBOOK);
        // we need that it will be changed to created user uid 
        // unless problems will occur like RegistrationEvents::FULL_USER_CREATE_VETO :)
        // we could use admin instead of guest but tbh it is guest
        $userEntity->setApproved_By(1); 
        // add more atributes here like 
        // zikula name 
        // avatar profile pic
        // bio and other stuff from public profile data (check data)

        // $this->get('zikula_users_module.helper.registration_helper')->registerNewUser($userEntity);

        return $userEntity;

    }

    // To Create account we have to prepare username
    private function createUserNameFromFacebook(FacebookUser $FacebookUser)
    {
        // first lets remove spaces and all bad characters from initial facebook name
        // $legalName will be our base for username generation
        // and truncate to max 
        $legalName = $this->removeIllegalCharacters($FacebookUser->getName());
        // above is just an preparation
        // this function makes all the rest of the magic
        $uniqueAllowedLegalNameWithRightSize = $this->getUniqueUsernameWithRightSize($legalName);
        // $uniqueAllowedLegalNameWithRightSize = $legalName;//$this->getUniqueUsernameWithRightSize($legalName);
        // done :)
        return $uniqueAllowedLegalNameWithRightSize;
    }

    // this function returns valid username to check
    // it bases on facebook username but after more that 10 loops 
    // or 
    private function removeIllegalCharacters(String $string):string
    {
        // remove double spaces
        $legalNameArray = [];
        // remove illegal characters
        $stringArray = str_split(trim($string));
        $testArr = [];
        foreach ($stringArray as $key => $character) {
            if (($character !== ' ') && !$this->isLegalCharacter($character)) {
                unset($stringArray[$key]);
            }
        }

        return (string) implode($stringArray);
    }

    private function isLegalCharacter(String $character)
    {
        return preg_match('/^' . UsersConstant::UNAME_VALIDATION_PATTERN . '$/uD', $character);
    }

    // this function returns valid username to check
    // it bases on facebook username but after more that 10 loops 
    // or 
    private function getUniqueUsernameWithRightSize(String $username)
    {
        $this->illegalUserNames = $this->variableApi->get('ZikulaUsersModule', UsersConstant::MODVAR_REGISTRATION_ILLEGAL_UNAMES, '');
        $isOkCheck = function ($username) {
            if(!$this->checkIfIsRightSize($username)) {
                return false;
            }
            if(!$this->checkIfIsAllowed($username)) {
                return false;
            }            
            // is unique
            return count($this->userRepository->getUsersByUsername($username)) == 0;
        };

        while (!$isOkCheck($username)) {
            $username = $this->generateRandom($username);
        }

        return $username;
    }

    private function checkIfIsAllowed(String $username)
    {
        $illegalUserNames = $this->illegalUserNames;
        if (!empty($illegalUserNames)) {
            $pattern = ['/^(\s*,\s*|\s+)+/D', '/\b(\s*,\s*|\s+)+\b/D', '/(\s*,\s*|\s+)+$/D'];
            $replace = ['', '|', ''];
            $illegalUserNames = preg_replace($pattern, $replace, preg_quote($illegalUserNames, '/'));
            if (preg_match("/^({$illegalUserNames})/iD", $username)) {
                return false;
            }
        }

        return true;
    }

    private function checkIfIsRightSize(String $username)
    {
        return strlen($username) <= UsersConstant::UNAME_VALIDATION_MAX_LENGTH && strlen($username) >= 1;
    }

    private function generateRandom(String $username)
    {
        $append = (strlen($username) + 1) < UsersConstant::UNAME_VALIDATION_MAX_LENGTH;
        $randomChar = substr(str_shuffle(md5(time())),0,1);
        $username = $append ? $username.$randomChar : (substr($username, 0, -1).$randomChar);

        return $username;
    }

    /**
     * {@inheritdoc}
     * 
     * 
     */
    public function mapUserAccount(array $data)
    {
        if (!$this->variableApi->get(Constant::MODNAME, 'registerAsNative', false)) {

            return;
        }

        $mapping = new AuthenticationMappingEntity();
        $mapping->setUid($data['uid']);
        $mapping->setUname($data['uname']);
        $mapping->setEmail($data['email']);

        // @TODO autogenerate passowrd?
        if (empty($data['pass'])) {
            $mapping->setPass('');
        } else {
            $mapping->setPass($this->passwordApi->getHashedPassword($data['pass']));
        }

        // set by settings ? default Native Either
        // @TODO need to get default native method here...
        // $method = 
        $mapping->setMethod(ZAuthConstant::AUTHENTICATION_METHOD_EITHER);

        $mapping->setVerifiedEmail(true);
        $errors = $this->validator->validate($mapping);

        // email address constrain validation
        // @TODO should be turned off in case multiple accounts are allowed
        if (!$this->variableApi->get(Constant::MODNAME, 'multipleSameAccountsAllowed', false)) {
            if (count($errors) > 0) {
                $errorsTxt = '';
                foreach ($errors as $error) {
                    $errorsTxt .= $error->getMessage(). ' ';
                }
                // throw error
                throw new \Exception($errorsTxt);
            }
        }
        $this->mappingRepository->persistAndFlush($mapping);

        return true;
    }
}