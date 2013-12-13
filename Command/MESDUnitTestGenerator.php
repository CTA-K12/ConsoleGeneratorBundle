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

namespace MESD\Console\GeneratorBundle\Command;

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
class MESDUnitTestGenerator
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
    private $spaces = '  ';

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
     * @var array
     */
    private $includedEntities = array();

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
    private static $testTemplate =
'<?php

<testNamespace>

use <entityLN>;
<includes>

<testClassName> extends \PHPUnit_Framework_TestCase
{
<testBody>
}
';
    
    /**
     * @var string
     */
    private static $testConstructTemplate =
'
/**
 *  Tests the constructor for <entity>
 */
public function test<entity>Construct()
{
<spaces>//Create an instance of <entity>
<spaces>$<entityLC> = new <entity>();
<spaces>
<spaces>//Check that the constructor returned an instance
<spaces>$this->assertNotNull($<entityLC>);
}
';

    /**
     * @var string
     */
    private static $testEntityGetSetTemplate =
'/**
 * Tests <entity>\'s get set functions for <fEntity>
 */
public function <methodName>
{
<spaces>//Create an instance of <entity>
<spaces>$<entityLC> = new <entity>();
<spaces>
<spaces>//Set the field
<spaces>$<fEntityLC> = new <fEntityName>();
<spaces>$<entityLC>->set<fEntity>($<fEntityLC>);
<spaces>
<spaces>//Check that it was set
<spaces>$this->assertNotNull($<entityLC>->get<fEntity>());
<spaces>$this->assertInstanceOf(\'<fEntityLN>\', $<entityLC>->get<fEntity>());
}';

    /**
     * @var string
     */
    private static $testEntityManyToManyGetSetTemplate =
'/**
 * Tests <entity>\'s get set functions for <fEntity>
 */
public function <methodName>
{
<spaces>//Create an instance of <entity>
<spaces>$<entityLC> = new <entity>();
<spaces>
<spaces>//Set the field
<spaces>$<fEntityLC> = new <fEntityName>();
<spaces>$<fEntityLC>2 = new <fEntityName>();
<spaces>$<entityLC>->add<fEntity>($<fEntityLC>);
<spaces>$<entityLC>->add<fEntity>($<fEntityLC>2);
<spaces>
<spaces>//Check that it was set
<spaces>$this->assertNotNull($<entityLC>->get<fEntity>());
<spaces>$this->assertInstanceOf(\'<fEntityLN>\', $<entityLC>->get<fEntity>()->toArray()[0]);
<spaces>$this->assertEquals(2, count($<entityLC>->get<fEntity>()->toArray()));
<spaces>
<spaces>//Test remove
<spaces>$<entityLC>->remove<fEntity>($<fEntityLC>2);
<spaces>$this->assertEquals(1, count($<entityLC>->get<fEntity>()->toArray()));
}';

    /**
     * @var string
     */
    private static $testBooleanGetSetTemplate =
'/**
 * Tests <entity>\'s get set functions for <fName>
 */
public function <methodName>
{
<spaces>//Create an instance of <entity>
<spaces>$<entityLC> = new <entity>();
<spaces>
<spaces>//Set the field
<spaces>$<entityLC>->set<fName>(true);
<spaces>
<spaces>//Check that it was set
<spaces>$this->assertNotNull($<entityLC>->get<fName>());
<spaces>$this->assertEquals(\'boolean\', gettype($<entityLC>->get<fName>()));
<spaces>$this->assertTrue($<entityLC>->get<fName>());
}';

    /**
     * @var string
     */
    private static $testIntegerGetSetTemplate =
'/**
 * Tests <entity>\'s get set functions for <fName>
 */
public function <methodName>
{
<spaces>//Create an instance of <entity>
<spaces>$<entityLC> = new <entity>();
<spaces>
<spaces>//Set the field
<spaces>$<entityLC>->set<fName>(5);
<spaces>
<spaces>//Check that it was set
<spaces>$this->assertNotNull($<entityLC>->get<fName>());
<spaces>$this->assertEquals(\'integer\', gettype($<entityLC>->get<fName>()));
<spaces>$this->assertEquals(5, $<entityLC>->get<fName>());
}';

    /**
     * @var string
     */
    private static $testDoubleGetSetTemplate =
'/**
 * Tests <entity>\'s get set functions for <fName>
 */
public function <methodName>
{
<spaces>//Create an instance of <entity>
<spaces>$<entityLC> = new <entity>();
<spaces>
<spaces>//Set the field
<spaces>$<entityLC>->set<fName>(3.1415);
<spaces>
<spaces>//Check that it was set
<spaces>$this->assertNotNull($<entityLC>->get<fName>());
<spaces>$this->assertEquals(\'double\', gettype($<entityLC>->get<fName>()));
<spaces>$this->assertEquals(3.1415, $<entityLC>->get<fName>());
}';

    /**
     * @var string
     */
    private static $testStringGetSetTemplate =
'/**
 * Tests <entity>\'s get set functions for <fName>
 */
public function <methodName>
{
<spaces>//Create an instance of <entity>
<spaces>$<entityLC> = new <entity>();
<spaces>
<spaces>//Set the field
<spaces>$<entityLC>->set<fName>(\'chocolate cake\');
<spaces>
<spaces>//Check that it was set
<spaces>$this->assertNotNull($<entityLC>->get<fName>());
<spaces>$this->assertEquals(\'string\', gettype($<entityLC>->get<fName>()));
<spaces>$this->assertEquals(\'chocolate cake\', $<entityLC>->get<fName>());
}';

    /**
     * @var string
     */
    private static $testDateTimeGetSetTemplate =
'/**
 * Tests <entity>\'s get set functions for <fName>
 */
public function <methodName>
{
<spaces>//Create an instance of <entity>
<spaces>$<entityLC> = new <entity>();
<spaces>
<spaces>//Set the field
<spaces>$now = new \DateTime();
<spaces>$<entityLC>->set<fName>($now);
<spaces>
<spaces>//Check that it was set
<spaces>$this->assertNotNull($<entityLC>->get<fName>());
<spaces>$this->assertInstanceOf(\'\DateTime\', $<entityLC>->get<fName>());
<spaces>$this->assertEquals($now->format(\'Y-m-d H:i:s\'), $<entityLC>->get<fName>()->format(\'Y-m-d H:i:s\'));
}';



    public function __construct()
    {

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
            $this->writeEntityClass($metadata, $outputDirectory);
        }
    }

    /**
     * Generated and write entity class to disk for the given ClassMetadataInfo instance
     *
     * @param ClassMetadataInfo $metadata
     * @param string $outputDirectory
     * @return void
     */
    public function writeEntityClass(ClassMetadataInfo $metadata, $outputDirectory)
    {
        $testName = str_replace('\Entity\\', '\Tests\Entity\\', $metadata->name) . 'Test';
        $path = $outputDirectory . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $testName . $this->extension);
        $dir = dirname($path);

        if ( ! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->isNew = !file_exists($path) || (file_exists($path) && $this->regenerateEntityIfExists);

        if ( ! $this->isNew) {
            $this->parseTokensInEntityFile(file_get_contents($path));
        } else {
            $this->staticReflection[$testName] = array('properties' => array(), 'methods' => array());
        }

        if ($this->backupExisting && file_exists($path)) {
            $backupPath = dirname($path) . DIRECTORY_SEPARATOR . basename($path) . "~";
            if (!copy($path, $backupPath)) {
                throw new \RuntimeException("Attempt to backup overwritten entity file but copy operation failed.");
            }
        }

        // If entity doesn't exist or we're re-generating the entities entirely
        if ($this->isNew) {
            file_put_contents($path, $this->generateTestClass($metadata));
        }
        //If entity exists and we're allowed to update the entity class
        else if ( ! $this->isNew && $this->updateEntityIfExists) {
             file_put_contents($path, $this->generateUpdatedTestClass($metadata, $path));
        }
    }

    /**
     * Generate a PHP5 Doctrine 2 entity class from the given ClassMetadataInfo instance
     *
     * @param ClassMetadataInfo $metadata
     * @return string $code
     */
    public function generateTestClass(ClassMetadataInfo $metadata)
    {
        $this->includedEntities[] = $this->generateEntityPath($metadata);

        $placeHolders = array(
            '<testNamespace>',
            '<entityLN>',
            '<includes>',
            '<testClassName>',
            '<testBody>'
        );

        $replacements = array(
            $this->generateTestNamespace($metadata),
            $this->generateEntityPath($metadata),
            $this->generateIncludes($metadata),
            $this->generateTestClassName($metadata),
            $this->generateTestBody($metadata)
        );
        $code = str_replace($placeHolders, $replacements, self::$testTemplate);

        return str_replace('<spaces>', $this->spaces, $code);
    }

    /**
     * @todo this won't work if there is a namespace in brackets and a class outside of it.
     * @param string $src
     */
    private function parseTokensInEntityFile($src)
    {
        $tokens = token_get_all($src);
        $lastSeenNamespace = "";
        $lastSeenClass = false;

        $inNamespace = false;
        $inClass = false;
        $inUse = false;
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT))) {
                continue;
            }

            if ($inNamespace) {
                if ($token[0] == T_NS_SEPARATOR || $token[0] == T_STRING) {
                    $lastSeenNamespace .= $token[1];
                } else if (is_string($token) && in_array($token, array(';', '{'))) {
                    $inNamespace = false;
                }
            }

            if ($inClass) {
                $inClass = false;
                $lastSeenClass = $lastSeenNamespace . ($lastSeenNamespace ? '\\' : '') . $token[1];
                $this->staticReflection[$lastSeenClass]['properties'] = array();
                $this->staticReflection[$lastSeenClass]['methods'] = array();
            }

            if ($inUse) {
                if ($token[0] == T_NS_SEPARATOR || $token[0] == T_STRING) {
                    $includeEntity .= $token[1];
                } else if (is_string($token) && in_array($token, array(';', '{'))) {
                    $inUse = false;
                    $this->includedEntities[] = $includeEntity;
                }
            } else if ($token[0] == T_NAMESPACE) {
                $lastSeenNamespace = "";
                $inNamespace = true;
            } else if ($token[0] == T_CLASS) {
                $inClass = true;
            } else if ($token[0] == T_FUNCTION) {
                if ($tokens[$i+2][0] == T_STRING) {
                    $this->staticReflection[$lastSeenClass]['methods'][] = $tokens[$i+2][1];
                } else if ($tokens[$i+2] == "&" && $tokens[$i+3][0] == T_STRING) {
                    $this->staticReflection[$lastSeenClass]['methods'][] = $tokens[$i+3][1];
                }
            } else if (in_array($token[0], array(T_VAR, T_PUBLIC, T_PRIVATE, T_PROTECTED)) && $tokens[$i+2][0] != T_FUNCTION) {
                $this->staticReflection[$lastSeenClass]['properties'][] = substr($tokens[$i+2][1], 1);
            } else if ($token[0] == T_USE) {
                $inUse = true;
                $includeEntity = '';
            }
        }
    }

    private function hasProperty($property, ClassMetadataInfo $metadata)
    {
        if ($this->extendsClass()) {
            // don't generate property if its already on the base class.
            $reflClass = new \ReflectionClass($this->getClassToExtend());
            if ($reflClass->hasProperty($property)) {
                return true;
            }
        }

        return (
            isset($this->staticReflection[$metadata->name]) &&
            in_array($property, $this->staticReflection[$metadata->name]['properties'])
        );
    }

    private function hasMethod($method, ClassMetadataInfo $metadata)
    {
        if ($this->extendsClass()) {
            // don't generate method if its already on the base class.
            $reflClass = new \ReflectionClass($this->getClassToExtend());
            if ($reflClass->hasMethod($method)) {
                return true;
            }
        }

        $testName = str_replace('\Entity\\', '\Tests\Entity\\', $metadata->name) . 'Test';

        return (
            isset($this->staticReflection[$testName]) &&
            in_array($method, $this->staticReflection[$testName]['methods'])
        );
    }

    /**
     * Generate the updated code for the given ClassMetadataInfo and entity at path
     *
     * @param ClassMetadataInfo $metadata
     * @param string $path
     * @return string $code;
     */
    public function generateUpdatedTestClass(ClassMetadataInfo $metadata, $path)
    {
        $currentCode = file_get_contents($path);

        $body = $this->generateTestBody($metadata);
        $includes = $this->generateIncludes($metadata);
        $body = str_replace('<spaces>', $this->spaces, $body);
        $startClass = stripos($currentCode, 'Class');
        $last = strrpos($currentCode, '}');

        return substr($currentCode, 0, $startClass) . $includes . (strlen($includes) > 0 ? "\n\n" : '') 
            . substr($currentCode, $startClass, $last - $startClass) . $body . (strlen($body) > 0 ? "\n" : '') . "}";
    }

    /**
     * Set the number of spaces the exported class should have
     *
     * @param integer $numSpaces
     * @return void
     */
    public function setNumSpaces($numSpaces)
    {
        $this->spaces = str_repeat(' ', $numSpaces);
        $this->numSpaces = $numSpaces;
    }

    /**
     * Set the extension to use when writing php files to disk
     *
     * @param string $extension
     * @return void
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;
    }

    /**
     * Set the name of the class the generated classes should extend from
     *
     * @return void
     */
    public function setClassToExtend($classToExtend)
    {
        $this->classToExtend = $classToExtend;
    }

    /**
     * Set whether or not to generate annotations for the entity
     *
     * @param bool $bool
     * @return void
     */
    public function setGenerateAnnotations($bool)
    {
        $this->generateAnnotations = $bool;
    }

    /**
     * Set the class fields visibility for the entity (can either be private or protected)
     *
     * @param bool $bool
     * @return void
     */
    public function setFieldVisibility($visibility)
    {
        if ($visibility !== self::FIELD_VISIBLE_PRIVATE && $visibility !== self::FIELD_VISIBLE_PROTECTED) {
            throw new \InvalidArgumentException('Invalid provided visibilty (only private and protected are allowed): ' . $visibility);
        }

        $this->fieldVisibility = $visibility;
    }

    /**
     * Set an annotation prefix.
     *
     * @param string $prefix
     */
    public function setAnnotationPrefix($prefix)
    {
        $this->annotationsPrefix = $prefix;
    }

    /**
     * Set whether or not to try and update the entity if it already exists
     *
     * @param bool $bool
     * @return void
     */
    public function setUpdateEntityIfExists($bool)
    {
        $this->updateEntityIfExists = $bool;
    }

    /**
     * Set whether or not to regenerate the entity if it exists
     *
     * @param bool $bool
     * @return void
     */
    public function setRegenerateEntityIfExists($bool)
    {
        $this->regenerateEntityIfExists = $bool;
    }

    /**
     * Set whether or not to generate stub methods for the entity
     *
     * @param bool $bool
     * @return void
     */
    public function setGenerateStubMethods($bool)
    {
        $this->generateEntityStubMethods = $bool;
    }

    /**
     * Should an existing entity be backed up if it already exists?
     */
    public function setBackupExisting($bool)
    {
        $this->backupExisting = $bool;
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

     /**
     * @param   string $type
     * @return  string
     */
    private function getType($type)
    {
        if (isset($this->typeAlias[$type])) {
            return $this->typeAlias[$type];
        }

        return $type;
    }

    private function generateTestNamespace(ClassMetadataInfo $metadata)
    {
        return str_replace('\Entity', '\Tests\Entity', 'namespace ' . $this->getNamespace($metadata) .';');
    }

    private function generateEntityPath(ClassMetadataInfo $metadata)
    {
        return $metadata->name;
    }

    private function generateTestClassName(ClassMetadataInfo $metadata)
    {
        return 'class ' . $this->getClassName($metadata) . 'Test';
    }

    private function generateIncludes(CLassMetadataInfo $metadata) 
    {
        $lines = array();
        foreach ($metadata->associationMappings as $associationMapping) {
            if (! in_array($associationMapping['targetEntity'], $this->includedEntities)) {
                $lines[] = 'use ' . $associationMapping['targetEntity'] . ';';
                $this->includedEntities[] = $associationMapping['targetEntity'];
            }
        }

        return implode("\n", $lines);
    }

    private function generateTestBody(ClassMetadataInfo $metadata)
    {
        $code = array();

        $code[] = $this->generateTestConstructor($metadata);
        $code[] = $this->generateTestStubMethods($metadata);

        return implode("\n", $code);
    }

    private function generateTestConstructor(ClassMetadataInfo $metadata) {
        if ($this->hasMethod('test' . substr(strrchr($metadata->name, '\\'), 1) . 'Construct', $metadata)) {
            return;
        }

        $placeHolders = array(
            '<entity>',
            '<entityLC>'
        );

        $replacements = array(
            substr(strrchr($metadata->name, '\\'), 1),
            lcfirst(substr(strrchr($metadata->name, '\\'), 1))
        );
        $code = str_replace($placeHolders, $replacements, self::$testConstructTemplate);
        return $this->prefixCodeWithSpaces($code);
    }

    private function generateTestStubMethods(ClassMetadataInfo $metadata)
    {
        $methods = array();
        foreach ($metadata->fieldMappings as $fieldMapping) {
            if ($code = $this->generateTestStubMethod($metadata, $fieldMapping['fieldName'], $fieldMapping['type'])) {
                $methods[] = $code;
            }
        }

        foreach ($metadata->associationMappings as $associationMapping) {
            if ($code = $this->generateTestStubMethod($metadata, $associationMapping['fieldName'], $associationMapping['targetEntity'])) {
                $methods[] = $code;
            }
        }

        return implode("\n\n", $methods);
    }

    private function prefixCodeWithSpaces($code, $num = 1)
    {
        $lines = explode("\n", $code);

        foreach ($lines as $key => $value) {
            $lines[$key] = str_repeat($this->spaces, $num) . $lines[$key];
        }

        return implode("\n", $lines);
    }

    private function generateTestStubMethod(ClassMetadataInfo $metadata, $fieldName, $typeHint)
    {
        //Ignore the id field
        if ($fieldName == 'id') {
            return;
        }

        $capAfterUS = function ($m) {
                return strtoupper($m[1]);
            };
        $cleanedFieldName = preg_replace_callback('/(?:^|_)([a-z])/', $capAfterUS, $fieldName);

        $methodName = 'testGetSet' . ucfirst($cleanedFieldName);

        if ($this->hasMethod($methodName, $metadata)) {
            return;
        }
        $this->staticReflection[$metadata->name]['methods'][] = $methodName;

        //Get the template
        if ($this->getType($typeHint) == 'string') {
            $template = self::$testStringGetSetTemplate;
            $type = 'primative';
        } 
        else if ($this->getType($typeHint) == 'integer') {
            $template = self::$testIntegerGetSetTemplate;
            $type = 'primative';
        }
        else if ($this->getType($typeHint) == 'boolean') {
            $template = self::$testBooleanGetSetTemplate;
            $type = 'primative';
        }
        else if ($this->getType($typeHint) == 'float') {
            $template = self::$testDoubleGetSetTemplate;
            $type = 'primative';
        }
        else if ($this->getType($typeHint) == '\DateTime') {
            $template = self::$testDateTimeGetSetTemplate;
            $type = 'primative';  //Yeah, I know this is wrong looking, but date time is a primative from the db side
        }
        else {
            if ($metadata->associationMappings[$fieldName]['type'] == $metadata::MANY_TO_MANY ||
                    $metadata->associationMappings[$fieldName]['type'] == $metadata::ONE_TO_MANY) {
                $template = self::$testEntityManyToManyGetSetTemplate;
            }
            else {
                $template = self::$testEntityGetSetTemplate;
            }
            $type = 'object';
        }

        if ($type == 'object') {
            $replacements = array(
                '<methodName>'  => $methodName . '()',
                '<entity>'      => substr(strrchr($metadata->name, '\\'), 1),
                '<fEntity>'     => ucfirst($cleanedFieldName),
                '<entityLC>'    => lcfirst(substr(strrchr($metadata->name, '\\'), 1)),
                '<fEntityLC>'   => $cleanedFieldName,
                '<fEntityLN>'   => $this->getType($typeHint),
                '<fEntityName>' => substr(strrchr($this->getType($typeHint), '\\'), 1)
            );
        }
        else {
            $replacements = array(
                '<methodName>'  => $methodName . '()',
                '<entity>'      => substr(strrchr($metadata->name, '\\'), 1),
                '<fName>'       => ucfirst($cleanedFieldName),
                '<entityLC>'    => lcfirst(substr(strrchr($metadata->name, '\\'), 1))
            );
        }

        $method = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        return $this->prefixCodeWithSpaces($method);
    }

}
