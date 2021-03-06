<?php

namespace AppBundle\Services;

use AppBundle\Entity\Claim;
use Doctrine\ORM\Query\Expr;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Validator\Context\ExecutionContext;
use AppBundle\Services\ClaimRule;

class HrRule extends ClaimRule
{
    public function getListClaimPeriodForFilterHr()
    {
        $expr = new Expr();
        $company = $this->getCompany();
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder('claim');
        $qb->select('claim');
        $qb->from('AppBundle:Claim', 'claim');
        $qb->where($expr->eq('claim.company', ':company'));
        $qb->andWhere($expr->eq('claim.status', ':statusApproverApproved'));
        $qb->orderBy('claim.createdAt', 'DESC');
        $qb->setParameter('statusApproverApproved', Claim::STATUS_APPROVER_APPROVED);
        $qb->setParameter('company', $company);
        $claims = $qb->getQuery()->getResult();

        $listPeriod = ['Show All' => 'all'];
        $from = $this->getCurrentClaimPeriod('from');
        $to = $this->getCurrentClaimPeriod('to');
        $listPeriod[$from->format('d M Y') . ' - ' . $to->format('d M Y')] = $from->format('Y-m-d');
        foreach ($claims as $claim) {
            $listPeriod[$claim->getPeriodFrom()->format('d M Y') . ' - ' . $claim->getPeriodTo()->format('d M Y')] = $claim->getPeriodFrom()->format('Y-m-d');
        }
        return $listPeriod;
    }

    public function getListClaimPeriodForFilterHrReport($reverse = 0)
    {
        $expr = new Expr();
        $company = $this->getCompany();
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder('claim');
        $qb->select('claim');
        $qb->from('AppBundle:Claim', 'claim');
        $qb->where($expr->eq('claim.company', ':company'));
        $qb->andWhere($expr->eq('claim.status', ':statusProcessed'));
        $qb->orderBy('claim.createdAt', 'DESC');
        $qb->setParameter('statusProcessed', Claim::STATUS_PROCESSED);
        $qb->setParameter('company', $company);
        $claims = $qb->getQuery()->getResult();

        if ($reverse == 0) {
            $listPeriod = ['all' => 'Show All'];
            $from = $this->getCurrentClaimPeriod('from');
            $to = $this->getCurrentClaimPeriod('to');
            $listPeriod[$from->format('Y-m-d')] = $from->format('d M Y') . ' - ' . $to->format('d M Y');
            foreach ($claims as $claim) {
                $listPeriod[$claim->getPeriodFrom()->format('Y-m-d')] = $claim->getPeriodFrom()->format('d M Y') . ' - ' . $claim->getPeriodTo()->format('d M Y');
            }
        } else {
            $listPeriod = ['Show All' => 'all'];
            $from = $this->getCurrentClaimPeriod('from');
            $to = $this->getCurrentClaimPeriod('to');
            $listPeriod[$from->format('d M Y') . ' - ' . $to->format('d M Y')] = $from->format('Y-m-d');
            foreach ($claims as $claim) {
                $listPeriod[$claim->getPeriodFrom()->format('d M Y') . ' - ' . $claim->getPeriodTo()->format('d M Y')] = $claim->getPeriodFrom()->format('Y-m-d');
            }
        }
        return $listPeriod;
    }


