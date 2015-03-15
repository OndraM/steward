<?php

namespace Lmc\Steward\Component;

use Lmc\Steward\Component\Fixtures\StringableObject;
use Lmc\Steward\Test\AbstractTestCase;
use Lmc\Steward\Test\ConfigHelper;

class LegacyTest extends \PHPUnit_Framework_TestCase
{
    /** @var AbstractTestCase */
    protected $testCase;

    public function setUp()
    {
        $this->testCase = $this->getMockBuilder('Lmc\Steward\Test\AbstractTestCase')
            ->setMethods(['getName'])
            ->getMock();

        $this->testCase->expects($this->any())
            ->method('getName')
            ->willReturn('testFooMethod');

        ConfigHelper::unsetConfigInstance();
    }

    /**
     * @expectedException Lmc\Steward\Component\LegacyException
     * @expectedExceptionMessage Cannot read legacy file
     */
    public function testShouldThrowExceptionIfLegacyFileNotFound()
    {
        $legacy = new Legacy($this->testCase);
        $this->expectOutputRegex('/.*New legacy instantiated.*/');

        $legacy->loadWithName('not-existing');
    }

    /**
     * @expectedException Lmc\Steward\Component\LegacyException
     * @expectedExceptionMessage Cannot parse legacy from file
     */
    public function testShouldFailIfNotUnserializableFileFound()
    {
        $fn = sys_get_temp_dir() . '/wrong.legacy';
        touch($fn);

        $legacy = new Legacy($this->testCase);
        $legacy->setFileDir(sys_get_temp_dir());
        $this->expectOutputRegex('/.*New legacy instantiated.*/');

        $legacy->loadWithName('wrong');
    }

    public function testShouldReadLegacyFileWithSpecifiedName()
    {
        $expectedData = [
            'foo' => 'bar',
            'baz' => 'ban',
        ];

        $legacy = new Legacy($this->testCase);
        $legacy->setFileDir(__DIR__ . '/Fixtures');

        $output = $legacy->loadWithName('simple');

        $this->expectOutputRegex('/.*Reading legacy "simple" from file.*/');

        $this->assertEquals($expectedData, $output);
    }

    public function testShouldSaveAndReadLegacyDataWithSpecifiedName()
    {
        $sampleData = [
            'time' => time(),
            'baz' => '+ěščřžýáí"!$<>',
        ];

        $legacy = new Legacy($this->testCase);
        $legacy->setFileDir(sys_get_temp_dir());

        $legacy->saveWithName($sampleData, 'foo');

        $output = $legacy->loadWithName('foo');
        $this->expectOutputRegex('/.*Reading legacy "foo" from file.*/');

        $this->assertSame($sampleData, $output);
    }

    /**
     * @expectedException Lmc\Steward\Component\LegacyException
     * @expectedExceptionMessage Cannot save legacy to file /notexisting/baz.legacy
     */
    public function testShouldFailIfSavingToNotExistingDirectory()
    {
        $legacy = new Legacy($this->testCase);
        $legacy->setFileDir('/notexisting');

        $this->expectOutputRegex('/.*Saving data as legacy "baz" to file "\/notexisting\/baz\.legacy".*/');
        $legacy->saveWithName([], 'baz');
    }

    public function testShouldSaveObjectAndDumpUsingToStringMethodIfObjectHasItAndDebugModeIsEnabled()
    {
        // enable debug mode
        $configValues = ConfigHelper::getDummyConfig();
        $configValues['DEBUG'] = 1;
        ConfigHelper::setEnvironmentVariables($configValues);

        $object = new StringableObject('foobar string');
        $legacy = new Legacy($this->testCase);
        $legacy->setFileDir(sys_get_temp_dir());
        $legacy->saveWithName($object, 'object');

        $this->expectOutputRegex('/.*Legacy data: __toString\(\) called: foobar string.*/');
    }

