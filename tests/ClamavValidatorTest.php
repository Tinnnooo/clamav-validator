<?php

namespace Noin\Tests\ClamavValidator;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;
use Mockery;
use Noin\ClamavValidator\ClamavValidatorException;
use PHPUnit\Framework\TestCase;
use Noin\Tests\ClamavValidator\Helpers\ValidatorHelper;

class ClamavValidatorTest extends TestCase
{
    use ValidatorHelper;

    protected $clean_data;
    protected $virus_data;
    protected $error_data;
    protected $rules;
    protected $multiple_files_all_clean;
    protected $multiple_files_some_with_virus;

    protected function setUp(): void
    {
        $this->clean_data = [
            'file' => $this->getTempPath(__DIR__ . '/files/test1.txt')
        ];
        $this->virus_data = [
            'file' => $this->getTempPath(__DIR__ . '/files/test2.txt')
        ];
        $this->error_data = [
            'file' => $this->getTempPath(__DIR__ . '/files/test3.txt')
        ];
        $this->multiple_files_all_clean = [
            'files' => [
                $this->getTempPath(__DIR__ . '/files/test1.txt'),
                $this->getTempPath(__DIR__ . '/files/test4.txt'),
            ]
        ];
        $this->multiple_files_some_with_virus = [
            'files' => [
                $this->getTempPath(__DIR__ . '/files/test1.txt'),
                $this->getTempPath(__DIR__ . '/files/test2.txt'),
                $this->getTempPath(__DIR__ . '/files/test4.txt'),
            ]
        ];
    }

    private function setConfig(array $opts = []): void
    {
        $opts = array_merge(['error' => false, 'skip' => false, 'exception' => false], $opts);

        $config = Mockery::mock();
        $config->shouldReceive('get')->with('clamav.preferred_socket')->andReturn('unix_socket');
        $config->shouldReceive('get')->with('clamav.client_exceptions')->andReturn($opts['exception']);
        $config->shouldReceive('get')->with('clamav.unix_socket')->andReturn(!$opts['error'] ? '/var/run/clamav/clamd.ctl' : '/dev/null');
        $config->shouldReceive('get')->with('clamav.tcp_socket')->andReturn(!$opts['error'] ? 'tcp://127.0.0.1:3310' : 'tcp://127.0.0.1:0');
        $config->shouldReceive('get')->with('clamav.socket_read_timeout')->andReturn(30);
        $config->shouldReceive('get')->with('clamav.socket_connect_timeout')->andReturn(5);
        $config->shouldReceive('get')->with('clamav.skip_validation')->andReturn($opts['skip']);

        Config::swap($config);
    }

    protected function tearDown(): void
    {
        chmod($this->error_data['file'], 0644);

        Container::getInstance()->flush();

        Mockery::close();
    }

    public function testValidatesSkipped()
    {
        $this->setConfig(['skip' => true]);

        $validator = $this->makeValidator(
            $this->clean_data,
            ['file' => 'clamav'],
        );

        $this->assertTrue($validator->passes());
    }

    public function testValidatesSkippedForBoolValidatedConfigValues()
    {
        $this->setConfig(['skip' => '1']);

        $validator = $this->makeValidator(
            $this->clean_data,
            ['file' => 'clamav'],
        );

        $this->assertTrue($validator->passes());
    }

    public function testValidatesClean()
    {
        $this->setConfig();

        $validator = $this->makeValidator(
            $this->clean_data,
            ['file' => 'clamav'],
        );

        $this->assertTrue($validator->passes());
    }

    public function testValidatesCleanMultiFile()
    {
        $this->setConfig();

        $validator = $this->makeValidator(
            $this->multiple_files_all_clean,
            ['files' => 'clamav'],
        );

        $this->assertTrue($validator->passes());
    }

    public function testValidatesVirus()
    {
        $this->setConfig();

        $validator = $this->makeValidator(
            $this->virus_data,
            ['file' => 'clamav'],
        );

        $this->assertTrue($validator->fails());
    }

    public function testValidatesVirusMultiFile()
    {
        $this->setConfig();

        $validator = $this->makeValidator(
            $this->multiple_files_some_with_virus,
            ['files' => 'clamav'],
        );

        $this->assertTrue($validator->fails());
    }

    public function testCannotValidateNonReadable()
    {
        $this->setConfig();

        $this->expectException(ClamavValidatorException::class);

        $validator = $this->makeValidator(
            $this->error_data,
            ['file' => 'clamav'],
        );

        chmod($this->error_data['file'], 0000);

        $validator->passes();
    }

    public function testFailsValidationOnError()
    {
        $this->setConfig(['error' => true]);

        $validator = $this->makeValidator(
            $this->clean_data,
            ['file' => 'clamav'],
        );

        $this->assertTrue($validator->fails());
    }

    public function testThrowsExceptionOnValidationError()
    {
        $this->setConfig(['error' => true, 'exception' => true]);

        $this->expectException(ClamavValidatorException::class);

        $validator = $this->makeValidator(
            $this->clean_data,
            ['file' => 'clamav'],
        );

        $this->assertTrue($validator->fails());
    }
}
