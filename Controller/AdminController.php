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

use Kaikmedia\AuthModule\Form\Type\PreferencesType;
use Kaikmedia\AuthModule\Form\Type\AdminFacebookType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Zikula\Core\Controller\AbstractController;
use Zikula\ThemeModule\Engine\Annotation\Theme;

/**
 * @Route("/admin")
 */
class AdminController extends AbstractController
{
    /**
     * @Route("/index")
     *
     * @Theme("admin")
     *
     * the main administration function
     *
     * @return RedirectResponse
     */
    public function indexAction()
    {
        // access throw component instance user
        $this->get('kaikmedia_auth_module.access_manager')->hasPermission(ACCESS_ADMIN, true);

        return $this->render('@KaikmediaAuthModule/Admin/index.html.twig', [

        ]);
    }

    /**
     * @Route("/facebook")
     *
     * @Theme("admin")
     *
     * the main administration function
     *
     * @return RedirectResponse
     */
    public function facebookAction(Request $request)
    {
        // access throw component instance user
        $this->get('kaikmedia_auth_module.access_manager')->hasPermission(ACCESS_ADMIN, true);

        $settings = $this->getVar('facebook', []);
        $settings['enabled'] = array_key_exists('enabled', $settings) ? $settings['enabled'] : false;
        $settings['xfbml'] = array_key_exists('xfbml', $settings) ? $settings['xfbml'] : true;
        $settings['status'] = array_key_exists('status', $settings) ? $settings['status'] : true;
        $settings['cookie'] = array_key_exists('cookie', $settings) ? $settings['cookie'] : false;
        $settings['frictionlessRequests'] = array_key_exists('frictionlessRequests', $settings) ? $settings['frictionlessRequests'] : false;
        // $settings['hideFlashCallback'] = array_key_exists('hideFlashCallback', $settings) ? $settings['hideFlashCallback'] : false;

        $formBuilder = $this->get('form.factory')
            ->createBuilder(AdminFacebookType::class, $settings)
            // ->setMethod('POST')
        ;
        $formBuilder
            ->add('save', SubmitType::class)
        ;
        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isValid()) {
            $data = $form->getData();
            $this->setVar('facebook', $data);
            $request->getSession()
                ->getFlashBag()
                ->add('status', $this->__('Setting changes.'));

            $this->redirect($this->generateUrl('kaikmediaauthmodule_admin_facebook'));    
        }

        return $this->render('@KaikmediaAuthModule/Facebook/admin.form.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/preferences")
     *
     * @Theme("admin")
     *
     * @return Response symfony response object
     * @throws AccessDeniedException Thrown if the user doesn't have admin access to the module
     */
    public function preferencesAction(Request $request)
    {
        // access throw component instance user
        $this->get('kaikmedia_auth_module.access_manager')->hasPermission(ACCESS_ADMIN, true);

        $settings = $this->getVar('global', []);
        $settings['enabled'] = $this->getVar('enabled', false);
        $formBuilder = $this->get('form.factory')
            ->createBuilder(PreferencesType::class, $settings)
            // ->setMethod('POST')
        ;
        $formBuilder
            ->add('save', SubmitType::class)
        ;
        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isValid()) {
            $data = $form->getData();
            $enabled = array_key_exists('enabled', $data) ? $data['enabled'] : false;
            unset($data['enabled']);
            $this->setVar('enabled', $enabled);
            $this->setVar('global', $data);
            $request->getSession()
                ->getFlashBag()
                ->add('status', $this->__('Setting changes.'));

            $this->redirect($this->generateUrl('kaikmediaauthmodule_admin_preferences'));    
        }

        return $this->render('@KaikmediaAuthModule/Admin/preferences.html.twig', [
            'form' => $form->createView()
        ]);
    }
}