    public function getTotalAmountClaimEachEmployeeForHr($position,$from)
    {
        $expr = new Expr();
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder('claim');
        $qb->select('SUM(claim.claimAmountConverted)');
        $qb->from('AppBundle:Claim', 'claim');
        $qb->where('claim.position = :position');
        $qb->andWhere($expr->eq('claim.status', ':statusApproverApproved'));
        $qb->setParameter('statusApproverApproved', Claim::STATUS_APPROVER_APPROVED);
        $qb->setParameter('position', $position);
        if ($from !='all') {
            $dateFilter = new  \DateTime($from);
            $qb->andWhere($expr->eq('claim.periodFrom', ':periodFrom'));
            $qb->setParameter('periodFrom', $dateFilter->format('Y-m-d'));
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getTotalAmountClaimEachEmployeeForHrReport($position,$from)
    {

        $expr = new Expr();
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder('claim');
        $qb->select('SUM(claim.claimAmountConverted)');
        $qb->from('AppBundle:Claim', 'claim');
        $qb->where('claim.position = :position');
        $qb->andWhere($expr->eq('claim.status', ':statusHrApproved'));
        $qb->setParameter('statusHrApproved', Claim::STATUS_PROCESSED);
        $qb->setParameter('position', $position);
        if ($from !='all') {
            $dateFilter = new  \DateTime($from);
            $qb->andWhere($expr->eq('claim.periodFrom', ':periodFrom'));
            $qb->setParameter('periodFrom', $dateFilter->format('Y-m-d'));
        }

        return $qb->getQuery()->getSingleScalarResult();
    }


    public function getDataForPayMaster($from)
    {
        $em = $this->container->get('doctrine')->getManager();
        $expr = new Expr();
        $company = $this->getCompany();
        $qb = $em->createQueryBuilder('position');
        $qb->select('position');
        $qb->from('AppBundle:Position', 'position');
        $qb->leftJoin('position.claims', 'claim');
        $qb->leftJoin('position.company', 'company');
        $qb->andWhere(
            $expr->eq('company', ':company')
        );
        $qb->andWhere($expr->eq('claim.status', ':statusProcessed'));
        $qb->setParameter('statusProcessed', Claim::STATUS_PROCESSED);
        $qb->setParameter('company', $company);
        if ($from != 'none') {
            $qb->andWhere($expr->eq('claim.periodFrom', ':periodFrom'));
            $qb->setParameter('periodFrom', $from);
        }

        return $qb->getQuery()->getResult();
    }

    public function getProcessedDate($from, $position)
    {
        $em = $this->container->get('doctrine')->getManager();
        $expr = new Expr();
        $company = $this->getCompany();
        $qb = $em->createQueryBuilder('claim');
        $qb->select('claim');
        $qb->from('AppBundle:claim', 'claim');
        $qb->leftJoin('claim.position', 'position');
        $qb->andWhere($expr->eq('position', ':position'));
        $qb->andWhere($expr->eq('claim.status', ':statusProcessed'));
        $qb->setParameter('statusProcessed', Claim::STATUS_PROCESSED);
        $qb->setParameter('position', $position);
        if ($from != 'none') {
            $qb->andWhere($expr->eq('claim.periodFrom', ':periodFrom'));
            $qb->setParameter('periodFrom', $from);
        }

        $result = $qb->getQuery()->getResult();
        if (count($result)) {
            if ($result[0]->getProcessedDate()) {
                return $result[0]->getProcessedDate()->format('Ymdhis');
            }
        }
        return null;
    }

    public function getDataForFormatPayMaster($from)
    {
        $em = $this->container->get('doctrine')->getManager();
        $expr = new Expr();
        $company = $this->getCompany();
        $qb = $em->createQueryBuilder('claim');
        $qb->select('claim,SUM(claim.claimAmountConverted)');
        $qb->from('AppBundle:Claim', 'claim');
        $qb->andWhere($expr->eq('claim.company', ':company'));
        $qb->andWhere($expr->eq('claim.status', ':statusProcessed'));
        $qb->groupBy('claim.payCode,claim.position');
        $qb->setParameter('statusProcessed', Claim::STATUS_PROCESSED);
        $qb->setParameter('company', $company);
        if ($from != 'all') {
            $qb->andWhere($expr->eq('claim.periodFrom', ':periodFrom'));
            $qb->setParameter('periodFrom', $from);
        }

        return $qb->getQuery()->getResult();
    }


    public function getDataForExcelReport($from)
    {
        $em = $this->container->get('doctrine')->getManager();
        $expr = new Expr();
        $company = $this->getCompany();
        $qb = $em->createQueryBuilder('position');
        $qb->select('position');
        $qb->from('AppBundle:Position', 'position');
        $qb->leftJoin('position.claims', 'claim');
        $qb->leftJoin('position.company', 'company');
        $qb->andWhere(
            $expr->eq('company', ':company')
        );
        $qb->andWhere($expr->eq('claim.status', ':statusProcessed'));
        $qb->setParameter('statusProcessed', Claim::STATUS_PROCESSED);
        $qb->setParameter('company', $company);
        if ($from != 'all') {
            $qb->andWhere($expr->eq('claim.periodFrom', ':periodFrom'));
            $qb->setParameter('periodFrom', $from);
        }

        return $qb->getQuery()->getResult();
    }


}
