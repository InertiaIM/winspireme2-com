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
    protected $states = array(
        'AL' => 'AL',
        'AK' => 'AK',
        'AZ' => 'AZ',
        'AR' => 'AR',
        'CA' => 'CA',
        'CO' => 'CO',
        'CT' => 'CT',
        'DE' => 'DE',
        'FL' => 'FL',
        'GA' => 'GA',
        'HI' => 'HI',
        'ID' => 'ID',
        'IL' => 'IL',
        'IN' => 'IN',
        'IA' => 'IA',
        'KS' => 'KS',
        'KY' => 'KY',
        'LA' => 'LA',
        'ME' => 'ME',
        'MD' => 'MD',
        'MA' => 'MA',
        'MI' => 'MI',
        'MN' => 'MN',
        'MS' => 'MS',
        'MO' => 'MO',
        'MT' => 'MT',
        'NE' => 'NE',
        'NV' => 'NV',
        'NH' => 'NH',
        'NJ' => 'NJ',
        'NM' => 'NM',
        'NY' => 'NY',
        'NC' => 'NC',
        'ND' => 'ND',
        'OH' => 'OH',
        'OK' => 'OK',
        'OR' => 'OR',
        'PA' => 'PA',
        'RI' => 'RI',
        'SC' => 'SC',
        'SD' => 'SD',
        'TN' => 'TN',
        'TX' => 'TX',
        'UT' => 'UT',
        'VT' => 'VT',
        'VA' => 'VA',
        'WA' => 'WA',
        'WV' => 'WV',
        'WI' => 'WI',
        'WY' => 'WY',
        'DC' => 'DC',
    );
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        ksort($this->states);
        
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
            'choices' => $this->states,
            'constraints' => array(
                new Choice(array(
                    'choices' => $this->states,
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