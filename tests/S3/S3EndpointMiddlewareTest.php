<?php
namespace Aws\Test\S3;

use Aws\Command;
use Aws\CommandInterface;
use Aws\S3\S3EndpointMiddleware;
use Aws\Test\UsesServiceTrait;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

class S3EndpointMiddlewareTest extends \PHPUnit_Framework_TestCase
{
    use UsesServiceTrait;

    /**
     * @dataProvider includedCommandProvider
     *
     * @param CommandInterface $command
     */
    public function testAppliesAccelerateDualStackEndpointToCommand(CommandInterface $command)
    {
        $middleware = new S3EndpointMiddleware(
            $this->acceleratePatternAssertingHandler($command, 's3-accelerate.dualstack'),
            'us-west-2',
            [
                'dual_stack' => true,
                'accelerate' => true,
            ]
        );

        $middleware($command, $this->getRequest($command));
    }

    /**
     * @dataProvider excludedCommandProvider
     *
     * @param CommandInterface $command
     */
    public function testAppliesDualStackToCommandForInvalidOperationsWhenEnableBoth(CommandInterface $command)
    {
        $middleware = new S3EndpointMiddleware(
            $this->dualStackAssertingHandler($command),
            'us-west-2',
            [
                'dual_stack' => true,
                'accelerate' => true,
            ]
        );

        $middleware($command, $this->getRequest($command));
    }

    /**
     * @dataProvider includedCommandProvider
     *
     * @param CommandInterface $command
     */
    public function testDoesNothingWithoutOptIn(CommandInterface $command)
    {
        $middleware = new S3EndpointMiddleware(
            $this->noAcceleratePatternAssertingHandler($command, 's3-accelerate.dualstack'),
            'us-west-2',
            []
        );

        $middleware($command, $this->getRequest($command));

        $middleware = new S3EndpointMiddleware(
            $this->noDualStackAssertingHandler($command),
            'us-west-2',
            []
        );

        $middleware($command, $this->getRequest($command));
    }

    /**
     * @dataProvider includedCommandProvider
     *
     * @param CommandInterface $command
     */
    public function testAppliesAccelerateDualStackEndpointWithOperationalLevelOptIn(CommandInterface $command)
    {
        $middleware = new S3EndpointMiddleware(
            $this->acceleratePatternAssertingHandler($command, 's3-accelerate.dualstack'),
            'us-west-2',
            [
                'dual_stack' => false,
                'accelerate' => false,
            ]
        );

        $command['@use_accelerate_endpoint'] = true;
        $command['@use_dual_stack_endpoint'] = true;
        $middleware($command, $this->getRequest($command));
    }

    /**
     * @dataProvider excludedCommandProvider
     *
     * @param CommandInterface $command
     */
    public function testAppliesDualStackForInvalidOperationsWhenEnableBothAtOperationalLevel(CommandInterface $command)
    {
        $middleware = new S3EndpointMiddleware(
            $this->dualStackAssertingHandler($command),
            'us-west-2',
            []
        );

        $command['@use_accelerate_endpoint'] = true;
        $command['@use_dual_stack_endpoint'] = true;
        $middleware($command, $this->getRequest($command));
    }

    /**
     * @dataProvider includedCommandProvider
     *
     * @param CommandInterface $command
     */
    public function testDoesNothingWhenDisabledBothOnOperationLevel(CommandInterface $command)
    {
        $middleware = new S3EndpointMiddleware(
            $this->noAcceleratePatternAssertingHandler($command, 's3-accelerate.dualstack'),
            'us-west-2',
            [
                'dual_stack' => true,
                'accelerate' => true,
            ]
        );
        $command['@use_accelerate_endpoint'] = false;
        $command['@use_dual_stack_endpoint'] = false;
        $middleware($command, $this->getRequest($command));

        $middleware = new S3EndpointMiddleware(
            $this->noDualStackAssertingHandler($command),
            'us-west-2',
            [
                'dual_stack' => true,
                'accelerate' => true,
            ]
        );
        $command['@use_accelerate_endpoint'] = false;
        $command['@use_dual_stack_endpoint'] = false;
        $middleware($command, $this->getRequest($command));
    }

    /**
     * @dataProvider excludedCommandProvider
     *
     * @param CommandInterface $command
     */
    public function testIgnoresExcludedCommands(CommandInterface $command)
    {
        $middleware = new S3EndpointMiddleware(
            $this->noAcceleratePatternAssertingHandler($command, 's3-accelerate'),
            'us-west-2',
            [ 'accelerate' => true, ]
        );

        $middleware($command, $this->getRequest($command));
    }

    /**
     * @dataProvider includedCommandProvider
     *
     * @param CommandInterface $command
     */
    public function testAppliesAccelerateEndpointToCommands(CommandInterface $command)
    {
        $middleware = new S3EndpointMiddleware(
            $this->acceleratePatternAssertingHandler($command, 's3-accelerate'),
            'us-west-2',
            [ 'accelerate' => true, ]
        );

        $middleware($command, $this->getRequest($command));
    }

