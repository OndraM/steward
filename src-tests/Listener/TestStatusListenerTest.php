<?php

namespace Lmc\Steward\Listener;

use Lmc\Steward\ConfigHelper;
use Lmc\Steward\Listener\Fixtures\DummyPublisher;
use Lmc\Steward\Publisher\SauceLabsPublisher;
use Lmc\Steward\Publisher\TestingBotPublisher;
use Lmc\Steward\Selenium\SeleniumServerAdapter;

class TestStatusListenerTest extends \PHPUnit_Framework_TestCase
{
    /** @var SeleniumServerAdapter|\PHPUnit_Framework_MockObject_MockObject */
    protected $seleniumAdapterMock;

    public function setUp()
    {
        $this->seleniumAdapterMock = $this->getMockBuilder(SeleniumServerAdapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $configValues = ConfigHelper::getDummyConfig();
        $configValues['DEBUG'] = 1;
        ConfigHelper::setEnvironmentVariables($configValues);
        ConfigHelper::unsetConfigInstance();
    }

    public function testShouldRegisterXmlPublisherByDefault()
    {
        new TestStatusListener([], $this->seleniumAdapterMock);

        $this->expectOutputRegex('/Registering test results publisher "Lmc\x5cSteward\x5cPublisher\x5cXmlPublisher"$/');
    }

    /**
     * @dataProvider cloudServiceProvider
     * @param string $detectedCloudService
     * @param array $customPublishers
     * @param array $expectedExtraPublishers
     */
    public function testShouldRegisterExtraPublishers(
        $detectedCloudService,
        array $customPublishers,
        array $expectedExtraPublishers
    ) {
        $this->seleniumAdapterMock->expects($this->any())
            ->method('getCloudService')
            ->willReturn($detectedCloudService);

        new TestStatusListener($customPublishers, $this->seleniumAdapterMock);

        $this->expectOutputRegex('/Registering test results publisher "Lmc\x5cSteward\x5cPublisher\x5cXmlPublisher"/');
        $output = $this->getActualOutput();
        $this->assertSame(
            count($expectedExtraPublishers) + 1, // +1 for XmlPublisher, which is expected always
            mb_substr_count($output, 'Registering test results publisher'),
            'Mismatching number of registered publishers'
        );

        foreach ($expectedExtraPublishers as $expectedExtraPublisher) {
            $this->assertContains(
                'Registering test results publisher "' . $expectedExtraPublisher . '"',
                $output
            );
        }
    }

    /**
     * @return array[]
     */
    public function cloudServiceProvider()
    {
        return [
            'No cloud service, no custom publisher' => ['', [], []],
            'Sauce Labs service' => [SeleniumServerAdapter::CLOUD_SERVICE_SAUCELABS, [], [SauceLabsPublisher::class]],
            'TestingBot service' => [SeleniumServerAdapter::CLOUD_SERVICE_TESTINGBOT, [], [TestingBotPublisher::class]],
            'No cloud service, custom Publisher' => ['', [DummyPublisher::class], [DummyPublisher::class]],
            'Cloud service and custom Publisher' => [
                SeleniumServerAdapter::CLOUD_SERVICE_SAUCELABS,
                [DummyPublisher::class],
                [DummyPublisher::class, SauceLabsPublisher::class],
            ],
        ];
    }

    public function testShouldThrowAnExceptionIfRegisteringNotExistingClassAsPublisher()
    {
        $this->setExpectedException(
            \RuntimeException::class,
            'Cannot add new test publisher, class "Foo\NotExistingClass" not found'
        );

        $this->expectOutputRegex('/.*/'); // workaround to force PHpUnit to swallow output

        new TestStatusListener(['Foo\NotExistingClass'], $this->seleniumAdapterMock);
    }

    public function testShouldThrowAnExceptionIfRegisteringImproperPublisher()
    {
        $this->setExpectedException(
            \RuntimeException::class,
            'Cannot add new test publisher, class "stdClass" must be an instance of "AbstractPublisher"'
        );

        $this->expectOutputRegex('/.*/'); // workaround to force PHpUnit to swallow output

        new TestStatusListener(['stdClass'], $this->seleniumAdapterMock);
    }
}