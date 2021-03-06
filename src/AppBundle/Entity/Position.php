<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Application\Sonata\MediaBundle\Entity\Media;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="position")
 */
class Position {
	const ROLE_DEFAULT = 'ROLE_USER';
	
	public function __construct() {
		$this->createdDate       = new \DateTime();
		$this->submissionBy      = new ArrayCollection();
		$this->submissionFor     = new ArrayCollection();
		$this->checkingHistories = new ArrayCollection();
		$this->approverHistories = new ArrayCollection();
		$this->roles             = array();
		$this->thirdParty        = false;
		// your own logic
	}
	
	public function __toString() {
		return $this->firstName;
	}
	
	public function getPendingClaims() {
		$criteria = Criteria::create()
		                    ->where(Criteria::expr()->in("status", [
			                    Claim::STATUS_CHECKER_APPROVED,
			                    Claim::STATUS_APPROVER_APPROVED_FIRST,
			                    Claim::STATUS_APPROVER_APPROVED_SECOND,
			                    Claim::STATUS_APPROVER_APPROVED_THIRD
		                    ]))
		                    ->orderBy(array( "createdAt" => Criteria::ASC ))
		                    ->setFirstResult(0);
		
		return $this->claims->matching($criteria);
	}
	
	/**
	 * @ORM\Id
	 * @ORM\Column(type="integer",options={"unsigned":true})
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	protected $id;
	
	/**
	 * @var Media
	 * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Company",inversedBy="positions")
	 * @ORM\JoinColumn(name="company_id", referencedColumnName="id",onDelete="CASCADE")
	 */
	private $company;
	
	/**
	 * @var User
	 * @ORM\ManyToOne(targetEntity="AppBundle\Entity\User", inversedBy="positions")
	 */
	private $user;
	
	/**
	 * @var string
	 * @ORM\Column(name="alias",type="string",nullable=true)
	 */
	private $alias;
	
	/** @var string
	 * @ORM\Column(name="first_name",type="string",nullable=true)
	 */
	private $firstName;
	
	
	/** @var string
	 * @ORM\Column(name="last_name",type="string",nullable=true)
	 */
	private $lastName;
	
	/**
	 * @var Media
	 * @ORM\ManyToOne(targetEntity="Application\Sonata\MediaBundle\Entity\Media",cascade={"persist","remove"})
	 * @ORM\JoinColumn(onDelete="SET NULL")
	 */
	private $image;
	
	/**
	 * @var string
	 * @ORM\Column(name="roles",type="array")
	 */
	private $roles;
	/**
	 * @var string
	 * @ORM\Column(name="email",type="string")
	 */
	private $email;
	
	/**
	 * @var integer
	 * @ORM\Column(name="contact_number",type="phone_number",nullable=true)
	 */
	private $contactNumber;
	/**
	 * @var integer
	 * @ORM\Column(name="employee_no",type="string")
	 */
	private $employeeNo;
	
	/**
	 * @var integer
	 * @ORM\Column(name="nric",type="string",nullable=true)
	 */
	private $nric;
	
	/**
	 * @var \DateTime
	 * @ORM\Column(name="created_date",type="datetime")
	 */
	private $createdDate;
	
	/**
	 * @var EmploymentType
	 * @ORM\ManyToOne(targetEntity="AppBundle\Entity\EmploymentType")
	 * @ORM\JoinColumn(onDelete="CASCADE")
	 */
	private $employmentType;
	
	/**
	 * @var \DateTime
	 * @ORM\Column(name="date_joined",type="date",nullable=true)
	 */
	private $dateJoined;
	
	/**
	 * @var float
	 * @ORM\Column(name="probation",type="float",nullable=true)
	 */
	private $probation;
	
	/**
	 * @var \DateTime
	 * @ORM\Column(name="last_date_of_service",type="date",nullable=true)
	 */
	private $lastDateOfService;
	
	/**
	 * @var Position
	 * @ORM\OneToMany(targetEntity="AppBundle\Entity\PositionSubmitter",mappedBy="submissionByPosition",cascade={"all"},orphanRemoval=true)
	 */
	private $submissionBy;
	
