<?php
namespace AppBundle\Admin;

use AppBundle\Entity\Company;
use AppBundle\Entity\CostCentre;
use AppBundle\Entity\PayCodeType;
use AppBundle\Entity\Region;
use Doctrine\ORM\Query\Expr;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Sonata\AdminBundle\Route\RouteCollection;
use AppBundle\Entity\User;

class BaseAdmin extends AbstractAdmin
{
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

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

    public function getCompany()
    {
        return $this->getContainer()->get('security.token_storage')->getToken()->getUser()->getCompany();
    }

    public function isAdmin()
    {
        if ($this->getUser()) {
            if ($this->getUser()->hasRole('ROLE_ADMIN')) {
                return true;
            }
        }
        return false;
    }

    public function isCLient()
    {
        if ($this->getUser()) {
            if ($this->getUser()->hasRole('ROLE_CLIENT_ADMIN')) {
                return true;
            }
        }
        return false;
    }

    public function isCs()
    {
        if ($this->getUser()) {
            if ($this->getUser()->hasRole('ROLE_CS_ADMIN')) {
                return true;
            }
        }
        return false;
    }

    public function isHr()
    {
        if ($this->getUser()) {
            if ($this->getUser()->hasRole('ROLE_HR_ADMIN')) {
                return true;
            }
        }
        return false;
    }

    /*
    * add company when add new(not effect when update)
    */
    public function prePersist($object)
    {
        if ($this->isAdmin()) {
            //admin current only create a client company (parent = null)
            if ($object instanceof Company) {
                $em = $this->getContainer()->get('doctrine')->getManager();
                //add Pay Code Type: By system default always has “Deductions” and “Allowances”
                $payCodeType1 = new PayCodeType();
                $payCodeType1->setName('Deductions');
                $payCodeType1->setOrderSort(1);
                $payCodeType1->setEnabled(true);
                $payCodeType1->setCompany($object);
                $em->persist($payCodeType1);

                $payCodeType2 = new PayCodeType();
                $payCodeType2->setName('Allowances');
                $payCodeType2->setOrderSort(1);
                $payCodeType2->setEnabled(true);
                $payCodeType2->setCompany($object);
                $em->persist($payCodeType2);
                $em->flush();

            }
        }
        if ($this->isCLient()) {
            if (!$object instanceof Company) {
                if (!$object instanceof User) {
                    $object->setCompany($this->getCompany());
                }
            } elseif ($object instanceof Company) {
                if ($object->getId() !== $this->getCompany()->getId()) {
                    $object->setParent($this->getCompany());
                }
            }
        }
        if ($this->isHr()) {
            $object->setCompany($this->getCompany());
        }
    }

    /*
     * filter by company list
     */
    public function createQuery($context = 'list')
    {
        $query = parent::createQuery($context);
        $class = $this->getClass();
        $company = $this->getCompany();
        $expr = new Expr();
        if ($this->isCLient()) {
            if ($company instanceof $class) {
                $query->andWhere(
                    $expr->orX(
                        $expr->eq($query->getRootAliases()[0] . '.parent', ':company'),
                        $expr->eq($query->getRootAliases()[0], ':company')
                    )
                );

                $query->setParameter('company', $company);
            } else {
                if ($this->getClass() !== 'AppBundle\Entity\User') {
                    $query->andWhere(
                        $expr->eq($query->getRootAliases()[0] . '.company', ':company')
                    );
                    $query->setParameter('company', $company);
                }

            }
        }
        if ($this->isHr()) {
            $query->andWhere(
                $expr->eq($query->getRootAliases()[0] . '.company', ':company')
            );
            $query->setParameter('company', $company);
        }

        return $query;
    }

    public function filterCompanyBycompany()
    {
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();
        $expr = new Expr();
        $qb->select('company')
            ->from('AppBundle\Entity\Company', 'company')
            ->where(
                $expr->orX(
                    $expr->eq('company.parent', ':company'),
                    $expr->eq('company', ':company')
                )
            )
            ->setParameter('company', $this->getCompany());
        return $qb;
    }

    public function filterCostCentreBycompany()
    {
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();
        $expr = new Expr();
        $qb->select('costCentre')
            ->from('AppBundle\Entity\CostCentre', 'costCentre')
            ->where($expr->eq('costCentre.company', ':company'))
            ->andWhere($expr->eq('costCentre.enabled', true))
            ->setParameter('company', $this->getCompany());
        return $qb;
    }

