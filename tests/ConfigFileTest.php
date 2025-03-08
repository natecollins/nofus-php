<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/ConfigFile.php';

use PHPUnit\Framework\TestCase;
use Nofus\ConfigFile;

final class ConfigFileTest extends TestCase {

    public function testCanLoadFile(): void {
        $cf = new ConfigFile(__DIR__ . DIRECTORY_SEPARATOR . 'test1.conf');
        $this->assertInstanceOf(ConfigFile::class, $cf);
        $bLoaded = $cf->load();
        if (!$bLoaded) {
            foreach ($cf->errors() as $sError) {
                echo " >> {$sError}" . PHP_EOL;
            }
        }
        $this->assertSame(true, $bLoaded);
    }

    public function testCanParseAllValues(): void {
        $cf = new ConfigFile(__DIR__ . DIRECTORY_SEPARATOR . 'test1.conf');
        $cf->preload(
            [
                "non-var"   => "doesn't exist",
                "var1"      => "get's overridden"
            ]
        );
        $cf->load();
        $this->assertEquals(    "doesn't exist",                            $cf->get("non-var"));
        $this->assertNull(      $cf->get('badvar1'));
        $this->assertNull(      $cf->get('badvar2'));
        $this->assertEquals(    'default val',                              $cf->get('invalid.var', 'default val'));
        $this->assertEquals(    42,                                         $cf->get('var1'));
        $this->assertEquals(    92,                                         $cf->get('var2'));
        $this->assertEquals(    [92],                                       $cf->getArray('var2'));
        $this->assertEquals(    'a string',                                 $cf->get('var_3'));
        $this->assertEquals(    'quoted string',                            $cf->get('VAR-4'));
        $this->assertEquals(    'Mis "quoted" string',                      $cf->get('_VAR5_'));
        $this->assertEquals(    'techinally valid var name',                $cf->get('-'));
        $this->assertEquals(    'also valid var name',                      $cf->get('_'));
        $this->assertEquals(    'Yet another  valid name',                  $cf->get('99'));
        $this->assertEquals(    '  spaced val  ',                           $cf->get('var6'));
        $this->assertEquals(    '"quoted quotes"',                          $cf->get('var7'));
        $this->assertEquals(    'quoted string # in value',                 $cf->get('var8'));
        $this->assertEquals(    '"start quoted" but not ended',             $cf->get('var9'));
        $this->assertEquals(    'special chars # \\\\ = inside string',     $cf->get('var10'));
        $this->assertEquals(    '',                                         $cf->get('var11'));
        $this->assertTrue(      $cf->get('var12'));
        $this->assertEquals(    'abc',                                      $cf->get('multi-var13'));
        $this->assertEquals(    ['abc','pqr','xyz'],                        $cf->getArray('multi-var13'));
        $this->assertEquals(    'non quoted start with "quoted end"',       $cf->get('var14'));
        $this->assertEquals(    '2',                                        $cf->get('marbles.green'));
        $this->assertEquals(    '6',                                        $cf->get('marbles.white'));
        $this->assertEquals(    '1',                                        $cf->get('marbles.yellow'));
        $cfMarbles = $cf->get('marbles');
        $this->assertEquals(    '4',                                        $cfMarbles->get('blue'));
        $this->assertEquals(    '3',                                        $cfMarbles->get('red'));
        $this->assertEquals(    '8',                                        $cfMarbles->get('clear'));
        $this->assertNull(      $cf->get('scope'));
        $this->assertEquals(    ['server','user','pw','db'],                $cf->enumerateScope('sql.maria.auth'));
        $cfAuth = $cf->get('sql.maria.auth');
        $this->assertEquals(    ['server','user','pw','db'],                $cfAuth->enumerateScope());
        $this->assertEquals(    'sql.example.com',                          $cfAuth->get('server'));
        $this->assertEquals(    'apache',                                   $cfAuth->get('user'));
        $this->assertEquals(    'secure',                                   $cf->get('sql.maria.auth.pw'));
        $cfMaria = $cf->get('sql.maria');
        $this->assertEquals(    'website',                                  $cfMaria->get('auth.db'));
        $this->assertEquals(    'a thing',                                  $cf->get('var15'));
        $this->assertEquals(    'white space before var',                   $cf->get('var16'));
        $this->assertNull(      $cf->get('same'));

        $this->assertIsArray(   $cf->getArray('invalid.name'));
        $this->assertEmpty(     $cf->getArray('invalid.name'));
    }

}
