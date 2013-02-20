<?php
namespace Inertia\WinspireBundle\Form\Type;

use FOS\UserBundle\Form\Type\RegistrationFormType as BaseType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

class UserType extends BaseType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        
        $builder->add('firstName', null, array(
            'constraints' => new NotBlank(),
            'label' => 'First Name',
            'required' => true
        ));
        $builder->add('lastName', null, array(
            'constraints' => new NotBlank(),
            'label' => 'Last Name',
            'required' => true
        ));
        $builder->add('phone', 'text', array(
            'constraints' => array(
                new Length(array('min' => 8)),
                new NotBlank(),
            ),
        ));
        $builder->add('newsletter', 'checkbox', array(
            'label' => 'Please sign me up for the Winspire newsletter, offers and important updates.',
            'required' => false
        ));
    }
    
//    public function getName()
//    {
//        return 'user';
//    }
}