<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SwitchUserController extends Controller
{
    public function indexAction(Request $request){
        if($this->isGranted('ROLE_ADMIN')){
            return $this->redirectToRoute('sonata_admin_dashboard');
        }
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $positions = $user->getPositions();
        if(count($positions)===1){
            $position = $positions[0];
            $company = $position->getCompany();
            $user->setCompany($company);
            $user->setRoles($position->getRoles());
            $user->setLoginWithPosition($position);
            $user->setThirdParty($position->isThirdParty());
            $em->persist($user);
            $em->flush();
            return $this->redirectToRoute('sonata_admin_dashboard');
        }
        if($request->isMethod('POST')){
            $company = $em->getRepository('AppBundle\Entity\Company')->find($request->get('company'));
            $position = $em->getRepository('AppBundle\Entity\Position')->findOneBy(['company'=>$company,'user'=>$user]);
            $user->setCompany($company);
            $user->setRoles($position->getRoles());
            $user->setLoginWithPosition($position);
            $user->setThirdParty($position->isThirdParty());
            $em->persist($user);
            $em->flush();
            return $this->redirectToRoute('sonata_admin_dashboard');
        }
        return $this->render('@App/SwitchUser/index.html.twig',['positions'=>$positions]);
    }
    public function testAction(){
        $backend = $this->get('sonata.notification.backend');
//        $backend->createAndPublish('claimMailer',[]);
//        $backend->createAndPublish('mailer', array(
//            'from' => array(
//                'email' => 'mikedutuandu@gmail.com',
//                'name'  => 'No Reply'
//            ),
//            'to'   => array(
//                'tuandumikedu@gmail.com' => 'My User',
//            ),
//            'message' => array(
//                'html' => '<b>hello</b>',
//                'text' => 'hello'
//            ),
//            'subject' => 'Contact form',
//        ));

//        $this->get('app.claim_notification')->sendNotification();

        $em = $this->getDoctrine()->getManager();
        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {
            $connection->query('SET FOREIGN_KEY_CHECKS=0');
            $connection->query('DROP TABLE category');
            // Beware of ALTER TABLE here--it's another DDL statement and will cause
            // an implicit commit.
            $connection->query('SET FOREIGN_KEY_CHECKS=1');
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollback();
        }
        $em = $this->getDoctrine()->getManager();
        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {
            $connection->query('SET FOREIGN_KEY_CHECKS=0');
            $connection->query('DROP TABLE currency_exchange_history');
            // Beware of ALTER TABLE here--it's another DDL statement and will cause
            // an implicit commit.
            $connection->query('SET FOREIGN_KEY_CHECKS=1');
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollback();
        }

        return new Response('aa');
    }
}