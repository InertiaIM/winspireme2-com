<?php
class WinspirePDF extends TCPDF
{
    public function Header()
    {
        // Logo
        $image_file = __DIR__ . '/../../../../../web/uploads/winspire-logo.svg';
// function ImageSVG($file, $x='', $y='', $w=0, $h=0, $link='', $align='', $palign='', $border=0, $fitonpage=false)
        $this->ImageSVG($image_file, '', '', 180, 0, '', 'T', 'L', 0, false);
        
// Could not produce an EPS file that the library would accept. 
//        $image_file = 'web/uploads/winspire-logo.eps';
//        // function ImageEps($file, $x='', $y='', $w=0, $h=0, $link='', $useBoundingBox=true, $align='', $palign='', $border=0, $fitonpage=false, $fixoutvals=false)
//        $this->ImageEps($image_file, 0, 4, 35, 0, '', false, 'T', 'L', 0, true, true);
    }
    
    public function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('times', '', 8);
        // Page number
        $this->Cell(0, 10, 'DB2/ 23867175.1', 0, false, 'L', 0, '', 0, false, 'T', 'M');
    }
}