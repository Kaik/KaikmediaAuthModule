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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Zikula\Common\Translator\IdentityTranslator;
use Kaikmedia\AuthModule\Constant;

class PreferencesType extends AbstractType
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
            ->add('multipleSameAccountsAllowed', ChoiceType::class, ['choices' => ['Off' => false, 'On' => true],
            'multiple' => false,
            'expanded' => true,
            'required' => true])  
            ->add('registerAsNative', ChoiceType::class, ['choices' => ['Off' => false, 'On' => true],
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
        return Constant::ADMIN_FORM_PREFERENCES;
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
