<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Configuration\Fixtures;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Selenium\CustomCapabilitiesResolverInterface;
use Lmc\Steward\Test\AbstractTestCase;

class ImplementsCapabilitiesResolverInterface implements CustomCapabilitiesResolverInterface
{
    public function __construct(ConfigProvider $config)
    {
    }

    public function resolveDesiredCapabilities(
        AbstractTestCase $test,
        DesiredCapabilities $capabilities
    ): DesiredCapabilities {
        return $capabilities;
    }
}
