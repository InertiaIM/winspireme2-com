<?php
namespace Inertia\WinspireBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class AccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', 'text', array(
            'constraints' => array(
                new NotBlank()
            ),
            'label' => 'Organization'
        ));
        $builder->add('state', 'choice', array(
            'choices' => array(
                'CA' => 'CA',
            ),
            'constraints' => array(
                new Choice(array(
                    'choices' => array(
                        'CA'
                    ),
                    'message' => 'Please choose a state.'
                )),
                new NotBlank(array(
                    'message' => 'Please choose a state.'
                ))
            ),
            'empty_value' => '',
            
        ));
        $builder->add('zip', 'text', array(
            'constraints' => array(
                new NotBlank(),
                new Length(array('min' => 3)),
            ),
            'label' => 'Zip Code'
        ));
    }
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Inertia\WinspireBundle\Entity\Account',
        ));
    }

    public function getName()
    {
        return 'account';
    }
}