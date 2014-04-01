<?php
namespace Inertia\WinspireBundle\Services;

use Doctrine\ORM\EntityManager;
use Inertia\WinspireBundle\Entity\Booking;
use Inertia\WinspireBundle\Entity\SuitcaseItem;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\SecurityContext;

class SuitcaseManager
{
    protected $em;
    protected $invoiceDir;
    protected $invoiceFee;
    protected $producer;
    protected $sc;
    protected $session;
    protected $suitcaseUser;
    
    public function __construct(EntityManager $em, Session $session, SecurityContext $sc, Producer $producer, $dir, $fee)
    {
        $this->em = $em;
        $this->invoiceDir = $dir;
        $this->invoiceFee = $fee;
        $this->producer = $producer;
        $this->sc = $sc;
        $this->session = $session;
        
        // In cases where we don't have a valid security context
        // TODO determine best method for handling this within an "offline" service
        if (!$this->sc->getToken()) {
            $this->suitcaseUser = false;
        }
        else {
            $this->suitcaseUser = $this->sc->getToken()->getUser();
            
            
            // Admin users should have the $user replaced by their customer.
            // So we'll first query for the latest Suitcase the salesperson
            // was working with, and make that the user.
            // TODO for better performance, we should store the user in the admin session?
            if ($this->sc->isGranted('ROLE_ADMIN')) {
                $uid = $this->session->get('uid');
                
                $qb = $this->em->createQueryBuilder();
                $qb->select(array('u'));
                $qb->from('InertiaWinspireBundle:User', 'u');
                $qb->andWhere('u.id = :id');
                $qb->setParameter('id', $uid);
                try {
                    $user = $qb->getQuery()->getSingleResult();
                    $this->suitcaseUser = $user;
                }
                catch (\Doctrine\Orm\NoResultException $e) {
                    // Can't find a user to work with; so we're done...
                    $this->suitcaseUser = false;
                }
            }
        }
    }
    
    
    public function addToSuitcase($suitcase, $packageId)
    {
        // First test whether this package is already in the Suitcase
        $query = $this->em->createQuery(
            'SELECT i FROM InertiaWinspireBundle:SuitcaseItem i WHERE i.suitcase = :suitcase_id AND i.package = :package_id AND i.status != \'X\''
        )
        ->setParameter('suitcase_id', $suitcase->getId())
        ->setParameter('package_id', $packageId)
        ;
        
        $items = $query->getResult();
        if (count($items) > 0) {
            return false;
        }
        
        
        // Second step is to check whether this package is "private" (only available to admins)
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('p'));
        $qb->from('InertiaWinspireBundle:Package', 'p');
        $qb->where('p.id = :package_id');
        $qb->setParameter('package_id', $packageId);
        
        if (!$this->sc->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('p.is_private != 1');
        }
        
        $packages = $qb->getQuery()->getResult();
        if (count($packages) < 1) {
            return false;
        }
        else {
            $package = $packages[0];
        }
        
        $suitcaseItem = new SuitcaseItem();
        $suitcaseItem->setPackage($package);
        $suitcaseItem->setQuantity(0);
        $suitcaseItem->setPrice(0);
        $suitcaseItem->setCost(0);
        $suitcaseItem->setSubtotal(0);
        
        if ($this->sc->isGranted('ROLE_ADMIN')) {
            $suitcaseItem->setStatus('R');
        }
        else {
            $suitcaseItem->setStatus('M');
        }
        $this->em->persist($suitcaseItem);
        
        $suitcase->addItem($suitcaseItem);
        if($suitcase->getStatus() != 'U') {
            // reopen suitcase and trigger reminder message
            $suitcase->setStatus('U');
            $suitcase->setUnpackedAt(new \DateTime());
            $suitcase->setDirty(true);
            
            $msg = array('suitcase_id' => $suitcase->getId());
            $this->producer->publish(serialize($msg), 'unpack-suitcase');
        }
        $suitcase->setUpdated($suitcaseItem->getUpdated());
        
        $this->em->persist($suitcase);
        $this->em->flush();
        
        $msg = array('id' => $suitcase->getId(), 'type' => 'suitcase-items');
        $this->producer->publish(serialize($msg), 'update-sf');
        
