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

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Sensio\Bundle\GeneratorBundle\Generator\Generator;

/**
 * Generates a form class based on a Doctrine entity.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Hugo Hamon <hugo.hamon@sensio.com>
 */
class MESDFormGenerator extends Generator
{
    private $filesystem;
    private $skeletonDir;
    private $className;
    private $classPath;

    public function __construct( Filesystem $filesystem, $bundle, $currbundle ) {
        $this->filesystem  = $filesystem;
        $this->setSkeletonDirs(sprintf( '%s/Resources/skeleton/form/', $currbundle->getPath() ) );
        $this->bundle=$bundle;
    }

    // public function __construct( Filesystem $filesystem, $bundle, $currbundle ) {
    //     $this->filesystem = $filesystem;
    //     $this->bundle=$bundle;
    //     $this->setSkeletonDirs($skeletonDir);
    //     // $this->skeletonDir = sprintf( '%s/Resources/skeleton/form/', $currbundle->getPath() );
    // }

    public function getClassName() {
        return $this->className;
    }

    public function getClassPath() {
        return $this->classPath;
    }

    /**
     * Generates the entity form class if it does not exist.
     *
     * @param BundleInterface $bundle   The bundle in which to create the class
     * @param string  $entity   The entity relative class name
     * @param ClassMetadataInfo $metadata The entity metadata class
     */
    public function generate( BundleInterface $bundle, $entity, ClassMetadataInfo $metadata, $forceOverwrite, $stock=false ) {
        $parts       = explode( '\\', $entity );
        $entityClass = array_pop( $parts );

        $this->className = $entityClass.'Type';
        $dirPath         = $bundle->getPath().'/FormType';
        $skelPath        = $bundle->getPath().'/Resources/skeleton/form/FormType.php.twig';
        $this->classPath = $dirPath.'/'.str_replace( '\\', '/', $entity ).'Type.php';

        if ( file_exists( $skelPath ) && !$stock ) {
            $this->skeletonDir=str_replace( "FormType.php.twig", "", $skelPath );
        }

        if ( file_exists( $this->classPath ) && !$forceOverwrite ) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to generate the %s form class as it already exists under the %s file.  Use --overwrite to override.'
                    , $this->className, $this->classPath ) );
        }

        if ( count( $metadata->identifier ) > 1 ) {
            throw new \RuntimeException( 'The form generator does not support entity classes with multiple primary keys.' );
        }

        $parts = explode( '\\', $entity );
        array_pop( $parts );

        $mmmaps=array_filter( array_map(
                function ( $string ) {
                    if ( ""==$string['isOwningSide'] &&
                        8==$string['type'] ) {
                        return $string[key( $string )];
                    }
                    return false;
                },
                $metadata->associationMappings
            )
        );

        $checks=array_filter( array_map(
                function ( $string ) {
                    if ( "boolean"==$string['type'] ) {
                        return $string[key( $string )];
                    }
                    return false;
                },
                $metadata->fieldMappings
            )
        );

        $datetimes=array_filter( array_map(
                function ( $string ) {
                    if ( "datetime"==$string['type'] ) {
                        return $string[key( $string )];
                    }
                    return false;
                },
                $metadata->fieldMappings
            )
        );

        $this->renderFile(
            'FormType.php.twig'
            , $this->classPath
            , array(
                'fields'           => array_diff( $this->
                    getFieldsFromMetadata( $metadata ),
                    array_merge( $mmmaps, $checks, $datetimes ) ),
                'namespace'        => $bundle->getNamespace(),
                'entity_namespace' => implode( '\\', $parts ),
                'entity_class'     => $entityClass,
                'form_class'       => $this->className,
                'form_type_name'   => strtolower( str_replace( '\\', '_', $bundle->getNamespace() ).( $parts ? '_' : '' ).implode( '_', $parts ).'_'.$this->className ),
                'mmmaps'           => $mmmaps,
                'checks'           => $checks,
                'datetimes'        => $datetimes,
            )
        );
        print_r( "Generated" );
        print_r( ( $this->skeletonDir==str_replace( "FormType.php.twig", "", $skelPath ) )?" custom ":" stock " );
        print_r( "FormType for ".$bundle->getName().":$entityClass\n" );
    }


    /**
     * Returns an array of fields. Fields can be both column fields and
     * association fields.
     *
     * @param ClassMetadataInfo $metadata
     * @return array $fields
     */
    private function getFieldsFromMetadata( ClassMetadataInfo $metadata ) {
        $fields = (array) $metadata->fieldNames;

        // Remove the primary key field if it's not managed manually
        if ( !$metadata->isIdentifierNatural() ) {
            $fields = array_diff( $fields, $metadata->identifier );
        }

        foreach ( $metadata->associationMappings as $fieldName => $relation ) {
            if ( $relation['type'] !== ClassMetadataInfo::ONE_TO_MANY ) {
                $fields[] = $fieldName;
            }
        }

        return $fields;
    }
}
