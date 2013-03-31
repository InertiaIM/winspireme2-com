<?php
namespace Inertia\WinspireBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\True;

class AccountType2 extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('address', 'text', array(
            'constraints' => array(
                new NotBlank(),
            ),
            'label' => 'Address Line 1'
        ));
        
        $builder->add('address2', 'text', array(
            'constraints' => array(
            ),
            'label' => 'Address Line 2  (Apt., Suite, etc.)',
            'required' => false
        ));
        
        $builder->add('city', 'text', array(
            'constraints' => array(
                new NotBlank(),
            )
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
        
        $builder->add('phone', 'text', array(
            'constraints' => array(
            ),
            'required' => false
        ));
        
        $builder->add('name', 'text', array(
            'constraints' => array(
                new NotBlank(),
            ),
            'label' => 'Name of Event',
            'mapped' => false
        ));
        
        $builder->add('date', 'text', array(
            'constraints' => array(
//                new Date(),
                new NotBlank(),
            ),
            'label' => 'Date of Event',
            'mapped' => false,
            'required' => true
        ));
        
        $builder->add('loa', 'checkbox', array(
            'constraints' => array(
                new True(array(
                    'message' => 'You must agree to the Letter of Agreement before proceeding.'
                )),
            ),
            'mapped' => false,
            'required' => true
        ));
        
        $builder->add('newsletter', 'checkbox', array(
            'label' => 'Please sign me up for the Winspire newsletter, offers and important updates.',
            'mapped' => false,
            'required' => false
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