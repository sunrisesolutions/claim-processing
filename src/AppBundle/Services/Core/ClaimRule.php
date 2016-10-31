<?php

namespace AppBundle\Services\Core;

use AppBundle\Entity\Claim;
use Doctrine\ORM\Query\Expr;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class ClaimRule
{
    use ContainerAwareTrait;

    /*1 global--------------------------------------*/
    public function getUser()
    {
        if (!$this->container->has('security.token_storage')) {
            return;
        }

        $tokenStorage = $this->container->get('security.token_storage');

        if (!$token = $tokenStorage->getToken()) {
            return;
        }

        $user = $token->getUser();
        if (!is_object($user)) {
            return;
        }

        return $user;
    }

    public function getPosition()
    {
        return $this->getUser()->getLoginWithPosition();
    }

    public function getCompany()
    {
        $company = $this->container->get('security.token_storage')->getToken()->getUser()->getCompany();
        //is admin
        if ($company === null) {

        }
        return $company;
    }

    public function getEmployeeGroupBelongToUser()
    {
        $employeeGroupDescriptionStr = $this->getPosition()->getEmployeeGroupDescription();
        $employeeGroupDescriptionArr = explode('>', $employeeGroupDescriptionStr);
        $employeeGroupBelongUser = [];
        for ($i = 0; $i <= count($employeeGroupDescriptionArr) - 1; $i++) {
            $groupItemArr = [];
            for ($j = 0; $j <= $i; $j++) {
                $groupItemArr[] = $employeeGroupDescriptionArr[$j];
            }
            $groupItemStr = implode('>', $groupItemArr);
            $employeeGroupBelongUser[] = $groupItemStr;
        }
        return $employeeGroupBelongUser;
    }

    public function getClaimTypeDefault()
    {
        $position = $this->getPosition();
        $company = $this->getCompany();
        $clientCompany = $company->getParent() ? $company->getParent() : $company;
        $em = $this->container->get('doctrine')->getManager();
        $claimType = $em->getRepository('AppBundle\Entity\ClaimType')->findOneBy(['isDefault' => true, 'company' => $clientCompany]);
        return $claimType;
    }

    public function getCurrentClaimPeriod($key)
    {
        $em = $this->container->get('doctrine')->getManager();
        //in the future will change with multiple cutofdate and claimable, currently just only one
        $claimType = $em->getRepository('AppBundle\Entity\ClaimType')->findOneBy([]);

        $claimPolicy = $claimType->getCompanyClaimPolicies();
        $cutOffdate = $claimPolicy->getCutOffDate();
        $currentDate = date('d');
        if ($currentDate <= $cutOffdate) {
            $periodTo = new \DateTime('NOW');
            $clone = clone $periodTo;
            $periodFrom = $clone->modify('-1 month');
        } else {
            $periodTo = new \DateTime('NOW');
            $periodTo->modify('+1 month');
            $clone = clone $periodTo;
            $periodFrom = $clone->modify('-1 month');
        }
        $periodFrom->setDate($periodFrom->format('Y'), $periodFrom->format('m'), $cutOffdate + 1);
        $periodTo->setDate($periodTo->format('Y'), $periodTo->format('m'), $cutOffdate);
        $period = ['from' => $periodFrom, 'to' => $periodTo];
        return $period[$key];
    }

    public function getLimitAmount(Claim $claim)
    {
        $em = $this->container->get('doctrine')->getManager();
        $limitRule = $em->getRepository('AppBundle\Entity\LimitRule')->findOneBy([
            'claimType' => $claim->getClaimType(),
            'claimCategory' => $claim->getClaimCategory()
        ]);
        if (!$limitRule) {
            return null;
        }
        //may be will have many limit amount, but the priority for more detail group
        $employeeGroupBelongToUser = $this->getEmployeeGroupBelongToUser();
        $expr = new Expr();
        for ($i = count($employeeGroupBelongToUser) - 1; $i >= 0; $i--) {
            $limitRuleEmployeeGroup = $em->createQueryBuilder()
                ->select('limitRuleEmployeeGroup')
                ->from('AppBundle\Entity\LimitRuleEmployeeGroup', 'limitRuleEmployeeGroup')
                ->join('limitRuleEmployeeGroup.employeeGroup', 'employeeGroup')
                ->where($expr->eq('limitRuleEmployeeGroup.limitRule', ':limitRule'))
                ->andWhere($expr->eq('employeeGroup.description', ':employeeGroup'))
                ->setParameter('limitRule', $limitRule)
                ->setParameter('employeeGroup', $employeeGroupBelongToUser[$i])
                ->getQuery()->getOneOrNullResult();
            if ($limitRuleEmployeeGroup) {
                return $limitRuleEmployeeGroup->getClaimLimit();
            }
        }
        return null;
    }

    public function isExceedLimitRule(Claim $claim)
    {
        $em = $this->container->get('doctrine')->getManager();
        $periodFrom = $this->getCurrentClaimPeriod('from');
        $periodTo = $this->getCurrentClaimPeriod('to');
        $expr = new Expr();
        $claims = $em->createQueryBuilder()
            ->select('claim')
            ->from('AppBundle\Entity\Claim', 'claim')
            ->where($expr->eq('claim.position', ':position'))
            ->andWhere($expr->eq('claim.claimType', ':claimType'))
            ->andWhere($expr->eq('claim.claimCategory', ':claimCategory'))
            ->andWhere($expr->eq('claim.periodFrom', ':periodFrom'))
            ->andWhere($expr->eq('claim.periodTo', ':periodTo'))
            ->setParameter('position', $this->getPosition())
            ->setParameter('claimType', $claim->getClaimType())
            ->setParameter('claimCategory', $claim->getClaimCategory())
            ->setParameter('periodFrom', $periodFrom->format('Y-m-d'))
            ->setParameter('periodTo', $periodTo->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        $limitAmount = $this->getLimitAmount($claim);
        if (!$limitAmount) {
            return false;
        }
        $totalAmount = $claim->getClaimAmount();
        foreach ($claims as $claim) {
            $totalAmount += $claim->getClaimAmount();
        }
        if ($totalAmount > $limitAmount) {
            return true;
        }
        return false;
    }


    /*2 for checker--------------------------------------*/
    public function getChecker(Claim $claim)
    {
        $expr = new Expr();
        $em = $this->container->get('doctrine')->getManager();
        //may be will have many checker, but the priority for more detail group
        $employeeGroupBelongToUser = $this->getEmployeeGroupBelongToUser();
        for ($i = count($employeeGroupBelongToUser) - 1; $i >= 0; $i--) {
            $checker = $em->createQueryBuilder()
                ->select('checker')
                ->from('AppBundle\Entity\Checker', 'checker')
                ->join('checker.checkerEmployeeGroups', 'checkerEmployeeGroup')
                ->join('checkerEmployeeGroup.employeeGroup', 'employeeGroup')
                ->where($expr->eq('employeeGroup.description', ':employeeGroup'))
                ->setParameter('employeeGroup', $employeeGroupBelongToUser[$i])
                ->getQuery()->getOneOrNullResult();
            if ($checker) {
                return $checker;
            }
        }
        return null;

    }

    public function getListClaimPeriodForFilterChecker()
    {
        $expr = new Expr();
        $position = $this->getPosition();
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder('claim');
        $qb->select('claim');
        $qb->from('AppBundle:Claim', 'claim');
        $qb->join('claim.checker', 'checker');
        $qb->where($expr->orX('checker.checker = :position', 'checker.backupChecker = :position'));
        $qb->andWhere($expr->orX(
            $expr->eq('claim.status', ':statusPending'),
            $expr->eq('claim.status', ':statusCheckerRejected'),
            $expr->eq('claim.status', ':statusCheckerApproved')
        ));
        $qb->orderBy('claim.createdAt', 'DESC');

        $qb->setParameter('statusPending', Claim::STATUS_PENDING);
        $qb->setParameter('statusCheckerRejected', Claim::STATUS_CHECKER_REJECTED);
        $qb->setParameter('statusCheckerApproved', Claim::STATUS_CHECKER_APPROVED);
        $qb->setParameter('position', $position);
        $claims = $qb->getQuery()->getResult();

        $listPeriod = [];
        foreach ($claims as $claim) {
            $listPeriod[$claim->getPeriodFrom()->format('d M Y') . ' - ' . $claim->getPeriodTo()->format('d M Y')] = $claim->getPeriodFrom()->format('Y-m-d');
        }
        return $listPeriod;
    }

    public function getNumberClaimEachEmployeeForChecker($position, $positionChecker)
    {
        $expr = new Expr();
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder('claim');
        $qb->select($qb->expr()->count('claim.id'));
        $qb->from('AppBundle:Claim', 'claim');
        $qb->join('claim.checker', 'checker');
        $qb->where('claim.position = :position');
        $qb->andWhere($expr->orX('checker.checker = :positionChecker', 'checker.backupChecker = :positionChecker'));
        $qb->andWhere($expr->orX(
            $expr->eq('claim.status', ':statusPending'),
            $expr->eq('claim.status', ':statusCheckerRejected'),
            $expr->eq('claim.status', ':statusCheckerApproved')
        ));
        $qb->setParameter('statusPending', Claim::STATUS_PENDING);
        $qb->setParameter('statusCheckerRejected', Claim::STATUS_CHECKER_REJECTED);
        $qb->setParameter('statusCheckerApproved', Claim::STATUS_CHECKER_APPROVED);
        $qb->setParameter('position', $position);
        $qb->setParameter('positionChecker', $positionChecker);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function isShowMenuForChecker($position)
    {
        $expr = new Expr();
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder('claim');
        $qb->select($qb->expr()->count('claim.id'));
        $qb->from('AppBundle:Claim', 'claim');
        $qb->join('claim.checker', 'checker');
        $qb->where($expr->orX('checker.checker = :position', 'checker.backupChecker = :position'));
        $qb->andWhere($expr->orX(
            $expr->eq('claim.status', ':statusPending'),
            $expr->eq('claim.status', ':statusCheckerRejected'),
            $expr->eq('claim.status', ':statusCheckerApproved')
        ));
        $qb->setParameter('statusPending', Claim::STATUS_PENDING);
        $qb->setParameter('statusCheckerRejected', Claim::STATUS_CHECKER_REJECTED);
        $qb->setParameter('statusCheckerApproved', Claim::STATUS_CHECKER_APPROVED);
        $qb->setParameter('position', $position);

        return $qb->getQuery()->getSingleScalarResult();
    }


    /*3 for approver--------------------------------------*/
    public function getApprover(Claim $claim)
    {
        $expr = new Expr();
        $em = $this->container->get('doctrine')->getManager();
        //may be will have many approver, but the priority for more detail group
        $employeeGroupBelongToUser = $this->getEmployeeGroupBelongToUser();
        for ($i = count($employeeGroupBelongToUser) - 1; $i >= 0; $i--) {
            $approver = $em->createQueryBuilder()
                ->select('approvalAmountPolicies')
                ->from('AppBundle\Entity\ApprovalAmountPolicies', 'approvalAmountPolicies')
                ->join('approvalAmountPolicies.approvalAmountPoliciesEmployeeGroups', 'approvalAmountPoliciesEmployeeGroup')
                ->join('approvalAmountPoliciesEmployeeGroup.employeeGroup', 'employeeGroup')
                ->where($expr->eq('employeeGroup.description', ':employeeGroup'))
                ->setParameter('employeeGroup', $employeeGroupBelongToUser[$i])
                ->getQuery()->getOneOrNullResult();
            if ($approver) {
                return $approver;
            }
        }
        return null;

    }

    public function assignClaimToSpecificApprover(Claim $claim)
    {
        $approver = $this->getApprover($claim);
        $amount = $claim->getClaimAmount();
        if ($approver) {
            //check approver1 can approve ?
            if ($approver->getApprover1() && $approver->isApproval1AmountStatus()) {
                if ($approver->getApproval1Amount()) {
                    if ($approver->getApproval1Amount() >= $amount) {
                        if ($approver->getApprover1()->getId() != $this->getPosition()->getId()) {
                            $result['approverEmployee'] = $approver->getApprover1();
                        } else {
                            $result['approverEmployee'] = $approver->getOverrideApprover1();
                        }
                        $result['approverBackupEmployee'] = $approver->getBackupApprover1();
                        return $result;
                    }
                } else {
                    if ($approver->getApprover1()->getId() != $this->getPosition()->getId()) {
                        $result['approverEmployee'] = $approver->getApprover1();
                    } else {
                        $result['approverEmployee'] = $approver->getOverrideApprover1();
                    }
                    $result['approverBackupEmployee'] = $approver->getBackupApprover1();
                    return $result;
                }
            }
            //check approver2 can approve ?
            if ($approver->getApprover2() && $approver->isApproval2AmountStatus()) {
                if ($approver->getApproval2Amount()) {
                    if ($approver->getApproval2Amount() >= $amount) {
                        if ($approver->getApprover2()->getId() != $this->getPosition()->getId()) {
                            $result['approverEmployee'] = $approver->getApprover2();
                        } else {
                            $result['approverEmployee'] = $approver->getOverrideApprover2();
                        }
                        $result['approverBackupEmployee'] = $approver->getBackupApprover2();
                        return $result;
                    }
                } else {
                    if ($approver->getApprover2()->getId() != $this->getPosition()->getId()) {
                        $result['approverEmployee'] = $approver->getApprover2();
                    } else {
                        $result['approverEmployee'] = $approver->getOverrideApprover2();
                    }
                    $result['approverBackupEmployee'] = $approver->getBackupApprover2();
                    return $result;
                }
            }
            //check approver3 can approve ?
            if ($approver->getApprover3() && $approver->isApproval3AmountStatus()) {
                if ($approver->getApproval3Amount()) {
                    if ($approver->getApproval3Amount() >= $amount) {
                        if ($approver->getApprover3()->getId() != $this->getPosition()->getId()) {
                            $result['approverEmployee'] = $approver->getApprover3();
                        } else {
                            $result['approverEmployee'] = $approver->getOverrideApprover3();
                        }
                        $result['approverBackupEmployee'] = $approver->getBackupApprover3();
                        return $result;
                    }
                } else {
                    if ($approver->getApprover3()->getId() != $this->getPosition()->getId()) {
                        $result['approverEmployee'] = $approver->getApprover3();
                    } else {
                        $result['approverEmployee'] = $approver->getOverrideApprover3();
                    }
                    $result['approverBackupEmployee'] = $approver->getBackupApprover3();
                    return $result;
                }
            }
        }
        return ['approverEmployee' => null, 'approverBackupEmployee' => null];
    }

    public function getListClaimPeriodForFilterApprover()
    {
        $expr = new Expr();
        $position = $this->getPosition();
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder('claim');
        $qb->select('claim');
        $qb->from('AppBundle:Claim', 'claim');
        $qb->where($expr->orX('claim.approverEmployee = :position', 'claim.approverBackupEmployee = :position'));
        $qb->andWhere($expr->orX(
            $expr->eq('claim.status', ':statusCheckerApproved'),
            $expr->eq('claim.status', ':statusApproverRejected'),
            $expr->eq('claim.status', ':statusApproverApproved')
        ));
        $qb->orderBy('claim.createdAt', 'DESC');
        $qb->setParameter('statusCheckerApproved', Claim::STATUS_CHECKER_APPROVED);
        $qb->setParameter('statusApproverRejected', Claim::STATUS_APPROVER_REJECTED);
        $qb->setParameter('statusApproverApproved', Claim::STATUS_APPROVER_APPROVED);
        $qb->setParameter('position', $position);
        $claims = $qb->getQuery()->getResult();

        $listPeriod = [];
        foreach ($claims as $claim) {
            $listPeriod[$claim->getPeriodFrom()->format('d M Y') . ' - ' . $claim->getPeriodTo()->format('d M Y')] = $claim->getPeriodFrom()->format('Y-m-d');
        }
        return $listPeriod;
    }

    public function getNumberClaimEachEmployeeForApprover($position, $positionApprover)
    {
        $expr = new Expr();
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder('claim');
        $qb->select($qb->expr()->count('claim.id'));
        $qb->from('AppBundle:Claim', 'claim');
        $qb->where('claim.position = :position');
        $qb->andWhere($expr->orX('claim.approverEmployee = :positionApprover', 'claim.approverBackupEmployee = :positionApprover'));
        $qb->andWhere($expr->orX(
            $expr->eq('claim.status', ':statusCheckerApproved'),
            $expr->eq('claim.status', ':statusApproverRejected'),
            $expr->eq('claim.status', ':statusApproverApproved')
        ));
        $qb->setParameter('statusCheckerApproved', Claim::STATUS_CHECKER_APPROVED);
        $qb->setParameter('statusApproverRejected', Claim::STATUS_APPROVER_REJECTED);
        $qb->setParameter('statusApproverApproved', Claim::STATUS_APPROVER_APPROVED);
        $qb->setParameter('position', $position);
        $qb->setParameter('positionApprover', $positionApprover);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function isShowMenuForApprover($position)
    {
        $expr = new Expr();
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder('claim');
        $qb->select($qb->expr()->count('claim.id'));
        $qb->from('AppBundle:Claim', 'claim');
        $qb->where($expr->orX('claim.approverEmployee = :position', 'claim.approverBackupEmployee = :position'));
        $qb->andWhere($expr->orX(
            $expr->eq('claim.status', ':statusCheckerApproved'),
            $expr->eq('claim.status', ':statusApproverRejected'),
            $expr->eq('claim.status', ':statusApproverApproved')
        ));
        $qb->setParameter('statusCheckerApproved', Claim::STATUS_CHECKER_APPROVED);
        $qb->setParameter('statusApproverRejected', Claim::STATUS_APPROVER_REJECTED);
        $qb->setParameter('statusApproverApproved', Claim::STATUS_APPROVER_APPROVED);
        $qb->setParameter('position', $position);

        return $qb->getQuery()->getSingleScalarResult();
    }


}
