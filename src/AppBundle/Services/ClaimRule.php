<?php

namespace AppBundle\Services;

use AppBundle\Entity\Claim;
use AppBundle\Entity\Company;
use AppBundle\Entity\Position;
use AppBundle\Entity\User;
use Doctrine\ORM\Query\Expr;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Context\ExecutionContext;

class ClaimRule {
	use ContainerAwareTrait;
	
	/**
	 * @return ContainerInterface
	 */
	public function getContainer() {
		return $this->container;
	}
	
	/**
	 * @return User|null
	 */
	public function getUser() {
		$throwException = true;
		$msg            = 'Unauthorisation Access';
		
		if( ! $this->getContainer()->has('security.token_storage')) {
			throw new \LogicException('The SecurityBundle is not registered in your application.');
		}
		
		if(null === $token = $this->getContainer()->get('security.token_storage')->getToken()) {
			if($throwException) {
				throw new AccessDeniedException($msg);
			}
			
			return null;
		}
		
		if( ! is_object($user = $token->getUser())) {
			// e.g. anonymous authentication
			if($throwException) {
				throw new AccessDeniedException($msg);
			}
			
			return null;
		}
		
		if( ! ($user instanceof UserInterface)) {
			if($throwException) {
				throw new AccessDeniedException($msg);
			}
			
			return null;
		}
		
		return $user;
	}
	
	public function getPosition() {
		return $this->getUser()->getLoginWithPosition();
	}
	
	/**
	 * @return Company
	 */
	public function getCompany() {
		$company = $this->getContainer()->get('security.token_storage')->getToken()->getUser()->getCompany();
		//is admin
		if($company === null) {
			
		}
		
		return $company;
	}
	
	public function getClientCompany() {
		//admin will return null
		$company = $this->getCompany();
		if($company && $company->getParent()) {
			return $company->getParent();
		}
		
		return $company;
	}
	
	public function getNameUser($id) {
		$em       = $this->getContainer()->get('doctrine')->getManager();
		$position = $em->getRepository('AppBundle\Entity\Position')->find($id);
		if($position) {
			return $position->getFirstName() . ' ' . $position->getLastName();
		}
		
		return '';
	}
	
	public function getCurrencyDefault() {
		$clientCompany    = $this->getClientCompany();
		$em               = $this->getContainer()->get('doctrine')->getManager();
		$currencyExchange = $em->getRepository('AppBundle\Entity\CurrencyExchange')->findOneBy([
			'isDefault' => true,
			'company'   => $clientCompany
		]);
		
		return $currencyExchange;
	}
	
	public function getEmployeeGroupBelongToUser(Position $position) {
		$employeeGroupDescriptionStr = $position->getEmployeeGroupDescription();
		$employeeGroupDescriptionArr = explode('>', $employeeGroupDescriptionStr);
		$employeeGroupBelongUser     = $this->getContainer()->get('app.util')->getResult($employeeGroupDescriptionArr);
		
		return $employeeGroupBelongUser;
	}
	
	public function getClaimTypeDefault() {
		$clientCompany = $this->getClientCompany();
		$em            = $this->getContainer()->get('doctrine')->getManager();
		$claimType     = $em->getRepository('AppBundle\Entity\ClaimType')->findOneBy([
			'isDefault' => true,
			'company'   => $clientCompany
		]);
		
		return $claimType;
	}
	
	public function getCurrentClaimPeriod($key) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		//in the future will change with multiple cutofdate and claimable, currently just only one
		$claimPolicy = $em->getRepository('AppBundle\Entity\CompanyClaimPolicies')->findOneBy([ 'company' => $this->getClientCompany() ]);
		
		if($claimPolicy) {
			$cutOffdate  = $claimPolicy->getCutOffDate();
			$currentDate = date('d');
			if($currentDate <= $cutOffdate) {
				$periodTo   = new \DateTime('NOW');
				$clone      = clone $periodTo;
				$periodFrom = $clone->modify('-1 month');
			} else {
				$periodTo = new \DateTime('NOW');
				$periodTo->modify('+1 month');
				$clone      = clone $periodTo;
				$periodFrom = $clone->modify('-1 month');
			}
			$periodFrom->setDate($periodFrom->format('Y'), $periodFrom->format('m'), $cutOffdate + 1);
			$periodTo->setDate($periodTo->format('Y'), $periodTo->format('m'), $cutOffdate);
			$period = [ 'from' => $periodFrom, 'to' => $periodTo ];
			
			return $period[ $key ];
		}
		
