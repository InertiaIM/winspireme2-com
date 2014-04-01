<?php
namespace Inertia\WinspireBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Core\SecurityContext;

class LoginListener
{
    private $em;
    private $dispatcher;
    private $redirectUrl;
    private $securityContext;
    
    public function __construct(SecurityContext $securityContext, Doctrine $doctrine, EventDispatcher $dispatcher)
    {
        $this->em = $doctrine->getManager();
        $this->dispatcher = $dispatcher;
        $this->redirectUrl = false;
        $this->securityContext = $securityContext;
    }
    
    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event)
    {
        if (($this->securityContext->isGranted('IS_AUTHENTICATED_FULLY') || $this->securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED'))
            && !$this->securityContext->isGranted('ROLE_ADMIN')
        ) {
            
            // user has just logged in
            $userId = $event->getAuthenticationToken()->getUser()->getId();
            $query = $this->em->createQuery(
                'SELECT u FROM InertiaWinspireBundle:User u WHERE u.id = :id'
            )
                ->setParameter('id', $userId)
            ;
            
            $user = $query->getSingleResult();
            
            if ($account = $user->getCompany()) {
                $locale = strtolower($account->getCountry());
                if ($locale == 'ca' && stripos($event->getRequest()->getHttpHost(), '.ca') === FALSE) {
                    // logout the user before redirecting, if we're on the wrong domain
                    $event->getRequest()->getSession()->invalidate();
                    $this->securityContext->setToken(null);
                    $this->redirectUrl = 'http://winspireme.ca/login?error=ca';
                    $this->dispatcher->addListener(KernelEvents::RESPONSE, array($this, 'onKernelResponse'));
                }
                
                if ($locale != 'ca' && stripos($event->getRequest()->getHttpHost(), '.ca') !== FALSE) {
                    // logout the user before redirecting, if we're on the wrong domain
                    $event->getRequest()->getSession()->invalidate();
                    $this->securityContext->setToken(null);
                    $this->redirectUrl = 'http://www.winspireme.com/login?error=us';
                    $this->dispatcher->addListener(KernelEvents::RESPONSE, array($this, 'onKernelResponse'));
                }
            }
        }
    }
    
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if ($this->redirectUrl) {
            $response = new RedirectResponse($this->redirectUrl, 301);
            $event->setResponse($response);
        }
    }
}