	/**
	 * @var Position
	 * @ORM\OneToMany(targetEntity="AppBundle\Entity\PositionSubmitter",mappedBy="submissionForPosition",cascade={"all"},orphanRemoval=true)
	 */
	private $submissionFor;
	
	/**
	 * @var ArrayCollection
	 * @ORM\OneToMany(targetEntity="AppBundle\Entity\Claim",mappedBy="position")
	 */
	private $claims;
	
	/**
	 * @var CheckerHistory
	 * @ORM\OneToMany(targetEntity="AppBundle\Entity\CheckerHistory",mappedBy="position")
	 */
	private $checkingHistories;
	/**
	 * @var CheckerHistory
	 * @ORM\OneToMany(targetEntity="AppBundle\Entity\ApproverHistory",mappedBy="position")
	 */
	private $approverHistories;
	
	/**
	 * @var EmployeeType
	 * @ORM\ManyToOne(targetEntity="AppBundle\Entity\EmployeeType")
	 * @ORM\JoinColumn(onDelete="CASCADE")
	 */
	private $employeeType;
	/**
	 * @var CostCentre
	 * @ORM\ManyToOne(targetEntity="AppBundle\Entity\CostCentre")
	 * @ORM\JoinColumn(onDelete="CASCADE")
	 */
	private $costCentre;
	
	/**
	 * @var Region
	 * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Region")
	 * @ORM\JoinColumn(onDelete="CASCADE")
	 */
	private $region;
	
	/**
	 * @var Branch
	 * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Branch")
	 * @ORM\JoinColumn(onDelete="CASCADE")
	 */
	private $branch;
	
	/**
	 * @var Department
	 * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Department")
	 * @ORM\JoinColumn(onDelete="CASCADE")
	 */
	private $department;
	
	/**
	 * @var Section
	 * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Section")
	 * @ORM\JoinColumn(onDelete="CASCADE")
	 */
	private $section;
	
	/**
	 * @var string
	 * @ORM\Column(name="employee_group_description",type="text")
	 */
	private $employeeGroupDescription;
	
	/**
	 * @var boolean
	 * @ORM\Column(name="third_party",type="boolean",options={"default":0})
	 */
	private $thirdParty;
	
	
	/**
	 * @return mixed
	 */
	public function getId() {
		return $this->id;
	}
	
	/**
	 * @return boolean
	 */
	public function isThirdParty() {
		return $this->thirdParty;
	}
	
	/**
	 * @param boolean $thirdParty
	 */
	public function setThirdParty($thirdParty) {
		$this->thirdParty = $thirdParty;
	}
	
	
	/**
	 * @return CheckerHistory
	 */
	public function getApproverHistories() {
		return $this->approverHistories;
	}
	
	/**
	 * @param CheckerHistory $approverHistories
	 */
	public function setApproverHistories($approverHistories) {
		$this->approverHistories = $approverHistories;
	}
	
	
	/**
	 * @return Claim
	 */
	public function getCheckingHistories() {
		return $this->checkingHistories;
	}
	
	/**
	 * @param Claim $checkingHistories
	 */
	public function setCheckingHistories($checkingHistories) {
		$this->checkingHistories = $checkingHistories;
	}
	
	
	public function addSubmissionBy(PositionSubmitter $submissionBy) {
		$this->submissionBy->add($submissionBy);
		$submissionBy->setSubmissionByPosition($this);
		
	}
	
	public function removeSubmissionBy(PositionSubmitter $submissionBy) {
		$this->submissionBy->removeElement($submissionBy);
		$submissionBy->setSubmissionByPosition(null);
	}
	
	public function addSubmissionFor(PositionSubmitter $submissionFor) {
		$this->submissionFor->add($submissionFor);
		$submissionFor->setSubmissionForPosition($this);
		
	}
	
	public function removeSubmissionFor(PositionSubmitter $submissionFor) {
		$this->submissionFor->removeElement($submissionFor);
		$submissionFor->setSubmissionForPosition(null);
	}
	
	/**
	 * @return string
	 */
	public function getEmployeeGroupDescription() {
		return $this->employeeGroupDescription;
	}
	
