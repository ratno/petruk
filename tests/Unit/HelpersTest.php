<?php

namespace Ratno\Petruk\Test\Unit;

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function testRunProcWithSuccessfulCommand(): void
    {
        $result = run_proc('echo "test output"');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('output', $result);
        $this->assertArrayHasKey('value', $result);
        $this->assertEquals(0, $result['value']);
        $this->assertEquals(['test output'], $result['output']);
    }

    public function testRunProcWithFailingCommand(): void
    {
        $result = run_proc('exit 1');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('value', $result);
        $this->assertEquals(1, $result['value']);
    }

    public function testRunProcWithCommandThatProducesError(): void
    {
        $result = run_proc('ls /nonexistent/directory');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('value', $result);
        $this->assertNotEquals(0, $result['value']);
        $this->assertNotEmpty($result['error']);
    }

    public function testRunProcWithMultipleOutputLines(): void
    {
        $result = run_proc('printf "line1\nline2\nline3"');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('output', $result);
        $this->assertEquals(['line1', 'line2', 'line3'], $result['output']);
        $this->assertEquals(0, $result['value']);
    }

    public function testRunProcReturnsEmptyArrayForNoOutput(): void
    {
        $result = run_proc('true');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('value', $result);
        $this->assertEquals(0, $result['value']);
        $this->assertArrayNotHasKey('output', $result);
    }
}