    /**
     * @dataProvider includedCommandProvider
     *
     * @param CommandInterface $command
     */
    public function testDoesNothingWhenAccelerationDisabledOnOperationLevel(CommandInterface $command)
    {
        $middleware = new S3EndpointMiddleware(
            $this->noAcceleratePatternAssertingHandler($command, 's3-accelerate'),
            'us-west-2',
            [ 'accelerate' => true, ]
        );

        $command['@use_accelerate_endpoint'] = false;
        $middleware($command, $this->getRequest($command));
    }

    public function testAppliesDualStackEndpointToCommand()
    {
        $command = new Command('CreateBucket', ['Bucket' => 'bucket']);
        $middleware = new S3EndpointMiddleware(
            $this->dualStackAssertingHandler($command),
            'us-west-2',
            [ 'dual_stack' => true, ]
        );
        $middleware($command, $this->getRequest($command));
    }

    public function testAppliesDualStackWithOperationLevelOptIn()
    {
        $command = new Command('CreateBucket', ['Bucket' => 'bucket']);
        $middleware = new S3EndpointMiddleware(
            $this->dualStackAssertingHandler($command),
            'us-west-2',
            [ 'dual_stack' => false, ]
        );

        $command['@use_dual_stack_endpoint'] = true;
        $middleware($command, $this->getRequest($command));
    }

    public function testDoesNothingForDualStackWithoutOptIn()
    {
        $command = new Command('DeleteBucket', [ 'Bucket' => 'bucket']);
        $middleware = new S3EndpointMiddleware(
            $this->noDualStackAssertingHandler($command),
            'us-west-2',
            []
        );
        $middleware($command, $this->getRequest($command));
    }

    public function testDoesNothingWhenDualStackDisabledOnOperationLevel()
    {
        $command = new Command('CreateBucket', ['Bucket' => 'bucket']);
        $middleware = new S3EndpointMiddleware(
            $this->noDualStackAssertingHandler($command),
            'us-west-2',
            [ 'dual_stack' => true, ]
        );

        $command['@use_dual_stack_endpoint'] = false;
        $middleware($command, $this->getRequest($command));
    }

    public function excludedCommandProvider()
    {
        return array_map(function ($commandName) {
            return [new Command($commandName, ['Bucket' => 'bucket'])];
        }, ['ListBuckets', 'CreateBucket', 'DeleteBucket']);
    }

    public function includedCommandProvider()
    {
        $excludedOperations = array_map(function (array $args) {
            return $args[0]->getName();
        }, $this->excludedCommandProvider());
        $s3Operations = $this->getTestClient('s3')->getApi()->getOperations();
        foreach ($excludedOperations as $excludedOperation) {
            unset($s3Operations[$excludedOperation]);
        }

        return array_map(function ($commandName) {
            return [new Command($commandName, ['Bucket' => 'bucket'])];
        }, array_keys($s3Operations));
    }

    private function getRequest(CommandInterface $command)
    {
        return new Request('GET', "https://s3.amazonaws.com/{$command['Bucket']}?key=query");
    }

    private function noAcceleratePatternAssertingHandler(CommandInterface $command, $pattern)
    {
        return function (
            CommandInterface $toHandle,
            RequestInterface $req
        ) use ($command, $pattern) {
            $this->assertNotContains($pattern, (string) $req->getUri());
            $this->assertContains($command['Bucket'], $req->getUri()->getPath());
        };
    }

    private function acceleratePatternAssertingHandler(CommandInterface $command, $pattern)
    {
        return function (
            CommandInterface $toHandle,
            RequestInterface $req
        ) use ($command, $pattern) {
            $this->assertSame(
                "{$command['Bucket']}.{$pattern}.amazonaws.com",
                $req->getUri()->getHost()
            );
            $this->assertNotContains($command['Bucket'], $req->getUri()->getPath());
        };
    }

    private function dualStackAssertingHandler(CommandInterface $command)
    {
        return function (
            CommandInterface $cmd,
            RequestInterface $req
        ) use ($command) {
            $this->assertSame(
                "s3.dualstack.us-west-2.amazonaws.com",
                $req->getUri()->getHost()
            );
            $this->assertContains($command['Bucket'], $req->getUri()->getPath());
            $this->assertContains('key=query', $req->getUri()->getQuery());
        };
    }

    private function noDualStackAssertingHandler(CommandInterface $command)
    {
        return function (
            CommandInterface $cmd,
            RequestInterface $req
        ) use ($command) {
            $this->assertNotContains('s3.dualstack', (string) $req->getUri());
            $this->assertContains($command['Bucket'], $req->getUri()->getPath());
            $this->assertContains('key=query', $req->getUri()->getQuery());
        };
    }
}