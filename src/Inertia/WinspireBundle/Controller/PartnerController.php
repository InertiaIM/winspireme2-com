<?php
namespace Inertia\WinspireBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class PartnerController extends Controller
{
    public function listAction()
    {
        $user = $this->getUser();
        
        $qb = $this->getDoctrine()->getManager()->createQueryBuilder();
        $qb->select(array('s', 'i', 'p'));
        $qb->from('InertiaWinspireBundle:Suitcase', 's');
        $qb->leftJoin('s.items', 'i', 'WITH', 'i.status != \'X\'');
        $qb->leftJoin('i.package', 'p');
        $qb->andWhere('s.sfPartnerId = :partner_id');
        $qb->setParameter('partner_id', $user->getSfId());
        
        $suitcases = $qb->getQuery()->getResult();
        
        return $this->render('InertiaWinspireBundle:Partner:list.html.twig', array(
            'suitcases' => $suitcases,
        ));
    }
}
