<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/DBConnect.php';

use PHPUnit\Framework\TestCase;
use Nofus\DBConnect;
use Nofus\DBException;

function dbauth() {
    $SERVERS = array(
        array(
            'host'=>'localhost',
            'username'=>'nofus_test',
            'password'=>'nofus_test',
            'database'=>'nofus_test',
            'port'=>3306
        )
    );
    return $SERVERS;
}

final class DBConnectTest extends TestCase {
    public function testNoConnInfo(): void {
        $this->expectException(DBException::class);
        $this->expectExceptionMessage("No DB valid server authentication provided.");
        $db = new DBConnect([], true);
    }

    public function testBadConnInfo(): void {
        $dba = dbauth();
        $dba[0]['database'] = 'invalid_db_name';
        $db = new DBConnect($dba, true);
        $this->expectException(DBException::class);
        $this->expectExceptionMessage("Unable to connect to database.");
        $this->assertEquals('nofus_test', $db->getDatabaseName());
    }

    public function testBasicConnection(): void {
        $db = new DBConnect(dbauth(), true);

        $this->assertFalse($db->connectionExists());
        $this->assertEquals('nofus_test', $db->getDatabaseName());
        $this->assertEquals('localhost', $db->getHost());
        $this->assertTrue($db->connectionExists());
    }

    public function testBasicQueries(): void {
        $db = new DBConnect(dbauth(), true);

        $rows = $db->query("SELECT * FROM table1");
        $this->assertTrue($db->connectionExists());
        $this->assertCount(2, $rows);

        $row = $db->queryRow("SELECT id, val1, val2 FROM table2");
        $this->assertCount(3, $row);

        $col_vals = $db->queryColumn("SELECT val1 FROM table2");
        $this->assertEquals([10,20], $col_vals);
    }

    public function testDBInspection(): void {
        $db = new DBConnect(dbauth(), true);

        $this->assertEquals(['table1','table2'],$db->getTables());
        $cols = array_column($db->getTableColumns('table2'),'name');
        sort($cols);
        $this->assertEquals(['id','idx','val1','val2'],$cols);
        $cols = $db->getAllColumns();
        sort($cols);
        $this->assertEquals(
            ['created','e_val','id','idx','val1','val2'],
            $cols
        );
        $e_vals = $db->enumValues('table1', 'e_val');
        sort($e_vals);
        $this->assertEquals(['first','second','third'],$e_vals);
        $this->assertEquals('table1',$db->escapeIdentifier('table1', false));
        $this->assertEquals('`table2`',$db->escapeIdentifier('table2'));
        $this->assertEquals('`e_val`',$db->escapeIdentifier('e_val'));
        $this->assertEquals('', $db->escapeIdentifier('incorrect'));
    }

    public function testTransactions(): void {
        $db = new DBConnect(dbauth(), true);

        $db->startTransaction();
        $iId = $db->query("INSERT INTO table2 VALUES(NULL,33,34,'abc')");
        $this->assertGreaterThan(2, $iId);
        $db->rollbackTransaction();
        $row = $db->queryRow("SELECT * FROM table2 WHERE id = ?", [$iId]);
        $this->assertNull($row);
    }

    public function testBadQueries(): void {
        $db = new DBConnect(dbauth(), true);

        $this->expectException(DBException::class);
        $this->expectExceptionMessage("SQL could not prepare query; it is not valid");
        $this->expectExceptionMessage("SELECT * FROM table2 WHERE blag = 3");
        $row = $db->queryRow("SELECT * FROM table2 WHERE blag = 3");
    }
}
