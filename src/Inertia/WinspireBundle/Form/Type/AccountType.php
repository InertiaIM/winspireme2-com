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
    protected $countries = array(
        'CA' => 'Canada',
        'US' => 'United States'
    );
    
    protected $states = array(
        'CA-AB' => 'Alberta',
        'CA-BC' => 'British Columbia',
        'CA-MB' => 'Manitoba',
        'CA-NB' => 'New Brunswick',
        'CA-NL' => 'Newfoundland and Labrador',
        'CA-NS' => 'Nova Scotia',
        'CA-NT' => 'Northwest Territories',
        'CA-NU' => 'Nunavut',
        'CA-ON' => 'Ontario',
        'CA-PE' => 'Prince Edward Island',
        'CA-QC' => 'Quebec',
        'CA-SK' => 'Saskatchewan',
        'CA-YT' => 'Yukon',
        'US-AL' => 'Alabama',
        'US-AK' => 'Alaska',
        'US-AZ' => 'Arizona',
        'US-AR' => 'Arkansas',
        'US-CA' => 'California',
        'US-CO' => 'Colorado',
        'US-CT' => 'Connecticut',
        'US-DE' => 'Delaware',
        'US-DC' => 'District of Columbia',
        'US-FL' => 'Florida',
        'US-GA' => 'Georgia',
        'US-HI' => 'Hawaii',
        'US-ID' => 'Idaho',
        'US-IL' => 'Illinois',
        'US-IN' => 'Indiana',
        'US-IA' => 'Iowa',
        'US-KS' => 'Kansas',
        'US-KY' => 'Kentucky',
        'US-LA' => 'Louisiana',
        'US-ME' => 'Maine',
        'US-MD' => 'Maryland',
        'US-MA' => 'Massachusetts',
        'US-MI' => 'Michigan',
        'US-MN' => 'Minnesota',
        'US-MS' => 'Mississippi',
        'US-MO' => 'Missouri',
        'US-MT' => 'Montana',
        'US-NE' => 'Nebraska',
        'US-NV' => 'Nevada',
        'US-NH' => 'New Hampshire',
        'US-NJ' => 'New Jersey',
        'US-NM' => 'New Mexico',
        'US-NY' => 'New York',
        'US-NC' => 'North Carolina',
        'US-ND' => 'North Dakota',
        'US-OH' => 'Ohio',
        'US-OK' => 'Oklahoma',
        'US-OR' => 'Oregon',
        'US-PA' => 'Pennsylvania',
        'US-RI' => 'Rhode Island',
        'US-SC' => 'South Carolina',
        'US-SD' => 'South Dakota',
        'US-TN' => 'Tennessee',
        'US-TX' => 'Texas',
        'US-UT' => 'Utah',
        'US-VT' => 'Vermont',
        'US-VA' => 'Virginia',
        'US-WA' => 'Washington',
        'US-WV' => 'West Virginia',
        'US-WI' => 'Wisconsin',
        'US-WY' => 'Wyoming',
    );
    
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', 'text', array(
            'constraints' => array(
                new NotBlank()
            ),
            'label' => 'Organization'
        ));
        $builder->add('country', 'choice', array(
            'choices' => $this->countries,
            'constraints' => array(
                new Choice(array(
                    'choices' => $this->getKeys($this->countries),
                    'message' => 'Please choose a country.'
                )),
                new NotBlank(array(
                    'message' => 'Please choose a country.'
                ))
            ),
            'empty_value' => '',
        ));
        $builder->add('state', 'choice', array(
            'choices' => $this->states,
            'constraints' => array(
                new Choice(array(
                    'choices' => $this->getKeys($this->states),
                    'message' => 'Please choose an available state/province.'
                )),
                new NotBlank(array(
                    'message' => 'Please choose a state/province.'
                ))
            ),
            'empty_value' => '',
            'mapped' => false,
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
    
    private function getKeys($array)
    {
        $temp = array();
        foreach ($array as $key => $val) {
            $temp[] = $key;
        }
        
        return $temp;
    }
}