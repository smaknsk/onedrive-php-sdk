<?php

namespace Test\Unit\Krizalys\Onedrive\Proxy;

use Krizalys\Onedrive\Proxy\QuotaProxy;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Quota;

class QuotaProxyTest extends \PHPUnit_Framework_TestCase
{
    public function testDeletedShouldReturnExpectedValue()
    {
        $graph = $this->createMock(Graph::class);
        $quota = $this->createMock(Quota::class);
        $quota->method('getDeleted')->willReturn(1234);
        $sut = new QuotaProxy($graph, $quota);
        $this->assertInstanceOf(QuotaProxy::class, $sut);
        $this->assertSame(1234, $sut->deleted);
    }

    public function testRemainingShouldReturnExpectedValue()
    {
        $graph = $this->createMock(Graph::class);
        $quota = $this->createMock(Quota::class);
        $quota->method('getRemaining')->willReturn(1234);
        $sut = new QuotaProxy($graph, $quota);
        $this->assertInstanceOf(QuotaProxy::class, $sut);
        $this->assertSame(1234, $sut->remaining);
    }

    public function testStateShouldReturnExpectedValue()
    {
        $graph = $this->createMock(Graph::class);
        $quota = $this->createMock(Quota::class);
        $quota->method('getState')->willReturn(1234);
        $sut = new QuotaProxy($graph, $quota);
        $this->assertInstanceOf(QuotaProxy::class, $sut);
        $this->assertSame(1234, $sut->state);
    }

    public function testTotalShouldReturnExpectedValue()
    {
        $graph = $this->createMock(Graph::class);
        $quota = $this->createMock(Quota::class);
        $quota->method('getTotal')->willReturn(1234);
        $sut = new QuotaProxy($graph, $quota);
        $this->assertInstanceOf(QuotaProxy::class, $sut);
        $this->assertSame(1234, $sut->total);
    }

    public function testUsedShouldReturnExpectedValue()
    {
        $graph = $this->createMock(Graph::class);
        $quota = $this->createMock(Quota::class);
        $quota->method('getUsed')->willReturn(1234);
        $sut = new QuotaProxy($graph, $quota);
        $this->assertInstanceOf(QuotaProxy::class, $sut);
        $this->assertSame(1234, $sut->used);
    }
}