    public function testShouldNotDumpDataIfDebugModeIsNotEnabled()
    {
        // disable debug mode
        $configValues = ConfigHelper::getDummyConfig();
        $configValues['DEBUG'] = 0;
        ConfigHelper::setEnvironmentVariables($configValues);

        $object = new StringableObject('foobar string');
        $legacy = new Legacy($this->testCase);
        $legacy->setFileDir(sys_get_temp_dir());
        $legacy->saveWithName($object, 'object');

        $this->expectOutputRegex('/^((?!foobar string).)*$/s'); // Output should not contain the string
    }

    /**
     * @expectedException Lmc\Steward\Component\LegacyException
     * @expectedExceptionMessage Cannot generate legacy name from class without 'Phase' followed by number in name
     */
    public function testShouldFailIfTryingToAutomaticallySaveLegacyIfTestDoesntHavePhaseInItsName()
    {
        $testCasePhase1 = $this->getMockBuilder('Lmc\Steward\Test\AbstractTestCase')
            ->setMethods([])
            ->setMockClassName('Mock_TestCaseWithoutPhaseNumerInName')
            ->getMock();

        $legacy = new Legacy($testCasePhase1);
        $legacy->setFileDir(sys_get_temp_dir());

        $legacy->save('data');

    }

    public function testShouldAutomaticallySaveAndLoadLegacyIfTestsHavePhaseInItsName()
    {
        // save data in first test case
        $testCasePhase1 = $this->getMockBuilder('Lmc\Steward\Test\AbstractTestCase')
            ->setMethods([])
            ->setMockClassName('Mock_TestCasePhase1')
            ->getMock();

        $legacy1 = new Legacy($testCasePhase1);
        $legacy1->setFileDir(sys_get_temp_dir());
        $legacy1->save('data');

        // load data in second test case
        $testCasePhase2 = $this->getMockBuilder('Lmc\Steward\Test\AbstractTestCase')
            ->setMethods([])
            ->setMockClassName('Mock_TestCasePhase2')
            ->getMock();

        $legacy2 = new Legacy($testCasePhase2);
        $legacy2->setFileDir(sys_get_temp_dir());

        $this->assertEquals('data', $legacy2->load());
    }

    public function testShouldAutomaticallySaveAndLoadLegacyForMethodsWithSameNameInTestsWithPhaseInItsName()
    {
        // save data in first test case in method 'testMethodFoo'
        $testCasePhase1 = $this->getMockBuilder('Lmc\Steward\Test\AbstractTestCase')
            ->setMethods(['getName'])
            ->setMockClassName('Mock_TestCasePhase1')
            ->getMock();

        $testCasePhase1->expects($this->any())
            ->method('getName')
            ->willReturn('testMethodFoo');

        $legacy1 = new Legacy($testCasePhase1);
        $legacy1->setFileDir(sys_get_temp_dir());
        $legacy1->save('data', Legacy::LEGACY_TYPE_TEST);

        // load data in second test case, also in method 'testMethodFoo'
        $testCasePhase2 = $this->getMockBuilder('Lmc\Steward\Test\AbstractTestCase')
            ->setMethods(['getName'])
            ->setMockClassName('Mock_TestCasePhase2')
            ->getMock();

        $testCasePhase2->expects($this->any())
            ->method('getName')
            ->willReturn('testMethodFoo');

        $legacy2 = new Legacy($testCasePhase2);
        $legacy2->setFileDir(sys_get_temp_dir());

        $this->assertEquals('data', $legacy2->load(Legacy::LEGACY_TYPE_TEST));

        // try to load the data in method with different name => it should not be accessible
        $testCasePhase2Method2 = $this->getMockBuilder('Lmc\Steward\Test\AbstractTestCase')
            ->setMethods(['getName'])
            ->setMockClassName('Mock_TestCasePhase2')
            ->getMock();
        $testCasePhase2Method2->expects($this->any())
            ->method('getName')
            ->willReturn('testMethodDifferent');

        $legacy2Method2 = new Legacy($testCasePhase2Method2);
        $legacy2Method2->setFileDir(sys_get_temp_dir());

        try {
            $legacy2Method2->load(Legacy::LEGACY_TYPE_TEST);
        } catch (LegacyException $e) {
            $this->assertContains('Cannot read legacy file', $e->getMessage());
            return;
        }
        $this->fail('Expected exception LegacyException not thrown when loading Legacy that should not exists');
    }
}
