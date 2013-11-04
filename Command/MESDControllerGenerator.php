<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MESD\Console\GeneratorBundle\Command;

use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Generates a CRUD controller.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MESDControllerGenerator extends Generator
{
    protected $filesystem;
    protected $skeletonDir;
    protected $routePrefix;
    protected $routeNamePrefix;
    protected $bundle;
    protected $entity;
    protected $metadata;
    protected $format;
    protected $actions;
    protected $overwrite;

    /**
     * Constructor.
     *
     * @param Filesystem $filesystem  A Filesystem instance
     * @param string  $skeletonDir Path to the skeleton directory
     */
    public function __construct( Filesystem $filesystem, $skeletonDir ) {
        $this->filesystem  = $filesystem;
        $this->setSkeletonDirs($skeletonDir);
        // $this->skeletonDir = $skeletonDir;
    }

    /**
     * Generate the CRUD controller.
     *
     * @param BundleInterface $bundle           A bundle object
     * @param string  $entity           The entity relative class name
     * @param ClassMetadataInfo $metadata         The entity class metadata
     * @param string  $format           The configuration format (xml, yaml, annotation)
     * @param string  $routePrefix      The route name prefix
     * @param array   $needWriteActions Wether or not to generate write actions
     *
     * @throws \RuntimeException
     */
    public function generate( BundleInterface $bundle, $entity, ClassMetadataInfo $metadata, $format, $routePrefix, $noWriteActions, $overwrite ) {
        $this->routePrefix = $routePrefix;
        $this->routeNamePrefix = str_replace( '/', '_', $routePrefix );
        $this->actions = $noWriteActions ? array( 'index', 'show' ) : array( 'index', 'show', 'new', 'edit', 'delete' ) ;
        $this->overwrite=$overwrite;
        if ( count( $metadata->identifier ) > 1 ) {
            throw new \RuntimeException( 'The CRUD generator does not support entity classes with multiple primary keys.' );
        }

        if ( !in_array( 'id', $metadata->identifier ) ) {
            throw new \RuntimeException( 'The CRUD generator expects the entity object has a primary key field named "id" with a getId() method.' );
        }

        $this->entity   = $entity;
        $this->bundle   = $bundle;
        $this->metadata = $metadata;
        $this->setFormat( $format );

        $this->generateControllerClass($overwrite);

        // $dir = sprintf('%s/Resources/views/%s', $this->bundle->getPath(), str_replace('\\', '/', $this->entity));

        // if (!file_exists($dir)) {
        //     $this->filesystem->mkdir($dir, 0777);
        // }

        $this->generateTestClass($overwrite);
        $this->generateConfiguration($overwrite);
    }

    /**
     * Sets the configuration format.
     *
     * @param string  $format The configuration format
     */
    private function setFormat( $format ) {
        switch ( $format ) {
        case 'yml':
        case 'xml':
        case 'php':
        case 'annotation':
            $this->format = $format;
            break;
        default:
            $this->format = 'yml';
            break;
        }
    }

    /**
     * Generates the routing configuration.
     *
     */
    private function generateConfiguration() {
        if ( !in_array( $this->format, array( 'yml', 'xml', 'php' ) ) ) {
            return;
        }

        $target = sprintf(
            '%s/Resources/config/routing/%s.%s',
            $this->bundle->getPath(),
            strtolower( str_replace( '\\', '_', $this->entity ) ),
            $this->format
        );

        $this->renderFile(
            'config/routing.'.$this->format.'.twig', $target
            , array(
                'actions'           => $this->actions,
                'route_prefix'      => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
                'bundle'            => $this->bundle->getName(),
                'entity'            => $this->entity,
            )
        );
    }

    /**
     * Generates the controller class only.
     *
     */
    private function generateControllerClass($forceOverwrite = false) {
        $dir = $this->bundle->getPath();
        $parts = explode( '\\', $this->entity );
        $entityClass = array_pop( $parts );
        $entityNamespace = implode( '\\', $parts );

        $target = sprintf(
            '%s/Controller/%s/%sController.php',
            $dir,
            str_replace( '\\', '/', $entityNamespace ),
            $entityClass
        );

        if ( file_exists( $target ) && !$this->overwrite ) {
            throw new \RuntimeException( 'Unable to generate the controller as it already exists.' );
        }

        $this->renderFile(
            'controller/controller.php.twig'
            , $target
            , array(
                'actions'           => $this->actions,
                'route_prefix'      => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
                'bundle'            => $this->bundle->getName(),
                'entity'            => $this->entity,
                'entity_class'      => $entityClass,
                'namespace'         => $this->bundle->getNamespace(),
                'entity_namespace'  => $entityNamespace,
                'format'            => $this->format,
            )
        );
    }

    /**
     * Generates the functional test class only.
     *
     */
    private function generateTestClass($forceOverwrite = false) {
        $parts = explode( '\\', $this->entity );
        $entityClass = array_pop( $parts );
        $entityNamespace = implode( '\\', $parts );

        $dir    = $this->bundle->getPath() .'/Tests/Controller';
        $target = $dir .'/'. str_replace( '\\', '/', $entityNamespace ).'/'. $entityClass .'ControllerTest.php';

        if (!$forceOverwrite && file_exists($target)) {
            throw new \RuntimeException('Unable to generate the test class as it already exists.');
        }

        $this->renderFile(
            'tests/controllerTest.php.twig'
            , $target, array(
            'route_prefix'      => $this->routePrefix,
            'route_name_prefix' => $this->routeNamePrefix,
            'entity'            => $this->entity,
            'bundle'            => $this->bundle->getName(),
            'entity_class'      => $entityClass,
            'namespace'         => $this->bundle->getNamespace(),
            'entity_namespace'  => $entityNamespace,
            'actions'           => $this->actions,
            'form_type_name'    => strtolower(str_replace('\\', '_', $this->bundle->getNamespace()).($parts ? '_' : '').implode('_', $parts).'_'.$entityClass.'Type'),
        ));
    }
}
