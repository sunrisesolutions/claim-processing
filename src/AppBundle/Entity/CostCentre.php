<?php
/**
 * Created by PhpStorm.
 * User: chuyennguyen
 * Date: 8/12/16
 * Time: 6:21 PM
 */

namespace AppBundle\Entity;


class CostCentre
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    private $code;

    private $description;

    private $enable;

    private $isDefault;

    private $company;

}