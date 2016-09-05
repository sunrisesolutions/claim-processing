<?php
namespace AppBundle\Admin;

use AppBundle\Entity\Company;
use AppBundle\Entity\CostCentre;
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
        if ($this->getContainer()->get('security.token_storage')->getToken()) {
            return $this->getContainer()->get('security.token_storage')->getToken()->getUser();
        }
        return null;
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
                if($this->getClass() !== 'AppBundle\Entity\User'){
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


}