<?php
namespace AppBundle\Admin;

use AppBundle\Entity\Category;
use AppBundle\Entity\Claim;
use AppBundle\Entity\ClaimCategory;
use AppBundle\Entity\CompanyClaimPolicies;
use AppBundle\Entity\LimitRule;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Doctrine\ORM\Query\Expr;
use AppBundle\Admin\BaseAdmin;

class LimitRuleAdmin extends BaseAdmin
{


    protected function configureFormFields(FormMapper $formMapper)
    {


        $formMapper
            ->add('claimType', 'sonata_type_model', array(
                'property' => 'code',
                'query' => $this->filterClaimTypeBycompany(),
                'placeholder' => 'Select Type',
                'empty_data' => null,
                'btn_add' => false
            ))
            ->add('claimCategory', 'sonata_type_model', array(
                'property' => 'code',
                'query' => $this->filterClaimCategoryBycompany(),
                'placeholder' => 'Select Category',
                'empty_data' => null,
                'btn_add' => false
            ))
            ->add('payCode', 'sonata_type_model', array(
                'property' => 'code',
                'query' => $this->filterPayCostBycompany(),
                'placeholder' => 'Select Pay Code',
                'empty_data' => null,
                'required' => false,
                'btn_add' => false
            ))
            ->add('taxRate', 'sonata_type_model', array(
                'property' => 'code',
                'query' => $this->filterTaxRateBycompany(),
                'placeholder' => 'Select Tax Rate',
                'empty_data' => null,
                'required' => false,
                'btn_add' => false
            ))
            ->add('limitRuleEmployeeGroups', 'sonata_type_collection', array(
                'label' => 'Employee Groups',
                'required' => false,
            ),
                array(
                    'edit' => 'inline',
                    'inline' => 'table',
                ));
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper->add('claimType.code');
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('claimType.code')
            ->add('claimType.claimTypeType.name', null, ['label' => 'Claim Type'])
            ->add('claimCategory.code', null, ['label' => 'Category Code'])
            ->add('claimCategory.description', null, ['label' => 'Category Description'])
            ->add('taxRate.code', null, ['label' => 'Tax Code'])
            ->add('payCode.code', null, ['label' => 'Pay Code'])
            ->add('_action', null, array(
                'actions' => array(
                    'delete' => array(),
                )
            ));
    }

    public function manualUpdate($limitRule)
    {
        foreach ($limitRule->getLimitRuleEmployeeGroups() as $limitRuleEmployeeGroup) {
            $limitRule->addLimitRuleEmployeeGroup($limitRuleEmployeeGroup);
        }
    }

    public function toString($object)
    {
        return $object instanceof LimitRule
            ? 'Claims Limit Rules'
            : 'Claims Limit Rules'; // shown in the breadcrumb on the create view
    }


}