<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Pdf\Drivers\GotenbergDriver;
use BiztechEG\EasyPdfWord\Pdf\Drivers\MpdfDriver;
use BiztechEG\EasyPdfWord\Pdf\PdfManager;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** The engine manager on its own, as plain PHP uses it: no Laravel app. */
class PdfManagerTest extends TestCase
{
    public function test_built_in_engines_and_aliases(): void
    {
        $manager = new PdfManager;

        $this->assertSame('mpdf', $manager->getDefaultDriver());
        $this->assertInstanceOf(MpdfDriver::class, $manager->driver());
        $this->assertSame($manager->driver('mpdf'), $manager->driver('MPDF'));
        $this->assertSame('browsershot', $manager->normalize('Chromium'));
        $this->assertInstanceOf(GotenbergDriver::class, $manager->driver('gotenberg'));
        $this->assertTrue($manager->has('chrome'));
        $this->assertFalse($manager->has('dompdf'));
        $this->assertSame(['mpdf', 'gotenberg'], array_keys($manager->getDrivers()));
        $this->assertSame([], $manager->forgetDrivers()->getDrivers());
    }

    public function test_extend_passes_the_creator_argument(): void
    {
        $manager = new PdfManager(creatorArgument: 'the-app');
        $received = null;
        $manager->extend('Mine', function ($app) use (&$received) {
            $received = $app;

            return new StubDriver('%PDF-mine');
        });

        $this->assertSame(['%PDF-mine', 'mine'], $manager->render('<p>x</p>', $this->options(), 'mine'));
        $this->assertSame('the-app', $received);
    }

    public function test_config_closures_are_read_on_every_use(): void
    {
        $config = ['pdf' => ['driver' => 'mpdf']];
        $manager = new PdfManager(function () use (&$config) {
            return $config;
        });
        $config['pdf']['driver'] = 'gotenberg';

        $this->assertSame('gotenberg', $manager->getDefaultDriver());
    }

    public function test_a_failing_engine_falls_back_and_warns(): void
    {
        $warnings = [];
        $manager = new PdfManager(['pdf' => ['fallback' => 'good']], warn: function (string $message) use (&$warnings) {
            $warnings[] = $message;
        });
        $manager->extend('bad', fn () => new StubDriver(fail: str_repeat('ب', 400)));
        $manager->extend('good', fn () => new StubDriver('%PDF-good'));

        $this->assertSame(['%PDF-good', 'good'], $manager->render('<p>x</p>', $this->options(), 'bad'));
        $this->assertCount(1, $warnings);
        $this->assertStringStartsWith('easy-pdf-word: [bad] failed, falling back to [good]: بب', $warnings[0]);
        $this->assertStringEndsWith('ب...', $warnings[0]);
    }

    public function test_unknown_engines_are_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown PDF engine [dompdf]');

        (new PdfManager)->render('<p>x</p>', $this->options(), 'dompdf');
    }

    public function test_other_calls_go_to_the_default_engine(): void
    {
        $manager = new PdfManager(['pdf' => ['driver' => 'stub']]);
        $manager->extend('stub', fn () => new StubDriver('%PDF'));

        $this->assertTrue($manager->isAvailable());
    }

    private function options(): PdfOptions
    {
        return new PdfOptions;
    }
}

class StubDriver implements PdfDriver
{
    public function __construct(private string $pdf = '%PDF', private ?string $fail = null) {}

    public function render(string $html, PdfOptions $options): string
    {
        return $this->fail === null ? $this->pdf : throw new RuntimeException($this->fail);
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function usesCssFonts(): bool
    {
        return false;
    }
}
