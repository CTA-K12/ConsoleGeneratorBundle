<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Modified by Dave Lighthart to:
// 1.  Render twigs only
// 2.  Use skeletons to render each field in the form, as appropriate
//
// April 03, 2013
//

namespace Mesd\Console\GeneratorBundle\Command;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Sensio\Bundle\GeneratorBundle\Generator\Generator;

/**
 * Generates a CRUD controller.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MesdTwigGenerator extends Generator
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

    /**
     * Constructor.
     *
     * @param Filesystem $filesystem  A Filesystem instance
     * @param string  $skeletonDir Path to the skeleton directory
     */
    // public function __construct(Filesystem $filesystem, $skeletonDir)
    public function __construct( Filesystem $filesystem, $bundle, $currbundle ) {
        $this->filesystem  = $filesystem;
        $this->setSkeletonDirs(sprintf( '%s/Resources/skeleton/twig/', $currbundle->getPath() ) );
        $this->bundle=$bundle;
    }

    /**
     * Generate the CRUD controller.
     *
     * @param BundleInterface $bundle         A bundle object
     * @param string  $entity         The entity relative class name
     * @param ClassMetadataInfo $metadata       The entity class metadata
     * @param string  $format         The configuration format (xml, yaml, annotation)
     * @param string  $routePrefix    The route name prefix
     * @param array   $forceOverWrite Whether or not to overwrite an existing directory
     *
     * @throws \RuntimeException
     */
    public function generate( BundleInterface $bundle, $entity, ClassMetadataInfo $metadata, $format, $forceOverwrite, $stock ) {
        $this->routePrefix = $entity;
        $this->routeNamePrefix = strtolower( str_replace( '/', '_', $this->routePrefix ) );
        $this->actions = array( 'index', 'show', 'new', 'edit' );

        if ( count( $metadata->identifier ) > 1 ) {
            throw new \RuntimeException( 'The CRUD generator does not support entity classes with multiple primary keys.' );
        }

        if ( !in_array( 'id'
                , $metadata->identifier ) ) {
            throw new \RuntimeException( 'The CRUD generator expects the entity object has a primary key field named "id" with a getId() method.' );
        }

        $this->entity   = $entity;
        $this->bundle   = $bundle;
        $this->metadata = $metadata;

        $dir = sprintf( '%s/Resources/views/%s', $this->bundle->getPath(), str_replace( '\\', '/', $this->entity ) );
        $shortdir = sprintf( '%s/Resources/views/%s'
            , str_replace( '\\', '/', $this->bundle->getNameSpace() )
            , str_replace( '\\', '/', $this->entity ) );


        if ( file_exists( $dir ) && !$forceOverwrite ) {
            throw new \RuntimeException( "--overwrite must be used to overwrite an existing directory" );
        }

        if ( !file_exists( $dir ) ) {
            $this->filesystem->mkdir( $dir, 0777 );
        }

        print_r("Generating twigs in $shortdir\n");

        $stockSkeleton = $this->skeletonDir;
        $skelPath = $bundle->getPath().'/Resources/skeleton/twig/index.html.twig';
        if ( file_exists( $skelPath ) && !$stock ) {
            $this->skeletonDir=str_replace( "index.html.twig", "", $skelPath );
        } else {
            $this->skeletonDir=$stockSkeleton;
        }
        $this->generateIndexView( $dir );
        print_r( "Generated" );
        print_r( $this->skeletonDir==$stockSkeleton?" stock ":" custom " );
        print_r( "index.html.twig from $this->skeletonDir\n" );

        $skelPath = $bundle->getPath().'/Resources/skeleton/twig/show.html.twig';
        if ( file_exists( $skelPath ) && !$stock ) {
            $this->skeletonDir=str_replace( "show.html.twig", "", $skelPath );
        } else {
            $this->skeletonDir=$stockSkeleton;
        }
        $this->generateShowView( $dir );
        print_r( "Generated" );
        print_r( $this->skeletonDir==$stockSkeleton?" stock ":" custom " );
        print_r( "show.html.twig from $this->skeletonDir\n" );


        $skelPath = $bundle->getPath().'/Resources/skeleton/twig/new.html.twig';
        if ( file_exists( $skelPath ) && !$stock ) {
            $this->skeletonDir=str_replace( "new.html.twig", "", $skelPath );
        } else {
            $this->skeletonDir=$stockSkeleton;
        }

        $this->generateNewView( $dir );
        print_r( "Generated" );
        print_r( $this->skeletonDir==$stockSkeleton?" stock ":" custom " );
        print_r( "new.html.twig from $this->skeletonDir\n" );

        $skelPath = $bundle->getPath().'/Resources/skeleton/twig/edit.html.twig';
        if ( file_exists( $skelPath ) && !$stock ) {
            $this->skeletonDir=str_replace( "edit.html.twig", "", $skelPath );
        } else {
            $this->skeletonDir=$stockSkeleton;
        }
        $this->generateEditView( $dir );
        print_r( "Generated" );
        print_r( $this->skeletonDir==$stockSkeleton?" stock ":" custom " );
        print_r( "edit.html.twig from $this->skeletonDir\n");
    }

    /**
     * Generates the index.html.twig template in the final bundle.
     *
     * @param string  $dir The path to the folder that hosts templates in the bundle
     */
    private function generateIndexView( $dir ) {

        $this->renderFile(
            'index.html.twig'
            , $dir.'/index.html.twig'
            , array(
                'dir'               => $this->skeletonDir,
                'entity'            => $this->entity,
                'fields'            => $this->metadata->fieldMappings,
                'actions'           => $this->actions,
                'record_actions'    => $this->getRecordActions(),
                'route_prefix'      => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
            )
        );
    }

    /**
     * Generates the show.html.twig template in the final bundle.
     *
     * @param string  $dir The path to the folder that hosts templates in the bundle
     */
    private function generateShowView( $dir ) {
        $maps=array_keys( array_filter( $this->metadata->associationMappings
                , function( $string ) {
                    return ( 1 == $string['isOwningSide'] ||
                        8 == $string['type'] );
                } ) );
        $checks=array_keys( array_filter( $this->metadata->fieldMappings
                , function( $string ) {
                    return 'boolean' == $string['type'];
                } ) );
        $this->renderFile(
            'show.html.twig'
            , $dir.'/show.html.twig'
            , array(
                'dir'                 => $this->skeletonDir
                , 'entity'            => $this->entity
                , 'fields'            => $this->metadata->fieldMappings
                , 'actions'           => $this->actions
                , 'route_prefix'      => $this->routePrefix
                , 'route_name_prefix' => $this->routeNamePrefix
                , 'fieldnames'        => array_diff( $this->metadata->fieldNames, array_merge( $maps, $checks ) )
                , 'maps'              => $maps
                , 'checks'            => $checks
            ) );
    }

    /**
     * Generates the new.html.twig template in the final bundle.
     *
     * @param string  $dir The path to the folder that hosts templates in the bundle
     */
    private function generateNewView( $dir ) {
        $maps=array_keys( array_filter( $this->metadata->associationMappings
                , function( $string ) {
                    return ( 1 == $string['isOwningSide'] ||
                        8 == $string['type'] );
                } ) );
        $checks=array_keys( array_filter( $this->metadata->fieldMappings
                , function( $string ) {
                    return 'boolean' == $string['type'];
                } ) );
        $this->renderFile(
            'new.html.twig'
            , $dir.'/new.html.twig'
            , array(
                'dir'               => $this->skeletonDir,
                'route_prefix'      => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
                'entity'            => $this->entity,
                'actions'           => $this->actions,
                'fieldnames'        => array_diff( $this->metadata->fieldNames,
                    array_merge( $maps, $checks ) ),
                'maps'              => $maps,
                'checks'            => $checks,
            ) );
    }

    /**
     * Generates the edit.html.twig template in the final bundle.
     *
     * @param string  $dir The path to the folder that hosts templates in the bundle
     */
    private function generateEditView( $dir ) {
        $maps=array_keys( array_filter( $this->metadata->associationMappings
                , function( $string ) {
                    return ( 1 == $string['isOwningSide'] ||
                        8 == $string['type'] );
                } ) );
        $checks=array_keys( array_filter( $this->metadata->fieldMappings
                , function( $string ) {
                    return 'boolean' == $string['type'];
                } ) );
        $this->renderFile(
            'edit.html.twig'
            , $dir.'/edit.html.twig'
            , array(
                'dir'               => $this->skeletonDir,
                'route_prefix'      => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
                'entity'            => $this->entity,
                'actions'           => $this->actions,
                'fieldnames'        => array_diff( $this->metadata->fieldNames,
                    array_merge( $maps, $checks ) ),
                'maps'              => $maps,
                'checks'            => $checks,
            ) );
    }

    /**
     * Returns an array of record actions to generate (edit, show).
     *
     * @return array
     */
    private function getRecordActions() {
        return array_filter( $this->actions, function( $item ) {
                return in_array( $item, array( 'show', 'edit' ) );
            } );
    }
}
