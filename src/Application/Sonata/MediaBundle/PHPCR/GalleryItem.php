<?php

/**
 * This file is part of the <name> project.
 *
 * (c) <yourname> <youremail>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Application\Sonata\MediaBundle\PHPCR;

use Sonata\MediaBundle\PHPCR\BaseGalleryItem as BaseGalleryItem;

/**
 * This file has been generated by the EasyExtends bundle ( https://sonata-project.org/bundles/easy-extends )
 *
 * References :
 *   working with object : http://docs.doctrine-project.org/projects/doctrine-phpcr-odm/en/latest/index.html
 *
 * @author <yourname> <youremail>
 */
class GalleryItem extends BaseGalleryItem
{
    /**
     * @var integer $id
     */
    protected $id;

    /**
     * Get id
     *
     * @return integer $id
     */
    public function getId()
    {
        return $this->id;
    }
}