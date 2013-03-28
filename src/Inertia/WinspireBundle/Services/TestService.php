<?php
namespace Inertia\WinspireBundle\Services;

class TestService
{
    public function __construct()
    {
        
    }
    
    public function notifications($notifications)
    {
        $dump = fopen('wtf.log', 'a');
        fwrite($dump, print_r($notifications, true));
        
        return array('Ack' => true);
    }
}