	/**
	 * @param string $employeeGroupDescription
	 */
	public function setEmployeeGroupDescription($employeeGroupDescription) {
		$this->employeeGroupDescription = $employeeGroupDescription;
	}
	
	
	/**
	 * @return Position
	 */
	public function getSubmissionBy() {
		return $this->submissionBy;
	}
	
	/**
	 * @param Position $submissionBy
	 */
	public function setSubmissionBy($submissionBy) {
		$this->submissionBy = $submissionBy;
	}
	
	/**
	 * @return Position
	 */
	public function getSubmissionFor() {
		return $this->submissionFor;
	}
	
	/**
	 * @param Position $submissionFor
	 */
	public function setSubmissionFor($submissionFor) {
		$this->submissionFor = $submissionFor;
	}
	
	/**
	 * @return Claim
	 */
	public function getClaims() {
		return $this->claims;
	}
	
	/**
	 * @param Claim $claims
	 */
	public function setClaims($claims) {
		$this->claims = $claims;
	}
	
	
	/**
	 * @return string
	 */
	public function getAlias() {
		return $this->alias;
	}
	
	/**
	 * @param string $alias
	 */
	public function setAlias($alias) {
		$this->alias = $alias;
	}
	
	/**
	 * @return string
	 */
	public function getFirstName() {
		return $this->firstName;
	}
	
	/**
	 * @param string $firstName
	 */
	public function setFirstName($firstName) {
		$this->firstName = $firstName;
	}
	
	/**
	 * @return string
	 */
	public function getLastName() {
		return $this->lastName;
	}
	
	/**
	 * @param string $lastName
	 */
	public function setLastName($lastName) {
		$this->lastName = $lastName;
	}
	
	/**
	 * @return Media
	 */
	public function getImage() {
		return $this->image;
	}
	
	/**
	 * @param Media $image
	 */
	public function setImage($image) {
		$this->image = $image;
	}
	
	/**
	 * @return Media
	 */
	public function getCompany() {
		return $this->company;
	}
	
	/**
	 * @param Media $company
	 */
	public function setCompany($company) {
		$this->company = $company;
	}
	
	/**
	 * @return int
	 */
	public function getContactNumber() {
		return $this->contactNumber;
	}
	
	/**
	 * @param int $contactNumber
	 */
	public function setContactNumber($contactNumber) {
		$this->contactNumber = $contactNumber;
	}
	
	/**
	 * @return int
	 */
	public function getEmployeeNo() {
		return $this->employeeNo;
	}
	
	/**
	 * @param int $employeeNo
	 */
	public function setEmployeeNo($employeeNo) {
		$this->employeeNo = $employeeNo;
	}
	
	/**
	 * @return int
	 */
	public function getNric() {
		return $this->nric;
	}
	
	/**
	 * @param int $nric
	 */
	public function setNric($nric) {
		$this->nric = $nric;
	}
	
	/**
	 * @return \DateTime
	 */
	public function getCreatedDate() {
		return $this->createdDate;
	}
	
	/**
	 * @param \DateTime $createdDate
	 */
	public function setCreatedDate($createdDate) {
		$this->createdDate = $createdDate;
	}
	
	
	/**
	 * @return EmploymentType
	 */
	public function getEmploymentType() {
		return $this->employmentType;
	}
	
	/**
	 * @param EmploymentType $employmentType
	 */
	public function setEmploymentType($employmentType) {
		$this->employmentType = $employmentType;
	}
	
	/**
	 * @return \DateTime
	 */
	public function getDateJoined() {
		return $this->dateJoined;
	}
	
	/**
	 * @param \DateTime $dateJoined
	 */
	public function setDateJoined($dateJoined) {
		$this->dateJoined = $dateJoined;
	}
	
	/**
	 * @return float
	 */
	public function getProbation() {
		return $this->probation;
	}
	
	/**
	 * @param float $probation
	 */
	public function setProbation($probation) {
		$this->probation = $probation;
	}
	
	/**
	 * @return \DateTime
	 */
	public function getLastDateOfService() {
		return $this->lastDateOfService;
	}
	
