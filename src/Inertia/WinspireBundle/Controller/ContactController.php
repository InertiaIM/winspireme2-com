<?php
namespace Inertia\WinspireBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class ContactController extends Controller
{
    public function indexAction(Request $request)
    {
        // TODO refactor into form type?
        $form = $this->get('form.factory')->createNamedBuilder('contact', 'form', array());
        
        $form->add('topic', 'choice',
            array(
                'choices' => array(
                    'new-customer' => 'New Customer',
                    'reserving-experiences' => 'Reserving Experiences',
                    'offering-experiences' => 'Offering Experiences',
                    'event-support' => 'Event Support',
                    'redeeming' => 'Redeeming & Booking Experiences',
                    'payment' => 'Payment & Invoicing',
                    'nonprofit-testimonial' => 'Nonprofit Testimonial',
                    'winning-bidder-testimonial' => 'Winning Bidder Testimonial',
                    'learn-more' => 'Learn More About Winspire',
                    'partners' => 'Partners',
                    'referral' => 'Referral',
                    'other' => 'Other'
                ),
                'constraints' => array(
                    
                ),
                'empty_data' => null,
                'empty_value' => '',
                'label' => 'Please select a topic:',
                'required' => true
            )
        );
        
        $form->add('first', 'text',
            array(
                'constraints' => array(),
                'label' => 'First Name'
            )
        );
        $form->add('last', 'text',
            array(
                'constraints' => array(),
                'label' => 'Last Name'
            )
        );
        $form->add('organization', 'text',
            array(
                'constraints' => array(),
                'label' => 'Organization',
                'required' => false
            )
        );
        $form->add('phone', 'text',
            array(
                'constraints' => array(),
                'label' => 'Phone',
                'required' => false
            )
        );
        $form->add('email', 'text',
            array(
                'constraints' => array(),
                'label' => 'Email',
            )
        );
        $form->add('comments', 'textarea',
            array(
                'constraints' => array(),
                'label' => 'Comments',
                'required' => false
            )
        );
        
        $form = $form->getForm();
        
        if ($request->isMethod('POST')) {
            $form->bind($request);
            if ($form->isValid()) {
                
                $topic = $form->get('topic')->getData();
                $first = $form->get('first')->getData();
                $last = $form->get('last')->getData();
                $organization = $form->get('organization')->getData();
                $phone = $form->get('phone')->getData();
                $email = $form->get('email')->getData();
                $comments = $form->get('comments')->getData();
                
                $msg = array(
                    'topic' => $topic,
                    'first' => $first,
                    'last' => $last,
                    'organization' => $organization,
                    'phone' => $phone,
                    'email' => $email,
                    'comments' => $comments,
                );
                $this->get('old_sound_rabbit_mq.winspire_producer')->publish(serialize($msg), 'contact');
                
                return $this->redirect($this->generateUrl('contactSuccess'));
            }
        }
        
        return $this->render('InertiaWinspireBundle:Contact:index.html.twig', array(
            'form' => $form->createView()
        ));
    }
    
    public function successAction()
    {
        return $this->render('InertiaWinspireBundle:Contact:index.html.twig', array(
            'form' => false
        ));
    }
}
