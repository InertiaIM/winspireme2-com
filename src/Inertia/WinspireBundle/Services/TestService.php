<?php
namespace Inertia\WinspireBundle\Services;

class TestService
{
    public function __construct()
    {
//        $this->mailer = $mailer;
    }
    
    public function notifications($notifications)
    {
        $dump = fopen('wtf.log', 'w');
        fwrite($dump, serialize($notifications));
        
        return array('Ack' => true);
    }
}