	/**
	 * @param \DateTime $lastDateOfService
	 */
	public function setLastDateOfService($lastDateOfService) {
		$this->lastDateOfService = $lastDateOfService;
	}
	
	
	/**
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}
	
	/**
	 * @param User $user
	 */
	public function setUser($user) {
		$this->user = $user;
	}
	
	/**
	 * Returns the user roles
	 *
	 * @return array The roles
	 */
	public function getRoles() {
		$roles = $this->roles;
		
		// we need to make sure to have at least one role
		$roles[] = static::ROLE_DEFAULT;
		
		return array_unique($roles);
	}
	
	public function setRoles(array $roles) {
		$this->roles = array();
		
		foreach($roles as $role) {
			$this->addRole($role);
		}
		
		return $this;
	}
	
	/**
	 * Never use this to check if this user has access to anything!
	 *
	 * Use the SecurityContext, or an implementation of AccessDecisionManager
	 * instead, e.g.
	 *
	 *         $securityContext->isGranted('ROLE_USER');
	 *
	 * @param string $role
	 *
	 * @return boolean
	 */
	public function hasRole($role) {
		return in_array(strtoupper($role), $this->getRoles(), true);
	}
	
	public function addRole($role) {
		$role = strtoupper($role);
		if($role === static::ROLE_DEFAULT) {
			return $this;
		}
		
		if( ! in_array($role, $this->roles, true)) {
			$this->roles[] = $role;
		}
		
		return $this;
	}
	
	public function removeRole($role) {
		if(false !== $key = array_search(strtoupper($role), $this->roles, true)) {
			unset($this->roles[ $key ]);
			$this->roles = array_values($this->roles);
		}
		
		return $this;
	}
	
	/**
	 * @return string
	 */
	public function getEmail() {
		return $this->email;
	}
	
	/**
	 * @param string $email
	 */
	public function setEmail($email) {
		$this->email = $email;
	}
	
	/**
	 * @return EmployeeType
	 */
	public function getEmployeeType() {
		return $this->employeeType;
	}
	
	/**
	 * @param EmployeeType $employeeType
	 */
	public function setEmployeeType($employeeType) {
		$this->employeeType = $employeeType;
	}
	
	/**
	 * @return CostCentre
	 */
	public function getCostCentre() {
		return $this->costCentre;
	}
	
	/**
	 * @param CostCentre $costCentre
	 */
	public function setCostCentre($costCentre) {
		$this->costCentre = $costCentre;
	}
	
	/**
	 * @return Region
	 */
	public function getRegion() {
		return $this->region;
	}
	
	/**
	 * @param Region $region
	 */
	public function setRegion($region) {
		$this->region = $region;
	}
	
	/**
	 * @return Branch
	 */
	public function getBranch() {
		return $this->branch;
	}
	
	/**
	 * @param Branch $branch
	 */
	public function setBranch($branch) {
		$this->branch = $branch;
	}
	
	/**
	 * @return Department
	 */
	public function getDepartment() {
		return $this->department;
	}
	
	/**
	 * @param Department $department
	 */
	public function setDepartment($department) {
		$this->department = $department;
	}
	
	/**
	 * @return Section
	 */
	public function getSection() {
		return $this->section;
	}
	
	/**
	 * @param Section $section
	 */
	public function setSection($section) {
		$this->section = $section;
	}
	
	
	public function validate(ExecutionContextInterface $context, $payload) {
		$company = $this->getCompany();
		if($company) {
			$expr     = Criteria::expr();
			$criteria = Criteria::create();
			$criteria->where($expr->eq('employeeNo', $this->employeeNo))
			         ->andWhere($expr->neq('id', $this->id));
			$positions = $company->getPositions()->matching($criteria);
			if(count($positions)) {
				$context->buildViolation('This value is exist')
				        ->atPath('employeeNo')
				        ->addViolation();
			}
			$proxySubmitters = $this->getSubmissionBy();
			foreach($proxySubmitters as $proxySubmitter) {
				$position = $proxySubmitter->getSubmissionForPosition();
				if($position && $position->getId() === $this->getId()) {
					$context->buildViolation('Proxy submitter must be difference with current employee')
					        ->atPath('submissionBy')
					        ->addViolation();
					break;
				}
			}
		}
	}
	
	
}