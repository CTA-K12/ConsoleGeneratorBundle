<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Mesd\Console\GeneratorBundle\Command;

use Doctrine\ORM\Mapping\ClassMetadataInfo,
    Doctrine\Common\Util\Inflector,
    Doctrine\DBAL\Types\Type;

/**
 * Generic class used to generate PHP5 entity classes from ClassMetadataInfo instances
 *
 *     [php]
 *     $classes = $em->getClassMetadataFactory()->getAllMetadata();
 *
 *     $generator = new \Doctrine\ORM\Tools\EntityGenerator();
 *     $generator->setGenerateAnnotations(true);
 *     $generator->setGenerateStubMethods(true);
 *     $generator->setRegenerateEntityIfExists(false);
 *     $generator->setUpdateEntityIfExists(true);
 *     $generator->generate($classes, '/path/to/generate/entities');
 *
 *
 * @link    www.doctrine-project.org
 * @since   2.0
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 */
class MesdGridGenerator
{
    /**
     * Specifies class fields should be protected
     */
    const FIELD_VISIBLE_PROTECTED = 'protected';

    /**
     * Specifies class fields should be private
     */
    const FIELD_VISIBLE_PRIVATE = 'private';

    /**
     * @var bool
     */
    private $backupExisting = true;

    /**
     * The extension to use for written php files
     *
     * @var string
     */
    private $extension = '.php';

    /**
     * Whether or not the current ClassMetadataInfo instance is new or old
     *
     * @var boolean
     */
    private $isNew = true;

    /**
     * @var array
     */
    private $staticReflection = array();

    /**
     * Number of spaces to use for indention in generated code
     */
    private $numSpaces = 2;

    /**
     * The actual spaces to use for indention
     *
     * @var string
     */
    private $spaces = '    ';

    /**
     * The class all generated entities should extend
     *
     * @var string
     */
    private $classToExtend;

    /**
     * Whether or not to generation annotations
     *
     * @var boolean
     */
    private $generateAnnotations = false;

    /**
     * @var string
     */
    private $annotationsPrefix = '';

    /**
     * Whether or not to generated sub methods
     *
     * @var boolean
     */
    private $generateEntityStubMethods = false;

    /**
     * Whether or not to update the entity class if it exists already
     *
     * @var boolean
     */
    private $updateEntityIfExists = false;

    /**
     * Whether or not to re-generate entity class if it exists already
     *
     * @var boolean
     */
    private $regenerateEntityIfExists = false;

    /**
     * @var boolean
     */
    private $fieldVisibility = 'private';

    /**
     * Hash-map for handle types
     *
     * @var array
     */
    private $typeAlias = array(
        Type::DATETIMETZ    => '\DateTime',
        Type::DATETIME      => '\DateTime',
        Type::DATE          => '\DateTime',
        Type::TIME          => '\DateTime',
        Type::OBJECT        => '\stdClass',
        Type::BIGINT        => 'integer',
        Type::SMALLINT      => 'integer',
        Type::TEXT          => 'string',
        Type::BLOB          => 'string',
        Type::DECIMAL       => 'float',
        Type::JSON_ARRAY    => 'array',
        Type::SIMPLE_ARRAY  => 'array',
    );

