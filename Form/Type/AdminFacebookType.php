<?php

/**
 * KaikMedia AuthModule
 *
 * @package    KaikmediaAuthModule
 * @author     Kaik <contact@kaikmedia.com>
 * @copyright  KaikMedia
 * @link       https://github.com/Kaik/KaikmediaAuthModule.git
 */

namespace Kaikmedia\AuthModule\Form\Type;

use Kaikmedia\AuthModule\Constant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Zikula\Common\Translator\IdentityTranslator;

class AdminFacebookType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('enabled', ChoiceType::class, ['choices' => ['Off' => false, 'On' => true],
            'multiple' => false,
            'expanded' => true,
            'required' => true])  


            ->add('clientId', TextType::class,[
                'required' => false,
            ])
            ->add('secretId', TextType::class,[
                'required' => false,
            ])
            ->add('api_version', ChoiceType::class, [
                'required' => true,
                'choices' => [
                    'v8.0' => 'v8.0',
                    'v7.0' => 'v7.0',
                    'v6.0' => 'v6.0',
                    'v5.0' => 'v5.0',
                    'v4.0' => 'v4.0',
                    'v3.3' => 'v3.3',
                    'v3.2' => 'v3.2',
                    'v3.1' => 'v3.1',
                ],
            ])
            ->add('xfbml', ChoiceType::class, ['choices' => ['Off' => false, 'On' => true],
            'multiple' => false,
            'expanded' => true,
            'required' => true])
            ->add('status', ChoiceType::class, ['choices' => ['Off' => false, 'On' => true],
            'multiple' => false,
            'expanded' => true,
            'required' => true])
            ->add('cookie', ChoiceType::class, ['choices' => ['Off' => false, 'On' => true],
            'multiple' => false,
            'expanded' => true,
            'required' => true])            
            ->add('frictionlessRequests', ChoiceType::class, ['choices' => ['Off' => false, 'On' => true],
            'multiple' => false,
            'expanded' => true,
            'required' => true])  

            // button

    //    $size = array_key_exists('button_size', $settings) ? $settings['button_size'] : 'large';
    //    $button_type = array_key_exists('button_type', $settings) ? $settings['button_type'] : 'continue_with';
    //    $auto_logout_link = array_key_exists('auto_logout_link', $settings) ? $settings['auto_logout_link'] : 'default';
    //    $use_continue_as = array_key_exists('use_continue_as', $settings) && $settings['use_continue_as'] ? 'yes' : 'no';

            ->add('button_size', ChoiceType::class, [
                'choices' => [
                    'Small' => 'small',
                    'Medium' => 'medium',
                    'Large' => 'large'
                ],
            'multiple' => false,
            'expanded' => false,
            'required' => true])
            ->add('button_type', ChoiceType::class, [
                'choices' => [
                    'Continue with' => 'continue_with',
                    'Login with' => 'login_with',
                ],
            'multiple' => false,
            'expanded' => false,
            'required' => true])
            ->add('button_layout', ChoiceType::class, [
                'choices' => [
                    'Default' => 'default',
                    'Rounded' => 'rounded',
                ],
            'multiple' => false,
            'expanded' => false,
            'required' => true])
            ->add('auto_logout_link', ChoiceType::class, ['choices' => ['Off' => false, 'On' => true],
            'multiple' => false,
            'expanded' => true,
            'required' => true])              
            ->add('use_continue_as', ChoiceType::class, ['choices' => ['Off' => false, 'On' => true],
            'multiple' => false,
            'expanded' => true,
            'required' => true]) 
            ->add('redirectHomePaths', TextareaType::class, [
            'required' => true])              
            ->add('download_user_avatar', ChoiceType::class, ['choices' => ['Off' => false, 'On' => true],
            'multiple' => false,
            'expanded' => true,
            'required' => true])            
            ->add('enable_facebook_user_settings', ChoiceType::class, ['choices' => ['Off' => false, 'On' => true],
            'multiple' => false,
            'expanded' => true,
            'required' => true])            
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return Constant::ADMIN_FORM_FACEBOOK;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'translator' => new IdentityTranslator(),
        ]);
    }
}
