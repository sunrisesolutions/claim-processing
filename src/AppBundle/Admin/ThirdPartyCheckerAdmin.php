<?php
namespace AppBundle\Admin;

use AppBundle\Admin\Transformer\RolesTransformer;
use AppBundle\Entity\Position;
use AppBundle\Entity\ThirdPartyChecker;
use AppBundle\Entity\User;
use Doctrine\ORM\Query\Expr;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Sonata\CoreBundle\Validator\ErrorElement;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Sonata\AdminBundle\Route\RouteCollection;

class ThirdPartyCheckerAdmin extends BaseAdmin
{


    public function setUserManager(UserManagerInterface $userManager)
    {
        $this->userManager = $userManager;
    }

    /**
     * @return UserManagerInterface
     */
    public function getUserManager()
    {
        return $this->userManager;
    }

    public function updateUser($email, $plainPassword)
    {
        $user = $this->getUserManager()->findUserByEmail($email);
        if (!$user) {
            $user = $this->getUserManager()->createUser();
            $user->setUsername($email);
            $user->setEmail($email);
            if ($plainPassword) {
                //just only create a position have email not belong system
                $user->setPlainPassword($plainPassword);
            } else {
                //when update email of position
                $user->setPassword($this->getUser()->getPassword());
            }
            $user->setEnabled(true);
            $this->getUserManager()->updateUser($user);
        } else {
            if (!empty($plainPassword)) {
                $user->setPlainPassword($plainPassword);
                $this->getUserManager()->updateCanonicalFields($user);
                $this->getUserManager()->updatePassword($user);
                $this->getUserManager()->updateUser($user);
            }
        }
        return $user;
    }
    public function updatePositionForCheckingClient($client, ThirdPartyChecker $thirdPartyChecker)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $plainPassword = $this->getForm()->get('plainPassword')->getData();
        $email = $this->getForm()->get('email')->getData();
        $position = $em->getRepository('AppBundle\Entity\Position')->findOneBy(['email' => $email,'company'=>$client]);
        $user = $this->updateUser($email, $plainPassword);
        if ($position) {
            //update position
            $position->setFirstName($thirdPartyChecker->getFirstName());
            $position->setLastName($thirdPartyChecker->getLastName());
            $position->setEmail($thirdPartyChecker->getEmail());
            $position->setAlias($thirdPartyChecker->getAlias());
            $position->setContactNumber($thirdPartyChecker->getContactNumber());
            $position->setNric($thirdPartyChecker->getNric());
            $position->setDateJoined($thirdPartyChecker->getDateJoined());
            $position->setProbation($thirdPartyChecker->getProbation());
            $position->setLastDateOfService($thirdPartyChecker->getLastDateOfService());
            $position->setThirdParty(true);

        } else {
            //create position
            $position = new Position();
            $position->setCompany($client);
            $position->setFirstName($thirdPartyChecker->getFirstName());
            $position->setLastName($thirdPartyChecker->getLastName());
            $position->setEmail($thirdPartyChecker->getEmail());
            $position->addRole('ROLE_USER');
            $position->setEmployeeNo(uniqid('3rd_checker_'));
            $position->setAlias($thirdPartyChecker->getAlias());
            $position->setContactNumber($thirdPartyChecker->getContactNumber());
            $position->setNric($thirdPartyChecker->getNric());
            $position->setDateJoined($thirdPartyChecker->getDateJoined());
            $position->setProbation($thirdPartyChecker->getProbation());
            $position->setLastDateOfService($thirdPartyChecker->getLastDateOfService());
            $position->setThirdParty(true);

        }
        $this->getContainer()->get('app.claim_rule')->updateEmployeeGroupDescription($position);
        $position->setUser($user);
        $em->persist($position);
        $em->flush();
    }

    public function manualUpdate($thirdPartyChecker)
    {
        foreach ($thirdPartyChecker->getThirdPartyCheckerClients() as $thirdPartyCheckerClient) {
            $thirdPartyChecker->addThirdPartyCheckerClient($thirdPartyCheckerClient);
            //update position
            $this->updatePositionForCheckingClient($thirdPartyCheckerClient->getClient(), $thirdPartyChecker);
        }


        parent::manualUpdate($thirdPartyChecker); // TODO: Change the autogenerated stub
    }


    protected function configureFormFields(FormMapper $formMapper)
    {
        $id = $this->getRequest()->get($this->getIdParameter());
        $object = $this->getObject($id);
        $action = $object === null ? 'create' : 'edit';
        $formMapper
            ->with('Information checker', array('class' => 'col-md-12'))
            ->add('alias', 'text', ['required' => false])
            ->add('firstName', 'text')
            ->add('lastName', 'text')
            ->add('email', 'text')
            ->add('contactNumber', PhoneNumberType::class, array('required' => false, 'widget' => PhoneNumberType::WIDGET_COUNTRY_CHOICE, 'country_choices' => array('SG'), 'preferred_country_choices' => array('SG')))
            ->add('nric', 'text', ['label' => 'NRIC/Fin No', 'required' => false])
            ->add('dateJoined', 'date', ['attr' => ['class' => 'datepicker'], 'widget' => 'single_text', 'format' => 'MM/dd/yyyy', 'required' => false])
            ->add('probation', 'number', ['label' => 'Probation (Month)', 'required' => false])
            ->add('lastDateOfService', 'date', ['attr' => ['class' => 'datepicker'], 'widget' => 'single_text', 'format' => 'MM/dd/yyyy', 'required' => false])
            ->add('plainPassword', 'text', [
                'mapped' => false,
                'required' => ($action === 'edit' ? false : true)
            ])
            ->end()
            ->with('Assign To Clients', array('class' => 'col-md-12'))
            ->add('thirdPartyCheckerClients', 'sonata_type_collection', array(
                'label' => 'Clients',
                'required' => false,
            ),
                array(
                    'edit' => 'inline',
                    'inline' => 'table',
                ))
            ->end();
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {

        $datagridMapper->add('search_by', 'doctrine_orm_callback', array(
            'callback' => function ($queryBuilder, $alias, $field, $value) {
                if (!$value['value']) {
                    return;
                }
                $expr = new Expr();
                $queryBuilder->andWhere($expr->orX(
                    $alias . '.email LIKE :email',
                    $alias . '.firstName LIKE :firstName',
                    $alias . '.contactNumber LIKE :contactNumber'
                ));
                $queryBuilder->setParameter('email', '%' . $value['value'] . '%');
                $queryBuilder->setParameter('firstName', '%' . $value['value'] . '%');
                $queryBuilder->setParameter('contactNumber', '%' . $value['value'] . '%');

                return true;
            },
            'field_type' => 'text',
            'field_options' => ['attr' => ['placeholder' => 'Name, Email, Employee No, NRIC/Fin']],
            'advanced_filter' => false,

        ));

    }


    protected function configureListFields(ListMapper $listMapper)
    {

        $listMapper
            ->addIdentifier('email', null, array(
                'sortable' => 'email',
            ))
            ->add('firstName')
            ->add('lastName')
            ->add('contactNumber')
            ->add('nric', null, ['label' => 'NRIC/Fin No'])
            ->add('_action', null, array(
                'actions' => array(
                    'delete' => array(),
                )
            ));
    }


    public function toString($object)
    {
        return $object instanceof ThirdPartyChecker
            ? $object->getEmail()
            : 'Third Party Checker'; // shown in the breadcrumb on the create view
    }
}