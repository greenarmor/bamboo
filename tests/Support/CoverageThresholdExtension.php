<?php

namespace Tests\Support;

use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use PHPUnit\Runner\CodeCoverage as RunnerCodeCoverage;
use PHPUnit\Runner\Extension\Extension as ExtensionContract;
use PHPUnit\Runner\Extension\Facade as ExtensionFacade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\CodeCoverageFilterRegistry;
use PHPUnit\TextUI\Configuration\Configuration;
use RuntimeException;
use SebastianBergmann\Environment\Runtime;
use function implode;
use function sprintf;
use function fwrite;
use const STDERR;

final class CoverageThresholdExtension implements ExtensionContract, ExecutionFinishedSubscriber
{
    private float $lineThreshold = 0.0;

    private float $functionThreshold = 0.0;

    private float $classThreshold = 0.0;

    private bool $canCollectCoverage = false;

    public function bootstrap(Configuration $configuration, ExtensionFacade $facade, ParameterCollection $parameters): void
    {
        $this->lineThreshold = $this->floatParameter($parameters, 'line');
        $this->functionThreshold = $this->floatParameter($parameters, 'function');
        $this->classThreshold = $this->floatParameter($parameters, 'class');

        $runtime = new Runtime();
        $this->canCollectCoverage = $runtime->hasPCOV() || $runtime->hasXdebug();

        if ($this->canCollectCoverage) {
            CodeCoverageFilterRegistry::instance()->init($configuration, true);
            $facade->requireCodeCoverageCollection();
        } else {
            fwrite(STDERR, "[coverage] Code coverage driver not available; thresholds will be skipped.\n");
        }

        $facade->registerSubscriber($this);
    }

    public function notify(ExecutionFinished $event): void
    {
        $coverage = RunnerCodeCoverage::instance();

        if (!$coverage->isActive() || !$this->canCollectCoverage) {
            return;
        }

        $report = $coverage->codeCoverage()->getReport();

        $linePercentage = $report->percentageOfExecutedLines()->asFloat();
        $functionPercentage = $report->percentageOfTestedFunctionsAndMethods()->asFloat();
        $classPercentage = $report->percentageOfTestedClassesAndTraits()->asFloat();

        $failures = [];

        if ($this->lineThreshold > 0 && $linePercentage < $this->lineThreshold) {
            $failures[] = sprintf('line coverage %.2f%% is below the %.2f%% threshold', $linePercentage, $this->lineThreshold);
        }

        if ($this->functionThreshold > 0 && $functionPercentage < $this->functionThreshold) {
            $failures[] = sprintf('function coverage %.2f%% is below the %.2f%% threshold', $functionPercentage, $this->functionThreshold);
        }

        if ($this->classThreshold > 0 && $classPercentage < $this->classThreshold) {
            $failures[] = sprintf('class coverage %.2f%% is below the %.2f%% threshold', $classPercentage, $this->classThreshold);
        }

        if ($failures !== []) {
            throw new RuntimeException('Code coverage thresholds not met: ' . implode('; ', $failures));
        }
    }

    private function floatParameter(ParameterCollection $parameters, string $name): float
    {
        if (!$parameters->has($name)) {
            return 0.0;
        }

        return (float) $parameters->get($name);
    }
}