    /**
     * @var string
     */
    private static $classTemplate =
'<?php

<namespace>

use Doctrine\ORM\Mapping as ORM;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\SecurityContext;

use APY\DataGridBundle\Grid\Source\Entity;
use Mesd\DoctrineExtensions\WalkerBundle\Walker\IlikeWalker;
use APY\DataGridBundle\Grid\Export\CSVExport;
use APY\DataGridBundle\Grid\Export\ExcelExport;
use APY\DataGridBundle\Grid\Action\RowAction;
use APY\DataGridBundle\Grid\Source\Vector;
use APY\DataGridBundle\Grid\Column;
use APY\DataGridBundle\Grid\Grid;

<entityClassName>Grid extends Grid {

<spaces>private $em;

<spaces>public function __construct($em){
<spaces><spaces>$this->em=$em;
<spaces>}

<spaces>public function makeGrid($grd){
<spaces><spaces>$source = new Entity(\'<entityBundleName>\');
<spaces><spaces>$grd->setSource($source);
<spaces><spaces>//$grd->setPersistence(true);
<gridBody>
<spaces>}
}
';

    private static $fieldColumn =
'/**
 * Default Column Setting.  Customize to suit
 */
<spaces><spaces>$grd->getColumn(\'<Column>\')
<spaces><spaces>->setTitle(\'<Column>\')
<spaces><spaces>//->setSize(-1)
<spaces><spaces>//<spaces>autosizing default, otherwis set pixels
<spaces><spaces>//<spaces>default is ORM type
<spaces><spaces>//->setFormat(\'Y/m/d\')
<spaces><spaces>// date and datetime
<spaces><spaces>// by default date displays midnight
<spaces><spaces>// use format above for just date,
<spaces><spaces>// or change to suit
<spaces><spaces>//->setAlign(\'left\')
<spaces><spaces>//options left/right/center
<spaces><spaces>//->setFilterType(\'input\')
<spaces><spaces>//->setFilterType(\'select\')
<spaces><spaces>//<spaces>Not all column types support filter
<spaces><spaces>//->setSortable(false)
<spaces><spaces>->setFilterable(false)
<spaces><spaces>//->setVisible(false)
<spaces><spaces>//->setOperators()
<spaces><spaces>//->setDefaultOperator()
<spaces><spaces>//<spaces>review documentation
<spaces><spaces>//->setOperatorsVisible(false)
<spaces><spaces>//->setOrder(\'asc\')
<spaces><spaces>//->setInputType(\'string\')
<spaces><spaces>//->setRole(\'security restriction\')
<spaces><spaces>//->selectFrom(\'source\')
<spaces><spaces>//<spaces>populate selector, via source, query or values
<spaces><spaces>//->setSearchOnClick(\'false\')
<spaces><spaces>//<spaces>true will inhibit drill down with code modifications
<spaces><spaces>//->setSafe(\'js\')
<spaces><spaces>//<spaces>twig filters: raw, html, js, css, url, html_attr
<spaces><spaces>;
';

    private static $associationColumn =
'/**
 * Default Association Column Setting.  Customize to suit
 */
<spaces><spaces>$<Column> = new Column\TextColumn(
<spaces><spaces><spaces><spaces>array(
<spaces><spaces><spaces><spaces><spaces>\'id\'       => \'<Column>\'
<spaces><spaces><spaces><spaces><spaces>, \'field\'  => \'<Column>.id\'
<spaces><spaces><spaces><spaces><spaces>, \'source\' => true
<spaces><spaces><spaces><spaces><spaces>// change this default
<spaces><spaces><spaces><spaces>)
<spaces><spaces>);

<spaces><spaces>$<Column>->setTitle(\'<Column>\')
<spaces><spaces>//->setSize(-1)
<spaces><spaces>//<spaces>autosizing default, otherwise set pixels
<spaces><spaces>//->setAlign(\'left\')
<spaces><spaces>//<spaces>options left/right/center
<spaces><spaces>//->setFilterType(\'input\')
<spaces><spaces>//->setFilterType(\'select\')
<spaces><spaces>//<spaces>Not all column types support filter
<spaces><spaces>//->setSortable(false)
<spaces><spaces>->setFilterable(false)
<spaces><spaces>//->setVisible(false)
<spaces><spaces>//->setOperators()
<spaces><spaces>//->setDefaultOperator()
<spaces><spaces>//<spaces>review documentation
<spaces><spaces>//->setOperatorsVisible(false)
<spaces><spaces>//->setOrder(\'asc\')
<spaces><spaces>//->setInputType(\'string\')
<spaces><spaces>//->setRole(\'security restriction\')
<spaces><spaces>//->selectFrom(\'source\')
<spaces><spaces>//<spaces>populate selector, via source, query or values
<spaces><spaces>//->setSearchOnClick(\'false\')
<spaces><spaces>//<spaces>true will inhibit drill down with code modifications
<spaces><spaces>//->setSafe(\'js\')
<spaces><spaces>//<spaces>twig filters: raw, html, js, css, url, html_attr
<spaces><spaces>;

<spaces><spaces>$grd->getColumns()->addColumn($<Column>);
';

    private static $gridFinal =
'

<spaces><spaces>$search
<spaces><spaces>= new Column\TextColumn(
<spaces><spaces><spaces>array(
<spaces><spaces><spaces><spaces>\'id\'                 => \'search\'
<spaces><spaces><spaces><spaces>, \'title\'            => \'Search\'
<spaces><spaces><spaces><spaces>, \'filter\'           => \'input\'
<spaces><spaces><spaces><spaces>, \'filterable\'       => true
<spaces><spaces><spaces><spaces>, \'operatorsVisible\' => false
<spaces><spaces><spaces><spaces>, \'visible\' => false
<spaces><spaces><spaces><spaces>)
<spaces><spaces>);
<spaces><spaces>$grd->getColumns()->addColumn( $search );
<spaces><spaces>$grd->showColumns(array(<ColumnStack>
<spaces><spaces>));
<spaces><spaces>// sort as needed
<spaces><spaces>$grd->setColumnsOrder(array(<ColumnStack>
<spaces><spaces>));
<spaces><spaces>$grd->setActionsColumnSize(80);
<spaces><spaces>// List the ones to be hidden here
<spaces><spaces>// Can also use ->setVisible(false) on each column
<spaces><spaces>$grd->hideColumns(array(
<spaces><spaces>));
<spaces><spaces>$grd->setPermanentFilters(array());
<spaces><spaces>$grd->setDefaultFilters(array());
<spaces><spaces>$grd->setPersistence(true);
<spaces><spaces>$grd->setLimits(array(15, 25, 50, 100));
<spaces><spaces>$grd->addExport(new CSVExport(\'CSV Export\'));
<spaces><spaces>$grd->addExport(new ExcelExport(\'Excel Export\'));

<spaces><spaces>$showAction = new RowAction(\'Show\'
<spaces><spaces><spaces>, \'<entityclassname>_show\'
<spaces><spaces><spaces>, false
<spaces><spaces><spaces>, \'_self\'
<spaces><spaces><spaces>, array(\'class\' => \'btn btn-info action icon-eye-open\')
<spaces><spaces>);
<spaces><spaces>$showAction->setRouteParameters(array(\'id\'));
<spaces><spaces>$grd->addRowAction($showAction);

<spaces><spaces>$editAction = new RowAction(\'Edit\'
<spaces><spaces><spaces>, \'<entityclassname>_edit\'
<spaces><spaces><spaces>, false
<spaces><spaces><spaces>, \'_self\'
<spaces><spaces><spaces>, array(\'class\' => \'btn btn-info action icon-pencil\')
<spaces><spaces>);
<spaces><spaces>$editAction->setRouteParameters(array(\'id\'));
<spaces><spaces>$grd->addRowAction($editAction);

<spaces><spaces>$deleteAction = new RowAction(\'Delete\'
<spaces><spaces><spaces>, \'<entityclassname>_delete\'
<spaces><spaces><spaces>, false
<spaces><spaces><spaces>, \'_self\'
<spaces><spaces><spaces>, array(\'class\' => \'btn btn-info action icon-remove\')
<spaces><spaces>);
<spaces><spaces>$deleteAction->setRouteParameters(array(\'id\'));
<spaces><spaces>$grd->addRowAction($deleteAction);
';



    public function __construct()
    {
        if (version_compare(\Doctrine\Common\Version::VERSION, '2.2.0-DEV', '>=')
        ) {
            $this->annotationsPrefix = 'ORM\\';
        }
    }

    public function setBackupExisting($bool)
    {
        $this->backupExisting = $bool;
    }

    /**
     * Generate and write entity classes for the given array of ClassMetadataInfo instances
     *
     * @param array $metadatas
     * @param string $outputDirectory
     * @return void
     */
    public function generate(array $metadatas, $outputDirectory)
    {
        foreach ($metadatas as $metadata) {
            $this->writeGridClass($metadata, $outputDirectory);
        }
    }

    /**
     * Generated and write entity class to disk for the given ClassMetadataInfo instance
     *
     * @param ClassMetadataInfo $metadata
     * @param string $outputDirectory
     * @return void
     */
    public function writeGridClass(ClassMetadataInfo $metadata, $outputDirectory) {
        $name=str_replace('Entity', 'Grid', $metadata->name);
        $path = $outputDirectory . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $name).'Grid' . $this->extension;
        $dir = dirname($path);

        if ( ! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->isNew = !file_exists($path) || (file_exists($path) && $this->regenerateEntityIfExists);

        if ($this->backupExisting && file_exists($path)) {
            $backupPath = dirname($path) . DIRECTORY_SEPARATOR . basename($path) . "~";
            if (!copy($path, $backupPath)) {
                throw new \RuntimeException("Attempt to backup overwritten entity file but copy operation failed.");
            }
        }

        file_put_contents($path, $this->generateGridClass($metadata));
    }

    /**
     * Generate a PHP5 Doctrine 2 entity class from the given ClassMetadataInfo instance
     *
     * @param ClassMetadataInfo $metadata
     * @return string $code
     */
    public function generateGridClass(ClassMetadataInfo $metadata)
    {

        $placeHolders = array(
            '<namespace>'
            ,'<entityBundleName>'
            ,'<entityClassName>'
            ,'<entityclassname>'
            ,'<gridBody>'
        );

        $replacements = array(
            $this->generateEntityNamespace($metadata)
            ,$this->generateEntityBundleName($metadata)
            ,$this->generateEntityClassName($metadata)
            ,strtolower($this->getClassName($metadata))
            ,$this->generateGridBody($metadata)
        );
        $code = str_replace($placeHolders, $replacements, self::$classTemplate);

        return str_replace('<spaces>', $this->spaces, $code);
    }

    public function generateGridBody(ClassMetadataInfo $metadata) {
        $fields = $metadata->getFieldNames();
        $associations = array_keys($metadata->getAssociationMappings());
        $columns = array_merge($fields, $associations);
        $code = array();

        foreach ($fields as $key => $field){
            $code[] = str_replace('<Column>', $field, self::$fieldColumn);
        }

        foreach ($associations as $key => $association){
            $code[] = str_replace('<Column>', $association, self::$associationColumn);
        }

        $code[] = str_replace(
            array('<Columns>','<ColumnStack>','<entityclassname>')
            , array(
                 "'".implode("','", $columns)."'"
                ,"\n        '".implode("'\n        ,'", $columns)."'"
                ,strtolower($this->getClassName($metadata))
                )
            , self::$gridFinal);

        return implode("\n", $code);
    }

    private function generateEntityNamespace(ClassMetadataInfo $metadata)
    {
        if ($this->hasNamespace($metadata)) {
            return str_replace('Entity','Grid','namespace ' . $this->getNamespace($metadata) .';');
        }
    }

    private function generateEntityBundleName(ClassMetadataInfo $metadata)
    {
        return str_replace(
            array('\\','Entity')
            ,array('',':')
            ,$this->getNameSpace($metadata)
            )
        .$this->getClassName($metadata)
        ;
    }

    private function generateEntityClassName(ClassMetadataInfo $metadata)
    {
        return 'class ' . $this->getClassName($metadata) .
            ($this->extendsClass() ? ' extends ' . $this->getClassToExtendName() : null);
    }


    private function hasNamespace(ClassMetadataInfo $metadata)
    {
        return strpos($metadata->name, '\\') ? true : false;
    }

    private function extendsClass()
    {
        return $this->classToExtend ? true : false;
    }

    private function getClassToExtend()
    {
        return $this->classToExtend;
    }

    private function getClassToExtendName()
    {
        $refl = new \ReflectionClass($this->getClassToExtend());

        return '\\' . $refl->getName();
    }

    private function getClassName(ClassMetadataInfo $metadata)
    {
        return ($pos = strrpos($metadata->name, '\\'))
            ? substr($metadata->name, $pos + 1, strlen($metadata->name)) : $metadata->name;
    }

    private function getNamespace(ClassMetadataInfo $metadata)
    {
        return substr($metadata->name, 0, strrpos($metadata->name, '\\'));
    }
}
