<?php
namespace Inertia\WinspireBundle\Consumer;

use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Templating\EngineInterface;

class PackSuitcaseConsumer implements ConsumerInterface
{
    protected $em;
    protected $mailer;
    protected $templating;
    
    public function __construct(EntityManager $entityManager, \Swift_Mailer $mailer, EngineInterface $templating)
    {
        $this->em = $entityManager;
        $this->mailer = $mailer;
        $this->templating = $templating;
    }
    
    public function execute(AMQPMessage $msg)
    {
        $body = unserialize($msg->body);
        $suitcaseId = $body['suitcase_id'];
        $first = $body['first'];
        
        $query = $this->em->createQuery(
            'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WHERE s.id = :id ORDER BY i.updated DESC'
        )->setParameter('id', $suitcaseId);
        
        try {
            $suitcase = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
//            throw $this->createNotFoundException();
        }
        
        $message = \Swift_Message::newInstance()
            ->setSubject('Order Confirmation')
            ->setFrom('notice@winspireme.com')
            ->setTo($suitcase->getUser()->getEmail())
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:order-confirmation.html.twig',
                    array(
                        'suitcase' => $suitcase,
                        'first' => $first
                    )
                ),
                'text/html'
            )
        ;
        
        if($first) {
            $pdf = new \WinspirePDF('P', 'mm', 'LETTER');
            $pdf->SetTopMargin(35);
            $pdf->SetLeftMargin(24);
            $pdf->SetRightMargin(24);
            $pdf->SetFontSize(11);
            
            $tagvs = array(
                'li' => array(
                    1 => array(
                        'n' => 1
                    )
                )
            );
            $pdf->setHtmlVSpace($tagvs);
            
            $pdf->addPage();
            $html = '
<style>
    a {
        color:#0578c8;
    }
</style>
<p><span style="text-decoration:underline;">' . $suitcase->getUser()->getCompany()->getName() . '</span></p>
<p><span style="text-decoration:underline;">' . date('F jS, Y') . '</span></p>
<p>Dear <span style="text-decoration:underline;">' . $suitcase->getUser()->getFirstName() . ' ' . $suitcase->getUser()->getLastName() . '</span>,</p>
<p>This agreement (this “Event Agreement”) is between Winspire Inc. and <span style="text-decoration:underline;">' . $suitcase->getUser()->getCompany()->getName() . '</span> (the “Nonprofit”) for using Winspire No-risk auction items in your <span style="text-decoration:underline;">' . date_format($suitcase->getEventDate(), 'F jS, Y') . '</span> fundraising event. This Event Agreement supplements the Website Agreement and Terms of Use, to which the Nonprofit already has agreed by using Winspire’s website at <a href="http://www.winspireme.com">www.winspireme.com</a> and creating an account.</p>
<p>Within this document, Winspire has provided access to the necessary promotional materials for each of the experiences the Nonprofit intends to use, including full experience description, full- color display sheet and a promotional image. <strong>All text details of each experience must be used exactly as provided.</strong> Winspire assumes no responsibility for any changes made by the Nonprofit to the text details of any experience. Furthermore, all auction items are subject to this Event Agreement. Winspire recommends that the Nonprofit provide this Event Agreement to its winning bidders.</p>
<p>Winspire experiences are offered on a no-risk basis, meaning that the Nonprofit purchases these item(s) only after knowing what is sold at the event. If an experience does not successfully sell, the Nonprofit is not required to purchase the item(s). Prior to placing an order, the Nonprofit will be asked to confirm the receipt of payment from the winning bidder. <strong>All experiences that are confirmed by the Nonprofit organization and invoiced by Winspire are non-refundable.</strong></p>
<p>The purchase of most Winspire experiences includes a complimentary booking service, allowing the winning bidder to work directly with Winspire to book each of the components of the purchased experience. To facilitate this service, it is necessary for the Nonprofit to provide the name, telephone number and email address of each winning bidder when placing the order. <strong>All Winspire experiences must be booked a minimum of 60 days in advance.</strong></p>
<p>Many Winspire experiences require the winning bidder to present hard-copy certificates at check in. These certificates will be sent directly to the travelers once the trip details have been finalized. These certificates must be treated the “same as cash”. <strong>Winspire can not guarantee that lost certificates can/will be replaced.</strong> Airfare expires one year from the date of invoice. Any airport departure taxes, fees or fuel surcharges (if charged) are the responsibility of the purchaser. In the unlikely event of an ownership change to one of the included hotels, Winspire will use its best effort to find comparable accommodations for that experience.</p>
<p>Payment will be made <strong>only in U.S. dollars</strong> and is due, in full, within 30 days from date of original invoice. Any monies owed to Winspire are subject to a late fee of 1.5% per month after being more than 30 days past due. If Winspire has not received payment for invoiced experiences within 60 days of original invoice date, the order will be cancelled and the Nonprofit will be charged a 10% restocking fee. If cancelled, the Nonprofit will be given the opportunity to reorder the experience(s), but at the prevailing price at the time of the reorder. Winspire accepts checks and all major credit cards. Should the Nonprofit choose to pay with a credit card, there will be a 3% processing fee added to the transaction amount.</p>
<p>Winspire reserves the right to change pricing on any experience as long as the Nonprofit’s event is 30 days or more out. Experience prices and text details are subject to change if order is not invoiced within 30 days of the event date.</p>
<p>There is a $19.95 processing fee applied to each invoice to cover the cost of shipping the final hard-copy certificates directly to the winning bidder(s) via Federal Express or similar express carrier.</p>
<h4>SUGGESTED RETAIL VALUE:</h4>
<p>Winspire recommends that the explanations provided below (in italics), regarding Suggested Retail Value, be included in any program rules and/or with any documentation that the Nonprofit provides to the winning bidder(s) for the experiences being used at the event.</p>
<hr/>
<p>&nbsp;</p>
<p><em>All suggested retail values are based on the following:</em></p>
<p><em><strong>American Airlines:</strong> Peak round-trip rates during premium travel season and participating cities are used for the 48 contiguous United States, Hawaii, Canada, Caribbean, Mexico, Central America, South America, Europe, Pacific (Asia, Australia, South Pacific Islands).</em></p>
<p><em><strong>Other Airlines:</strong> Tariff rates are utilized. One-way fares are utilized if two different cities are featured on the departing and returning flights.</em></p>
<p><em><strong>Hotels and Tours:</strong> Hotel’s published “Rack Rate” plus all applicable sales tax.</em></p>
<p><em><strong>Packages:</strong> Each component of a package is priced separately to determine the total suggested retail value.</em></p>
<p><em>All rates are based on the high season that the gift certificate is eligible for redemption.</em></p>
<p><em><strong>Cruises:</strong> Brochure rate for the cabin plus airfare add-ons offered by the cruise line in the brochure.</em></p>
<p><em><strong>Ground Transportation:</strong> Unless specifically stated otherwise, no ground transportation is included in any package. The winning bidders are required to secure and pay for ground transportation from airports to hotels, airports to cruise ships, hotels to include attractions or tours, etc.</em></p>
<hr/>
<p>&nbsp;</p>
<h4>RESERVED EXPERIENCES:</h4>
<p><span style="text-decoration:underline;">' . $suitcase->getUser()->getCompany()->getName() . '</span> plans to use the following experiences in its <span style="text-decoration:underline;">' . date_format($suitcase->getEventDate(), 'F jS, Y') . '</span> event.<br/>
(Click on the "Content files link" for each experience to view and download detailed descriptions as well as promotional pieces. Please note, experience description details are subject to change and should be downloaded or reviewed again just prior to usage in your event.)</p>
<p><strong>In order to increase your fundraising revenue potential;</strong></p>
<h4>ALL WINSPIRE EXPERIENCES CAN BE SOLD MULTIPLE TIMES.
<br/>FEEL FREE TO SELL THEM AS MANY TIMES AS POSSIBLE.</h4>
<p><strong>Additional terms and conditions:</strong></p>
<ol>
    <li>Disclaimer. Winspire is generally only a reseller of third party experiences and for products and/or services, and Winspire cannot be responsible for the business or activities of those third parties, including, without limitation, whether those parties are in compliance with law or whether the experience descriptions supplied by such third parties are accurate or complete.</li>
    <li>Pricing; Payment. Nonprofit understands the calculation for the Suggested Retail Value of Winspire experiences. Nonprofit understands that invoices must be paid within 30 days or the package will be subject to a 1.5% per month late fee. If a payment is not made within 60 days, a 10% restocking fee will apply. Once invoiced, Winspire travel experiences are non-returnable and non-refundable.</li>
    <li>Nonprofit’s Obligations. Nonprofit agrees to provide the name, telephone number and email address of each winning bidder upon confirmation of order. Nonprofit agrees not to change any description of the experience as supplied in the various documents provided by Winspire, and hereby represents and warrants to Nonprofit that the experience description has not been altered from that provided by the vendor.</li>
    <li>Compliance With Law. The third party providers of products and services are solely responsible for ensuring that the products and services are in compliance with all federal, state and local laws, and Winspire is not liable in any way for noncompliance with applicable laws by the provider. If a winning bidder needs accommodation for a disability, he or she is responsible for notifying the third party provider of the facility or service so that such third party can offer alternatives as appropriate, especially for facilities or events that may sell out. Winspire will assist winning bidders in so notifying the third party provider but is not liable for compliance with the Americans with Disabilities Act or other state or local laws regarding public accommodation or ticket sales.</li>
    <li>Disclaimer of Representations and Warranties. Nonprofit agrees and acknowledges that (other than the as specifically set forth above) Winspire makes no representations or warranties regarding the experience or components thereof. Winspire’s sole obligations under this Agreement are (i) to reproduce the item description exactly as provided by the vendor, and (ii) to deliver the item certificate to the winning bidder at the address provided by the Nonprofit. OTHER THAN AS SPECIFICALLY SET FORTH HEREIN, WINSPIRE MAKES NO OTHER WARRANTIES EXPRESS OR IMPLIED, AS TO ANY OTHER MATTER WHATSOEVER, INCLUDING, WITHOUT LIMITATION, THE CONDITION OF EXPERIENCES OR SERVICES PROVIDED HEREUNDER, AND WINSPIRE SPECIFICALLY DISCLAIMS ANY IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR ANY PARTICULAR PURPOSE OR NEED, AND ANY WARRANTIES THAT MAY ARISE FROM COURSE OF DEALING, COURSE OF PERFORMANCE OR USAGE OF TRADE.</li>
    <li>Indemnification. Nonprofit agrees to hold harmless, indemnify and defend Winspire against any claims made by any third party bidders, vendors or others for damages or other claims related to the experiences or the terms and conditions applicable thereto.</li>
    <li>Limitation of Liability. Winspire’s liability under this agreement shall be limited to confirmed delivery of the certificate and Winspire shall not be liable for any consequential, special or punitive damages. Winspire’s total aggregate liability for any claim under this Agreement shall not exceed the amount paid or owed by the winning bidder for the experience to which the claim relates.</li>
    <li>Email Communications. Nonprofit agrees that Winspire may from time to time send emails to Nonprofit containing such items as: company newsletters, new experience introductions, product promotions and other marketing related documents and tools. To opt out of future mailings, please use the “opt out” feature provided at the bottom of any such mailing and the email address will be removed from our database.</li>
</ol>
<hr/>
<p>&nbsp;</p>
<p>By clicking the “Accept” button for online Event Agreements, the Nonprofit hereby acknowledges and agrees to the terms of this Event Agreement set forth above.</p>
<p>Agreed to on <strong><span style="text-decoration:underline;">' . date('c') . '</span></strong></p>
<p>By:</p>
<table><tr><td width="40"></td><td>' 
        . $suitcase->getUser()->getFirstName() . ' ' . $suitcase->getUser()->getLastName() . '<br/>'
        . $suitcase->getUser()->getCompany()->getName() . '<br/>'
        . $suitcase->getUser()->getEmail() . '<br/>'
        . $suitcase->getUser()->getPhone() . 
'</td></tr></table>
';
            
            // output the HTML content
            $pdf->writeHTML($html, true, false, true, false, '');
            
            $data = $pdf->Output('', 'S');
            
            $attachment = \Swift_Attachment::newInstance($data, 'Letter of Agreement.pdf', 'application/pdf');
            $message->attach($attachment);
        }
        
        $this->em->clear();
        
        if (!$this->mailer->send($message)) {
            // Any other value not equal to false will acknowledge the message and remove it
            // from the queue
            return false;
        }
        
        return true;
    }
}