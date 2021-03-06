<?php
/**
 * Created by PhpStorm.
 * User: chuyennguyen
 * Date: 8/12/16
 * Time: 6:19 PM
 */

namespace AppBundle\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="employment_type")
 */

class EmploymentType
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer",options={"unsigned":true})
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;


    /**
     * @var integer
     * @ORM\Column(type="string")
     */
    private $code;

    /**
     * @var integer
     * @ORM\Column(type="string")
     */
    private $description;

    /**
     * @var Company
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Company")
     * @ORM\JoinColumn(name="company_id", referencedColumnName="id",onDelete="CASCADE")
     */
    private $company;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param int $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @return int
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param int $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return Company
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * @param Company $company
     */
    public function setCompany($company)
    {
        $this->company = $company;
    }


}