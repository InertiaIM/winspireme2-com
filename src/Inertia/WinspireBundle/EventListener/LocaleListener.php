<?php
namespace Inertia\WinspireBundle\EventListener;

use Maxmind\Bundle\GeoipBundle\Service\GeoipManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LocaleListener implements EventSubscriberInterface
{
    private $geoip;
    
    public function __construct(GeoipManager $geoip)
    {
        $this->geoip = $geoip;
    }
    
    public function onKernelRequest(GetResponseEvent $event)
    {
        // Only necessary to work on the master request
        // Sub-requests will always follow locale of the master
        if ($event->getRequestType() == HttpKernel::MASTER_REQUEST) {
            $request = $event->getRequest();
            
            if (stripos($request->getHttpHost(), '.ca') !== FALSE) {
                $request->setLocale('ca');
                $request->getSession()->set('_locale', $request->getLocale());
                
                return;
            }
            
            
            // If we have a _locale parameter in our URL, it should override
            if ($request->query->has('_locale')) {
                if (strtolower($request->query->get('_locale')) == 'ca') {
                    $request->setLocale('ca');
                }
                else {
                    $request->setLocale('us');
                }
                
                $request->getSession()->set('_locale', $request->getLocale());
                
                return;
            }
            
            
            if (!$request->hasPreviousSession()) {
                // US test IP
//                $ip = '24.199.43.162';
                
                // CA test IP
//                $ip = '23.16.0.1';
                
                $ip = $request->getClientIp();
                
                if ($geo = $this->geoip->lookup($ip)) {
                    $country = strtolower($geo->getCountryCode());
                    if ($country != 'ca') {
                        $country = 'us';
                    }
                }
                else {
                    $country = 'us';
                }
                
                $request->setLocale($country);
                
                // If I'm not on the Canadian domain, but GeoIP reports my IP as Canadian
                // I'll redirect to the Canadian domain
                if (stripos($request->getHttpHost(), '.ca') === FALSE && $country == 'ca') {
                    $event->setResponse(new RedirectResponse('http://winspireme.ca/?_locale=ca', 301));
                }
                
//              return;
            }
            else {
                // try to see if the locale has been set as a _locale routing parameter
                if ($locale = $request->attributes->get('_locale')) {
                    $request->getSession()->set('_locale', $locale);
                } else {
                    // if no explicit locale has been set on this request, use one from the session
                    $request->setLocale($request->getSession()->get('_locale', 'us'));
                }
            }
        }
    }
    
    public static function getSubscribedEvents()
    {
        return array(
            // must be registered before the default Locale listener
            KernelEvents::REQUEST => array(array('onKernelRequest', 17)),
        );
    }
}