    public function filterRegionBycompany()
    {
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();
        $expr = new Expr();
        $qb->select('region')
            ->from('AppBundle\Entity\Region', 'region')
            ->where($expr->eq('region.company', ':company'))
            ->andWhere($expr->eq('region.enabled', true))
            ->setParameter('company', $this->getCompany());
        return $qb;
    }

    public function filterBranchBycompany()
    {
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();
        $expr = new Expr();
        $qb->select('branch')
            ->from('AppBundle\Entity\Branch', 'branch')
            ->where($expr->eq('branch.company', ':company'))
            ->andWhere($expr->eq('branch.enabled', true))
            ->setParameter('company', $this->getCompany());
        return $qb;
    }

    public function filterDepartmentBycompany()
    {
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();
        $expr = new Expr();
        $qb->select('department')
            ->from('AppBundle\Entity\Department', 'department')
            ->where($expr->eq('department.company', ':company'))
            ->andWhere($expr->eq('department.enabled', true))
            ->setParameter('company', $this->getCompany());
        return $qb;
    }

    public function filterSectionBycompany(){
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();
        $expr = new Expr();
        $qb->select('section')
            ->from('AppBundle\Entity\Section','section')
            ->where($expr->eq('section.company', ':company'))
            ->andWhere($expr->eq('section.enabled', true))
            ->setParameter('company', $this->getCompany());
        return $qb;
    }

    public function filterEmployeeTypeBycompany(){
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();
        $expr = new Expr();
        $qb->select('employeeType')
            ->from('AppBundle\Entity\EmployeeType','employeeType')
            ->where($expr->eq('employeeType.company', ':company'))
            ->setParameter('company', $this->getCompany());
        return $qb;
    }

    public function filterClaimTypeBycompany()
    {
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();
        $expr = new Expr();
        $qb->select('claimType')
            ->from('AppBundle\Entity\ClaimType', 'claimType')
            ->where($expr->eq('claimType.company', ':company'))
            ->andWhere($expr->eq('claimType.enabled', true))
            ->setParameter('company', $this->getCompany());
        return $qb;
    }

    public function filterClaimCategoryBycompany()
    {
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();
        $expr = new Expr();
        $qb->select('claimCategory')
            ->from('AppBundle\Entity\ClaimCategory', 'claimCategory')
            ->where($expr->eq('claimCategory.company', ':company'))
            ->setParameter('company', $this->getCompany());
        return $qb;
    }

    public function filterTaxRateBycompany()
    {
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();
        $expr = new Expr();
        $qb->select('taxRate')
            ->from('AppBundle\Entity\TaxRate', 'taxRate')
            ->where($expr->eq('taxRate.company', ':company'))
            ->setParameter('company', $this->getCompany());
        return $qb;
    }

    public function filterPayCostBycompany()
    {
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();
        $expr = new Expr();
        $qb->select('payCode')
            ->from('AppBundle\Entity\PayCode', 'payCode')
            ->where($expr->eq('payCode.company', ':company'))
            ->andWhere($expr->eq('payCode.enabled', true))
            ->setParameter('company', $this->getCompany());
        return $qb;
    }
    public function filterClaimTypeTypeBycompany(){
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();
        $expr = new Expr();
        $qb->select('claimTypeType')
            ->from('AppBundle\Entity\ClaimTypeType','claimTypeType')
            ->where($expr->eq('claimTypeType.company', ':company'))
            ->andWhere($expr->eq('claimTypeType.enabled', true))
            ->setParameter('company', $this->getCompany());
        return $qb;
    }
    public function filterPayCodeTypeBycompany(){
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();
        $expr = new Expr();
        $qb->select('payCodeType')
            ->from('AppBundle\Entity\PayCodeType','payCodeType')
            ->where($expr->eq('payCodeType.company', ':company'))
            ->andWhere($expr->eq('payCodeType.enabled', true))
            ->setParameter('company', $this->getCompany());
        return $qb;
    }
    public function filterEmploymentTypeBycompany(){
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();
        $expr = new Expr();
        $qb->select('employmentType')
            ->from('AppBundle\Entity\EmploymentType','employmentType')
            ->where($expr->eq('employmentType.company', ':company'))
            ->setParameter('company', $this->getCompany());
        return $qb;
    }


}