<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base Test Case for MakeDeal CRM
 * 
 * Provides common functionality for all tests
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset any static state
        $this->resetStaticState();
        
        // Clear any caches
        $this->clearCaches();
    }

    /**
     * Tear down after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up any test files
        $this->cleanupTestFiles();
        
        // Reset mocked objects
        $this->resetMocks();
    }

    /**
     * Reset static state
     */
    protected function resetStaticState(): void
    {
        // Reset any singleton instances
        // Reset static properties
    }

    /**
     * Clear application caches
     */
    protected function clearCaches(): void
    {
        // Clear file caches
        // Clear memory caches
        // Clear query caches
    }

    /**
     * Clean up test files
     */
    protected function cleanupTestFiles(): void
    {
        $tmpDir = TEST_ROOT . '/tmp';
        if (is_dir($tmpDir)) {
            $files = glob($tmpDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Reset mocked objects
     */
    protected function resetMocks(): void
    {
        // Reset any global mocks
    }

    /**
     * Assert that an array has a key with a specific value
     */
    protected function assertArrayHasKeyWithValue(string $key, $value, array $array, string $message = ''): void
    {
        $this->assertArrayHasKey($key, $array, $message);
        $this->assertEquals($value, $array[$key], $message);
    }

    /**
     * Assert that a method was called with specific arguments
     */
    protected function assertMethodCalledWith($mock, string $method, array $arguments): void
    {
        $mock->expects($this->once())
            ->method($method)
            ->with(...$arguments);
    }

    /**
     * Get a private property value
     */
    protected function getPrivateProperty($object, string $property)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Set a private property value
     */
    protected function setPrivateProperty($object, string $property, $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Call a private method
     */
    protected function callPrivateMethod($object, string $method, array $arguments = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $arguments);
    }

    /**
     * Create a mock with constructor disabled
     */
    protected function createMockWithoutConstructor(string $className)
    {
        return $this->getMockBuilder($className)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Assert execution time is within limit
     */
    protected function assertExecutionTime(callable $callback, float $maxSeconds, string $message = ''): void
    {
        $start = microtime(true);
        $callback();
        $duration = microtime(true) - $start;
        
        $this->assertLessThan(
            $maxSeconds,
            $duration,
            $message ?: "Execution took {$duration} seconds, expected less than {$maxSeconds}"
        );
    }

    /**
     * Assert memory usage is within limit
     */
    protected function assertMemoryUsage(callable $callback, int $maxBytes, string $message = ''): void
    {
        $startMemory = memory_get_usage();
        $callback();
        $memoryUsed = memory_get_usage() - $startMemory;
        
        $this->assertLessThan(
            $maxBytes,
            $memoryUsed,
            $message ?: "Memory usage was {$memoryUsed} bytes, expected less than {$maxBytes}"
        );
    }
}