<?php

namespace Sly\UrlShortenerBundle\Manager;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Sly\UrlShortenerBundle\Shortener\Shortener;
use Sly\UrlShortenerBundle\Shortener\ShortenerInterface;
use Sly\UrlShortenerBundle\Router\Router;
use Sly\UrlShortenerBundle\Router\RouterInterface;
use Sly\UrlShortenerBundle\Entity\Link;
use Sly\UrlShortenerBundle\Provider\Internal,
    Sly\UrlShortenerBundle\Provider\Bitly,
    Sly\UrlShortenerBundle\Provider\Googl;

/**
 * Manager service.
 *
 * @uses BaseManager
 * @author Cédric Dugat <ph3@slynett.com>
 */
class Manager extends BaseManager implements ManagerInterface
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var ShortenerInterface
     */
    protected $shortener;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var array
     */
    protected $config;

    /**
     * Constructor.
     * 
     * @param EntityManager      $em        Entity Manager service
     * @param ShortenerInterface $shortener Shortener service
     * @param RouterInterface    $router    Bundle Router service
     * @param array              $config    Bundle configuration
     */
    public function __construct(EntityManager $em, ShortenerInterface $shortener, RouterInterface $router, array $config)
    {
        $this->em        = $em;
        $this->shortener = $shortener;
        $this->router    = $router;
        $this->config    = $config;
    }

    /**
     * Get Link entity from linked object.
     * 
     * @param object $object Object
     * 
     * @return Link
     */
    public function getLinkEntityFromObject($object)
    {
        $objectEntityClass = get_class($object);

        if (false === in_array($objectEntityClass, array_keys($this->config['entities']))) {
            throw new \Exception(sprintf('There is no "%s" entity in UrlShortener bundle configuration', $objectEntityClass));
        }

        $q = $this->getRepository()
                ->createQueryBuilder('l')
                ->where('l.objectEntity = :objectEntity')
                ->andWhere('l.objectId = :objectId')
                ->setParameters(array(
                    'objectEntity' => get_class($object),
                    'objectId'     => $object->getId(),
                ));

        return $q->getQuery()->getOneOrNullResult();
    }

    /**
     * Get Link entity from long URL.
     * 
     * @param string $longUrl Long URL
     * 
     * @return Link
     */
    public function getLinkEntityFromLongUrl($longUrl)
    {
        $q = $this->getRepository()
                ->createQueryBuilder('l')
                ->where('l.longUrl = :longUrl')
                ->setParameter('longUrl', $longUrl);

        return $q->getQuery()->getOneOrNullResult();
    }

    /**
     * Get Link entity from hash.
     * 
     * @param string $hash Hash
     * 
     * @return Link
     */
    public function getLinkEntityFromHash($hash)
    {
        $q = $this->getRepository()
                ->createQueryBuilder('l')
                ->where('l.hash = :hash')
                ->setParameter('hash', $hash);

        return $q->getQuery()->getOneOrNullResult();
    }

    /**
     * Create new Link.
     * 
     * @param object $object Object
     * 
     * @return Link
     */
    public function createNewLink($object)
    {
        $objectEntityClass = get_class($object);
        $providerApiInformations = isset($this->config['entities'][$objectEntityClass]['api']) ? $this->config['entities'][$objectEntityClass]['api'] : null;

        $this->shortener->setProvider(
            $this->config['entities'][$objectEntityClass]['provider'],
            isset($providerApiInformations) ? $providerApiInformations : array()
        );

        $longUrl = $this->router->getObjectShowRoute($object);

        if ($createdShortUrl = $this->shortener->createShortUrl($longUrl)) {
            $link = new Link();
            $link->setObjectEntity(get_class($object));
            $link->setObjectId($object->getId());
            $link->setShortUrl($createdShortUrl['shortUrl']);
            $link->setLongUrl($longUrl);
            $link->setHash($createdShortUrl['hash']);
            $link->setProvider($this->config['entities'][$objectEntityClass]['provider']);

            $this->em->persist($link);
            $this->em->flush($link);
        } else {
            return false;
        }

        return $link;
    }

    /**
     * Get repository from entity manager.
     * 
     * @return EntityRepository
     */
    protected function getRepository()
    {
        return $this->em->getRepository('SlyUrlShortenerBundle:Link');
    }
}