		return null;
	}
	
	public function getPayCode($claim) {
		$em        = $this->getContainer()->get('doctrine')->getManager();
		$limitRule = $em->getRepository('AppBundle\Entity\LimitRule')->findOneBy([
			'claimType'     => $claim->getClaimType(),
			'claimCategory' => $claim->getClaimCategory()
		]);
		if( ! $limitRule) {
			return null;
		}
		
		return $limitRule->getPayCode();
	}
	
	
	public function getLimitAmount(Claim $claim, $position) {
		$em        = $this->getContainer()->get('doctrine')->getManager();
		$limitRule = $em->getRepository('AppBundle\Entity\LimitRule')->findOneBy([
			'claimType'     => $claim->getClaimType(),
			'claimCategory' => $claim->getClaimCategory()
		]);
		if( ! $limitRule) {
			return null;
		}
		//may be will have many limit amount, but the priority for more detail group
		$employeeGroupBelongToUser = $this->getEmployeeGroupBelongToUser($position);
		$expr                      = new Expr();
		for($i = count($employeeGroupBelongToUser) - 1; $i >= 0; $i --) {
			$limitRuleEmployeeGroup = $em->createQueryBuilder()
			                             ->select('limitRuleEmployeeGroup')
			                             ->from('AppBundle\Entity\LimitRuleEmployeeGroup', 'limitRuleEmployeeGroup')
			                             ->join('limitRuleEmployeeGroup.employeeGroup', 'employeeGroup')
			                             ->where($expr->eq('limitRuleEmployeeGroup.limitRule', ':limitRule'))
			                             ->andWhere($expr->eq('employeeGroup.description', ':employeeGroup'))
			                             ->setParameter('limitRule', $limitRule)
			                             ->setParameter('employeeGroup', $employeeGroupBelongToUser[ $i ])
			                             ->getQuery()->getOneOrNullResult();
			if($limitRuleEmployeeGroup) {
				return $limitRuleEmployeeGroup->getClaimLimit();
			}
		}
		
		return null;
	}
	
	public function isExceedLimitRule(Claim $claim, $position) {
		$limitAmount = $this->getLimitAmount($claim, $position);
		if( ! $limitAmount) {
			return false;
		}
		
		$totalAmount = $this->getTotalClaimAmountForEmployee($claim, $position);
		
		if($totalAmount > $limitAmount) {
			return true;
		}
		
		return false;
	}
	
	public function getTotalClaimAmountForEmployee(Claim $claim, $position) {
		if($claim->isFlexiClaim()) {
			$totalAmount = $this->getTotalAmountFlexiClaimForEmployee($claim->getClaimType(), $claim->getClaimCategory(), $position);
		} else {
			$totalAmount = $this->getTotalAmountNormalClaimForEmployee($claim->getClaimType(), $claim->getClaimCategory(), $position);
		}
		
		return $totalAmount;
	}
	
	public function getTotalAmountNormalClaimForEmployee($claimType, $claimCategory, $position) {
		$em         = $this->getContainer()->get('doctrine')->getManager();
		$periodFrom = $this->getCurrentClaimPeriod('from');
		$periodTo   = $this->getCurrentClaimPeriod('to');
		$expr       = new Expr();
		$claims     = $em->createQueryBuilder()
		                 ->select('claim')
		                 ->from('AppBundle\Entity\Claim', 'claim')
		                 ->where($expr->eq('claim.position', ':position'))
		                 ->andWhere($expr->eq('claim.claimType', ':claimType'))
		                 ->andWhere($expr->eq('claim.claimCategory', ':claimCategory'))
		                 ->setParameter('position', $position)
		                 ->setParameter('claimType', $claimType)
		                 ->setParameter('claimCategory', $claimCategory)
		                 ->andWhere($expr->eq('claim.periodFrom', ':periodFrom'))
		                 ->andWhere($expr->eq('claim.periodTo', ':periodTo'))
		                 ->andWhere($expr->orX(
			                 $expr->eq('claim.status', ':statusPending'),
			                 $expr->in('claim.status', ':states'),
			                 $expr->eq('claim.status', ':statusApproverApproved'),
			                 $expr->eq('claim.status', ':statusHrApproved')
		                 ))
		                 ->setParameter('periodFrom', $periodFrom->format('Y-m-d'))
		                 ->setParameter('periodTo', $periodTo->format('Y-m-d'))
		                 ->setParameter('statusPending', Claim::STATUS_PENDING)
		                 ->setParameter('states', [
			                 Claim::STATUS_CHECKER_APPROVED,
			                 Claim::STATUS_APPROVER_APPROVED_FIRST,
			                 Claim::STATUS_APPROVER_APPROVED_SECOND,
			                 Claim::STATUS_APPROVER_APPROVED_THIRD
		                 ])
		                 ->setParameter('statusApproverApproved', Claim::STATUS_APPROVER_APPROVED)
		                 ->setParameter('statusHrApproved', Claim::STATUS_PROCESSED)
		                 ->getQuery()
		                 ->getResult();
		
		$totalAmount = 0;
		foreach($claims as $claim) {
			$totalAmount += $claim->getClaimAmountConverted();
		}
		
		return $totalAmount;
	}
	
	public function getDescriptionEmployeeGroup($employeeGroup) {
		if($employeeGroup == null) {
			return null;
		}
		$description = [];
		if($employeeGroup->getCompanyApply()) {
			$description[] = $employeeGroup->getCompanyApply()->getName();
		}
		if($employeeGroup->getCostCentre()) {
			$description[] = $employeeGroup->getCostCentre()->getCode();
		}
		if($employeeGroup->getDepartment()) {
			$description[] = $employeeGroup->getDepartment()->getCode();
		}
		if($employeeGroup->getEmployeeType()) {
			$description[] = $employeeGroup->getEmployeeType()->getCode();
		}
		if(count($description)) {
			$description = implode('>', $description);
		} else {
			$description = '';
		}
		
		return $description;
	}
	
	public function updateEmployeeGroupDescription($position) {
		$employeeGroupDescription = [];
		if($position->getCompany()) {
			$employeeGroupDescription[] = $position->getCompany()->getName();
		}
		if($position->getCostCentre()) {
			$employeeGroupDescription[] = $position->getCostCentre()->getCode();
		}
		if($position->getDepartment()) {
			$employeeGroupDescription[] = $position->getDepartment()->getCode();
		}
		if($position->getEmployeeType()) {
			$employeeGroupDescription[] = $position->getEmployeeType()->getCode();
		}
		if(count($employeeGroupDescription)) {
			$employeeGroupDescription = implode('>', $employeeGroupDescription);
		} else {
			$employeeGroupDescription = '';
		}
		$position->setEmployeeGroupDescription($employeeGroupDescription);
	}
	
	public function getNumberRejectedClaim() {
		$expr       = new Expr();
		$periodFrom = $this->getContainer()->get('app.claim_rule')->getCurrentClaimPeriod('from');
		$periodTo   = $this->getContainer()->get('app.claim_rule')->getCurrentClaimPeriod('to');
		$em         = $this->getContainer()->get('doctrine')->getManager();
		$query      = $em->createQueryBuilder('claim');
		$query->select($expr->count('claim.id'));
		$query->from('AppBundle:Claim', 'claim');
		$query->andWhere(
			$expr->eq('claim.position', ':position')
		);
		$query->andWhere(
			$expr->eq('claim.periodFrom', ':periodFrom')
		);
		$query->andWhere(
			$expr->eq('claim.periodTo', ':periodTo')
		);
		$query->andWhere($expr->orX(
			$expr->eq('claim.status', ':statusCheckerRejected'),
			$expr->eq('claim.status', ':statusApproverRejected'),
			$expr->eq('claim.status', ':statusHrRejected')
		));
		$query->setParameter('periodFrom', $periodFrom->format('Y-m-d'));
		$query->setParameter('periodTo', $periodTo->format('Y-m-d'));
		$query->setParameter('statusCheckerRejected', Claim::STATUS_CHECKER_REJECTED);
		$query->setParameter('statusApproverRejected', Claim::STATUS_APPROVER_REJECTED);
		$query->setParameter('statusHrRejected', Claim::STATUS_HR_REJECTED);
		$query->setParameter('position', $this->getPosition());
		
		return $query->getQuery()->getSingleScalarResult();
	}
	
	/**--------------------------Work with currency------------------------**/
	public function getTaxAmount($claimAmount, $taxRateId) {
		$taxRate = $this->getContainer()->get('doctrine')->getManager()->find('AppBundle\Entity\TaxRate', $taxRateId);
		if($taxRate) {
			$rate            = $taxRate->getRate();
			$amountBeforeTax = $claimAmount / (1 + $rate / 100);
			$taxAmount       = $claimAmount - $amountBeforeTax;
			
			return round($taxAmount, 2);
		}
		
		return null;
	}
	
	public function getExRate($exchangeRateId, $receiptDate) {
		$currencyExchange = $this->getContainer()->get('doctrine')->getManager()->find('AppBundle\Entity\CurrencyExchange', $exchangeRateId);
		if($currencyExchange) {
			$criteria = Criteria::create();
			$expr     = Criteria::expr();
			$criteria->orderBy([ 'effectiveDate' => Criteria::DESC ]);
			$criteria->andWhere($expr->lte('effectiveDate', $receiptDate));
			$currencyExchangeValues = $currencyExchange->getCurrencyExchangeValues()->matching($criteria);
			if($currencyExchangeValues->count()) {
				return $currencyExchangeValues[0]->getExRate();
			}
		}
		
		return null;
	}
	
	public function getClaimAmountConverted($claimAmount, $exchangeRateId, $receiptDate) {
		$exRate = $this->getExRate($exchangeRateId, $receiptDate);
		if($exRate) {
			return round($claimAmount * $exRate, 2);
		}
		
		return null;
	}
	
	public function getTaxAmountConverted($taxAmount, $exchangeRateId, $receiptDate) {
		$exRate = $this->getExRate($exchangeRateId, $receiptDate);
		if($exRate) {
			return round($taxAmount * $exRate, 2);
		}
		
		return null;
	}
	
	
	/**
	 * Flexi claim
	 */
	
	public function isHaveFlexiClaim() {
		$em = $this->getContainer()->get('doctrine')->getManager();
		//in the future will change with multiple cutofdate and claimable, currently just only one
		$flexiClaimPolicy = $em->getRepository('AppBundle\Entity\CompanyFlexiClaimPolicies')->findOneBy([ 'company' => $this->getClientCompany() ]);
		
		if($flexiClaimPolicy) {
			return true;
		}
		
		return false;
	}
	
	public function isFlexiClaim($claim, $position) {
		$em        = $this->getContainer()->get('doctrine')->getManager();
		$limitRule = $em->getRepository('AppBundle\Entity\LimitRule')->findOneBy([
			'claimType'     => $claim->getClaimType(),
			'claimCategory' => $claim->getClaimCategory()
		]);
		if( ! $limitRule) {
			return 0;
		}
		//may be will have many limit amount, but the priority for more detail group
		$employeeGroupBelongToUser = $this->getEmployeeGroupBelongToUser($position);
		$expr                      = new Expr();
		for($i = count($employeeGroupBelongToUser) - 1; $i >= 0; $i --) {
			$limitRuleEmployeeGroup = $em->createQueryBuilder()
			                             ->select('limitRuleEmployeeGroup')
			                             ->from('AppBundle\Entity\LimitRuleEmployeeGroup', 'limitRuleEmployeeGroup')
			                             ->join('limitRuleEmployeeGroup.employeeGroup', 'employeeGroup')
			                             ->where($expr->eq('limitRuleEmployeeGroup.limitRule', ':limitRule'))
			                             ->andWhere($expr->eq('employeeGroup.description', ':employeeGroup'))
			                             ->setParameter('limitRule', $limitRule)
			                             ->setParameter('employeeGroup', $employeeGroupBelongToUser[ $i ])
			                             ->getQuery()->getOneOrNullResult();
			if($limitRuleEmployeeGroup) {
				return $limitRuleEmployeeGroup->isLimitPerYear();
			}
		}
		
		return 0;
	}
	
	public function getFlexiBalance() {
		$position                  = $this->getPosition();
		$company                   = $this->getClientCompany();
		$expr                      = new Expr();
		$employeeGroupBelongToUser = $this->getEmployeeGroupBelongToUser($position);
		$em                        = $this->getContainer()->get('doctrine')->getManager();
		$limitRules                = $em->getRepository('AppBundle\Entity\LimitRule')->findBy([
			'company' => $company,
		]);
		$results                   = [];
		foreach($limitRules as $limitRule) {
			//may be will have many limit amount, but the priority for more detail group
			for($i = count($employeeGroupBelongToUser) - 1; $i >= 0; $i --) {
				$limitRuleEmployeeGroup = $em->createQueryBuilder()
				                             ->select('limitRuleEmployeeGroup')
				                             ->from('AppBundle\Entity\LimitRuleEmployeeGroup', 'limitRuleEmployeeGroup')
				                             ->join('limitRuleEmployeeGroup.employeeGroup', 'employeeGroup')
				                             ->where($expr->eq('limitRuleEmployeeGroup.limitRule', ':limitRule'))
				                             ->andWhere($expr->eq('employeeGroup.description', ':employeeGroup'))
				                             ->setParameter('limitRule', $limitRule)
				                             ->setParameter('employeeGroup', $employeeGroupBelongToUser[ $i ])
				                             ->getQuery()->getOneOrNullResult();
				if($limitRuleEmployeeGroup && $limitRuleEmployeeGroup->isLimitPerYear()) {
					$claimType                                                            = $limitRule->getClaimType();
					$claimcategory                                                        = $limitRule->getClaimCategory();
					$limit                                                                = $limitRuleEmployeeGroup->getClaimLimit();
					$totalAmount                                                          = $this->getTotalAmountFlexiClaimForEmployee($claimType, $claimcategory, $position);
					$balance                                                              = $limit - $totalAmount;
					$results[ $claimType->getCode() . ' / ' . $claimcategory->getCode() ] = $balance;
				}
			}
		}
		
		return $results;
	}
	
	public function getFlexiPeriod($key) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		//in the future will change with multiple cutofdate and claimable, currently just only one
		$flexiClaimPolicy = $em->getRepository('AppBundle\Entity\CompanyFlexiClaimPolicies')->findOneBy([ 'company' => $this->getClientCompany() ]);
		
		if($flexiClaimPolicy) {
			$date     = $flexiClaimPolicy->getDateStart();
			$month    = $flexiClaimPolicy->getMonthStart();
			$year     = date('Y');
			$str      = $year . '-' . $month . '-' . $date;
			$fromDate = new \DateTime($str);
			$clone    = clone $fromDate;
			$toDate   = $clone->modify('+1 year');
			$period   = [ 'from' => $fromDate->format('Y-m-d'), 'to' => $toDate->format('Y-m-d') ];
			
			return $period[ $key ];
		}
		
		return null;
	}
	
	public function getTotalAmountFlexiClaimForEmployee($claimType, $claimCategory, $position) {
		$fromDate = $this->getFlexiPeriod('from');
		$toDate   = $this->getFlexiPeriod('to');
		$em       = $this->getContainer()->get('doctrine')->getManager();
		$expr     = new Expr();
		$claims   = $em->createQueryBuilder()
		               ->select('claim')
		               ->from('AppBundle\Entity\Claim', 'claim')
		               ->where($expr->eq('claim.position', ':position'))
		               ->andWhere($expr->eq('claim.claimType', ':claimType'))
		               ->andWhere($expr->eq('claim.claimCategory', ':claimCategory'))
		               ->setParameter('position', $position)
		               ->setParameter('claimType', $claimType)
		               ->setParameter('claimCategory', $claimCategory)
		               ->andWhere('claim.receiptDate >= :fromDate')
		               ->andWhere('claim.receiptDate < :toDate')
		               ->andWhere($expr->orX(
			               $expr->eq('claim.status', ':statusPending'),
			               $expr->in('claim.status', ':states'),
			               $expr->eq('claim.status', ':statusApproverApproved'),
			               $expr->eq('claim.status', ':statusHrApproved')
		               ))
		               ->setParameter('fromDate', $fromDate)
		               ->setParameter('toDate', $toDate)
		               ->setParameter('statusPending', Claim::STATUS_PENDING)
		               ->setParameter('states', [
			               Claim::STATUS_CHECKER_APPROVED,
			               Claim::STATUS_APPROVER_APPROVED_FIRST,
			               Claim::STATUS_APPROVER_APPROVED_SECOND,
			               Claim::STATUS_APPROVER_APPROVED_THIRD
		               ])
		               ->setParameter('statusApproverApproved', Claim::STATUS_APPROVER_APPROVED)
		               ->setParameter('statusHrApproved', Claim::STATUS_PROCESSED)
		               ->getQuery()
		               ->getResult();
		
		$totalAmount = 0;
		foreach($claims as $claim) {
			$totalAmount += $claim->getClaimAmountConverted();
		}
		
		return $totalAmount;
	}
	
}