        return $package;
    }
    
    
    public function deleteFromSuitcase($suitcase, $packageId)
    {
        $items = $suitcase->getItems();
        $deleted = false;
        foreach($items as $item) {
            if($packageId == $item->getPackage()->getId()) {
                $item->setStatus('X');
                $suitcase->setUpdated(new \DateTime());
                
                if($suitcase->getStatus() == 'P') {
                    // reopen suitcase and trigger reminder message
                    $suitcase->setStatus('U');
                    $suitcase->setUnpackedAt(new \DateTime());
                    $suitcase->setDirty(true);
                    
                    $msg = array('suitcase_id' => $suitcase->getId());
                    $this->producer->publish(serialize($msg), 'unpack-suitcase');
                }
                
                $this->em->persist($item);
                $this->em->persist($suitcase);
                $this->em->flush();
                
                $deleted = true;
            }
        }
        
        $msg = array('id' => $suitcase->getId(), 'type' => 'suitcase-items');
        $this->producer->publish(serialize($msg), 'update-sf');
        
        return $deleted;
    }
    
    
    public function flagSuitcaseItem($suitcase, $packageId)
    {
        $items = $suitcase->getItems();
        $newStatus = false;
        $counts = array('M' => 0, 'D' => 0, 'R' => 0, 'E' => 0);
        foreach($items as $item) {
            $counts[$item->getStatus()]++;
            
            if($packageId == $item->getPackage()->getId()) {
                switch($item->getStatus()) {
                    case 'M':
                        $item->setStatus('D');
                        $newStatus = 'D';
                        $counts['M']--;
                        $counts['D']++;
                        break;
                    case 'D':
                        $item->setStatus('M');
                        $newStatus = 'M';
                        $counts['D']--;
                        $counts['M']++;
                        break;
                    case 'R':
                        $item->setStatus('E');
                        $newStatus = 'E';
                        $counts['R']--;
                        $counts['E']++;
                        break;
                    case 'E':
                        $item->setStatus('R');
                        $newStatus = 'R';
                        $counts['E']--;
                        $counts['R']++;
                        break;
                }
                
                $this->em->persist($item);
                $this->em->flush();
            }
        }
        
        return array('status' => $newStatus, 'counts' => $counts);
    }
    
    
    public function flagSuitcaseItems($suitcase, $ids)
    {
        $items = $suitcase->getItems();
        
        foreach($ids as $element) {
            foreach($items as $item) {
                if($element['id'] == $item->getPackage()->getId()) {
                    $item->setStatus($element['status']);
                    $this->em->persist($item);
                }
            }
        }
        $this->em->flush();
        
        return $this->getCounts($suitcase);
    }
    
    
    public function generateLoa($suitcase)
    {
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
<p><span style="text-decoration:underline;">' . $suitcase->getPackedAt()->format('F jS, Y') . '</span></p>
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
<p><span style="text-decoration:underline;">' . $suitcase->getUser()->getCompany()->getName() . '</span> plans to use the experiences listed in the Reservation Confirmation Email in its <span style="text-decoration:underline;">' . date_format($suitcase->getEventDate(), 'F jS, Y') . '</span> event.<br/>
(The Reservation Confirmation Email contains links to download promotional pieces for each experience, including detailed package description, a display PDF and additional images. Please note, experience description details are subject to change and should be downloaded or reviewed again just prior to usage in the event.)</p>
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
<p>Agreed to on <strong><span style="text-decoration:underline;">' . $suitcase->getPackedAt()->format('c') . '</span></strong></p>
<p>By:</p>
<table><tr><td width="40"></td><td>'
            . $suitcase->getUser()->getFirstName() . ' ' . $suitcase->getUser()->getLastName() . '<br/>'
            . $suitcase->getUser()->getCompany()->getName() . '<br/>'
            . $suitcase->getUser()->getEmail() . '<br/>'
            . $suitcase->getUser()->getPhone()
            . '</td></tr></table>';
        
        // output the HTML content
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $data = $pdf->Output('', 'S');
        
        return $data;
    }
    
    
    public function getCounts($suitcase)
    {
        $counts = array('M' => 0, 'D' => 0, 'R' => 0, 'E' => 0);
        foreach($suitcase->getItems() as $item) {
            if ($item->getStatus() != 'X') {
                $counts[$item->getStatus()]++;
            }
        }
        
        return $counts;
    }
    
    
    public function getSuitcase($active = true, $order = null)
    {
        $sid = $this->session->get('sid');
         
        if (!$this->suitcaseUser) {
            return false;
        }
        
        $suitcase = $this->querySuitcase($this->suitcaseUser, $active, $order, $sid);
        if ($suitcase != 'new') {
            $this->session->set('sid', $suitcase->getId());
            return $suitcase;
        }
        else {
            $this->session->set('sid', 'new');
            return 'new';
        }
    }
    
    public function getSuitcaseList($active = true, $order = 'name')
    {
        $suitcaseList = array();
        
        if ($this->suitcaseUser) {
            $qb = $this->em->createQueryBuilder();
            $qb->select(array('s'));
            $qb->from('InertiaWinspireBundle:Suitcase', 's');
            
            // If "active", we only want packed or unpacked Suitcases
            if ($active) {
                $qb->where($qb->expr()->in('s.status', array('U', 'P')));
            }
            else {
                $qb->where($qb->expr()->notIn('s.status', array('M')));
            }
            
            $qb->andWhere('s.user = :user_id');
            $qb->setParameter('user_id', $this->suitcaseUser->getId());

            switch ($order) {
                case 'name':
                    $qb->orderBy('s.name', 'ASC');
                    break;
                case 'date':
                    $qb->orderBy('s.eventDate', 'DESC');
                    break;
            }

            $suitcases = $qb->getQuery()->getResult();
            
            foreach($suitcases as $s) {
                switch ($s->getStatus()) {
                    case 'U':
                        $status = 'Unpacked';
                        break;
                    case 'P':
                        $status = 'Packed';
                        if ($s->getEventDate() < (new \DateTime())) {
                            $status = 'Request Invoice';
                        }
                        break;
                    case 'I':
                        $status = 'Not Paid';
                        break;
                    case 'R':
                        $status = 'Invoice Pending';
                        break;
                    case 'A':
                        $status = 'Send Vouchers';
                        break;
                }

                $suitcaseList[] = array(
                    'id' => $s->getId(),
                    'name' => $s->getEventName(),
                    'date' => $s->getEventDate(),
                    'status' => $status,
                    'raw' => strtolower($s->getStatus()),
                );
            }
        }
        
        return $suitcaseList;
    }
    
    
    public function getInvoiceFee()
    {
        return $this->invoiceFee;
    }
    
    
    public function getInvoiceFile($id)
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('s'));
        $qb->from('InertiaWinspireBundle:Suitcase', 's');
        $qb->where('s.id = :id');
        $qb->setParameter('id', $id);
        $qb->andWhere('s.invoiceFileName != \'\'');
        
        if (!$this->sc->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('s.user = :user');
            $qb->setParameter('user', $this->suitcaseUser);
        }
        
        try {
            $suitcase = $qb->getQuery()->getSingleResult();
            $filename = $this->invoiceDir . $suitcase->getSfId() . '/' . $suitcase->getInvoiceFileName();
            if (is_file($filename)) {
                $fp = fopen($filename, 'r');
                $contents = fread($fp, filesize($filename));
                fclose($fp);
                return array(
                    'filename' => $suitcase->getInvoiceFileName(),
                    'contents' => $contents
                );
            }
            else {
                return false;
            }
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            return false;
        }
    }
    
    
    public function packSuitcase($suitcase)
    {
        
    }
    
    
    public function requestInvoice($qtys)
    {
        $ids = array_keys($qtys);
        
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('s', 'i', 'p'));
        $qb->from('InertiaWinspireBundle:SuitcaseItem', 'i');
        $qb->join('i.suitcase', 's');
        $qb->join('i.package', 'p');
        $qb->where('i.status != \'X\'');
        $qb->andWhere($qb->expr()->in('i.id', ':ids'));
        $qb->setParameter('ids', $ids);
        
        if (!$this->sc->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('s.user = :user');
            $qb->setParameter('user', $this->suitcaseUser);
        }
        
        $items = $qb->getQuery()->getResult();
        $invoiceSubtotal = 0;
        foreach ($items as $item) {
            if (isset($qtys[$item->getId()])) {
                $item->setQuantity($qtys[$item->getId()]);
                $item->setCost($item->getPackage()->getCost());
                $item->setSubtotal($item->getQuantity() * $item->getCost());
                $invoiceSubtotal += $item->getSubtotal();
                $this->em->persist($item);
                
                for ($i=0; $i<$item->getQuantity(); $i++) {
                    $booking = new Booking();
                    $booking->setVoucherSent(false);
                    $booking->setDirty(false);
                    $item->addBooking($booking);
                    $this->em->persist($booking);
                }
            }
        }
        
        if (count($items) > 0) {
            $suitcase = $items[0]->getSuitcase();
            
            if ($invoiceSubtotal > 0) {
                $suitcase->setFee($this->invoiceFee);
                $suitcase->setStatus('R');
            }
            else {
                $suitcase->setFee(0);
                $suitcase->setStatus('M');
            }
            
            $suitcase->setDirty(true);
            $suitcase->setInvoiceRequestedAt(new \DateTime());
            $this->em->persist($suitcase);
            $this->em->flush();
            
            $msg = array('suitcase_id' => $suitcase->getId());
            $this->producer->publish(serialize($msg), 'invoice-request');
            
            return $suitcase;
        }
        else {
            return false;
        }
    }
    
    
    public function previewVoucher($voucher)
    {
        if (!isset($voucher['id'])) {
            return false;
        }
//print_r($voucher['id']); exit;
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('b', 'i', 's'));
        $qb->from('InertiaWinspireBundle:Booking', 'b');
        $qb->join('b.suitcaseItem', 'i');
        $qb->join('i.suitcase', 's');
        $qb->where('i.status != \'X\'');
        $qb->andWhere('b.id = :id');
        $qb->andWhere('s.status = \'A\'');
        $qb->setParameter('id', $voucher['id']);
        
        if (!$this->sc->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('s.user = :user');
            $qb->setParameter('user', $this->suitcaseUser);
        }
        
        
        
        
        try {
            $booking = $qb->getQuery()->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            return false;
        }
        
        $suitcase = $booking->getSuitcaseItem()->getSuitcase();
        // Query for appropriate Content Pack Version
        $query = $this->em->createQuery(
            'SELECT c, v FROM InertiaWinspireBundle:ContentPack c JOIN c.versions v WHERE c.sfId = :id AND v.created <= :date ORDER BY v.created DESC'
        )
            ->setParameter('id', $booking->getSuitcaseItem()->getPackage()->getSfContentPackId())
            ->setParameter('date', $suitcase->getEventDate())
        ;
        
        $query->setMaxResults(1);
        
        try {
            $contentPack = $query->getSingleResult();
            $contentPackVersions = $contentPack->getVersions();
            $contentPackVersion = $contentPackVersions[0];
            $contentPackVersionId = $contentPackVersion->getId();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            $contentPackVersionId = false;
        }
        
        return array('booking' => $booking, 'content_pack_version_id' => $contentPackVersionId);
    }
    
    
    public function sendVoucher($voucher)
    {
        if (!isset($voucher['id'])) {
            return 0;
        }
        
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('b', 'i', 's'));
        $qb->from('InertiaWinspireBundle:Booking', 'b');
        $qb->join('b.suitcaseItem', 'i');
        $qb->join('i.suitcase', 's');
        $qb->where('i.status != \'X\'');
        $qb->andWhere('b.id = :id');
        $qb->andWhere('s.status = \'A\'');
        $qb->setParameter('id', $voucher['id']);
        
        if (!$this->sc->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('s.user = :user');
            $qb->setParameter('user', $this->suitcaseUser);
        }
        
        try {
            $booking = $qb->getQuery()->getSingleResult();
            $booking->setVoucherSent(true);
            $booking->setVoucherSentAt(new \DateTime());
            $this->em->persist($booking);
            $this->em->flush();
            $count = 1;
            
            $msg = array('booking_id' => $booking->getId(), 'cc' => isset($voucher['cc']) ? true : false, 'message' => $voucher['message']);
            $this->producer->publish(serialize($msg), 'send-voucher');
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            $count = 0;
        }
        
        return $count;
    }
    
    
    public function updatePrice($id, $price)
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('i'));
        $qb->from('InertiaWinspireBundle:SuitcaseItem', 'i');
        $qb->join('i.suitcase', 's');
        $qb->where('i.status != \'X\'');
        $qb->andWhere('i.id = :id');
        $qb->setParameter('id', $id);
        
        if (!$this->sc->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('s.user = :user');
            $qb->setParameter('user', $this->suitcaseUser);
        }
        
        try {
            $item = $qb->getQuery()->getSingleResult();
            $item->setPrice($price);
            $preUpdateAt = $item->getUpdated();
            
            $this->em->persist($item);
            $this->em->flush();
            
            if ($preUpdateAt != $item->getUpdated()) {
                $this->em->persist($item);
                $this->em->flush();
                
                $msg = array('item_id' => $item->getId());
                $this->producer->publish(serialize($msg), 'price-update');
            }
            
            $count = 1;
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            $count = 0;
        }
        
        return $count;
    }
    
    
    public function updateSuitcaseBooking($id, $request)
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('i', 'b'));
        $qb->from('InertiaWinspireBundle:Booking', 'b');
        $qb->join('b.suitcaseItem', 'i');
        $qb->join('i.suitcase', 's');
        $qb->where('i.status != \'X\'');
        $qb->andWhere('b.id = :id');
        $qb->andWhere('b.voucherSent != true');
        $qb->setParameter('id', $id);
        
        if (!$this->sc->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('s.user = :user');
            $qb->setParameter('user', $this->suitcaseUser);
        }
        
        try { 
            $booking = $qb->getQuery()->getSingleResult();
            $booking->setFirstName($request['first']);
            $booking->setLastName($request['last']);
            $booking->setPhone($request['phone']);
            $booking->setEmail($request['email']);
            $preUpdateAt = $booking->getUpdated();
            
            $this->em->persist($booking);
            $this->em->flush();
            
            if ($preUpdateAt != $booking->getUpdated()) {
                $booking->setDirty(true);
                $this->em->persist($booking);
                $this->em->flush();
                $msg = array('booking_id' => $booking->getId());
                $this->producer->publish(serialize($msg), 'booking-update');
            }
            
            $count = 1;
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            $count = 0;
        }
        
        return $count;
    }
    
    
    public function updateSuitcaseQty($id, $qty)
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('i', 'p'));
        $qb->from('InertiaWinspireBundle:SuitcaseItem', 'i');
        $qb->join('i.suitcase', 's');
        $qb->join('i.package', 'p');
        $qb->where('i.status != \'X\'');
        $qb->andWhere('i.id = :id');
        $qb->setParameter('id', $id);
        
        if (!$this->sc->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('s.user = :user');
            $qb->setParameter('user', $this->suitcaseUser);
        }
        
        try {
            $item = $qb->getQuery()->getSingleResult();
            $item->setCost($item->getPackage()->getCost());
            $item->setQuantity($qty);
            $item->setSubTotal($item->getCost() * $item->getQuantity());
            $this->em->persist($item);
            $this->em->flush();
            $count = 1;
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            $count = 0;
        }
        
        return $count;
    }
    
    
    protected function querySuitcase($user, $active = true, $order = null, $sid = null)
    {
        if (!$this->sc->isGranted('ROLE_USER')) {
            return "new";
        }
        
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('s', 'i', 'p'));
        $qb->from('InertiaWinspireBundle:Suitcase', 's');
        $qb->leftJoin('s.items', 'i', 'WITH', 'i.status != \'X\'');
        $qb->leftJoin('i.package', 'p');
        
        // If "active", we only want packed or unpacked Suitcases
        if ($active) {
            $qb->where($qb->expr()->in('s.status', array('U', 'P')));
        }
        
        $qb->andWhere('s.user = :user_id');
        $qb->setParameter('user_id', $user->getId());
        
        if ($sid) {
            $qb->andWhere('s.id = :id');
            $qb->setParameter('id', $sid);
        }
        
        $qb->orderBy('s.updated', 'DESC');
        
        // Set the sort order based on our "order" parameter
        if ($order == 'update') {
            $qb->addOrderBy('i.updated', 'DESC');
        }
        else {
            $qb->addOrderBy('p.parent_header', 'ASC');
        }
        
        
        $suitcases = $qb->getQuery()->getResult();
        if (count($suitcases) == 0) {
            if ($sid) {
                $suitcase = $this->querySuitcase($user, $active, $order);
            }
            else {
                $suitcase = 'new';
            }
        }
        else {
            $suitcase = $suitcases[0];
        }
        
        
        return $suitcase;
    }
}
