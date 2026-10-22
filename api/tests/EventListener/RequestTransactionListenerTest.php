<?php

namespace App\Tests\EventListener;

use App\EventListener\RequestTransactionListener;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function PHPUnit\Framework\exactly;
use function PHPUnit\Framework\never;
use function PHPUnit\Framework\once;

/**
 * @internal
 */
#[AllowMockObjectsWithoutExpectations]
class RequestTransactionListenerTest extends TestCase {
    private Connection|MockObject $connection;
    private EntityManagerInterface|MockObject $entityManager;
    private HttpKernelInterface|MockObject $kernel;
    private LoggerInterface|MockObject $logger;

    private MockObject|Request $request;
    private RequestTransactionListener $requestTransactionListener;

    protected function setUp(): void {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->kernel = $this->createStub(HttpKernelInterface::class);
        $this->request = $this->createMock(Request::class);

        $this->requestTransactionListener = new RequestTransactionListener($this->entityManager, $this->logger);
    }

    /**
     * @throws Exception
     */
    public function testIgnoresRequestEventForSubRequest() {
        $this->entityManager->expects(never())->method('getConnection');

        $this->requestTransactionListener->startTransaction(
            new KernelEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::SUB_REQUEST
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testStartsTransactionForRequestEventForMainRequest() {
        $this->entityManager->expects(exactly(2))->method('getConnection');

        $this->requestTransactionListener->startTransaction(
            new KernelEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testThrowsIfMultipleTransactionsStarted() {
        $this->entityManager->expects(exactly(2))->method('getConnection');
        $this->requestTransactionListener->startTransaction(
            new KernelEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );

        $this->expectException(\RuntimeException::class);
        $this->requestTransactionListener->startTransaction(
            new KernelEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testIgnoresResponseEventForSubRequest() {
        $this->entityManager->expects(never())->method('getConnection');
        $this->entityManager->expects(never())->method('flush');

        $this->requestTransactionListener->commitTransaction(
            new KernelEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::SUB_REQUEST
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testCommitsForResponseEventForMainRequest() {
        $this->entityManager->expects(exactly(4))->method('getConnection');
        $this->connection->method('getTransactionNestingLevel')->willReturn(1);

        $this->requestTransactionListener->startTransaction(
            new KernelEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );

        $this->requestTransactionListener->commitTransaction(
            new KernelEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testThrowsOnCommitIfNoTransactionStarted() {
        $this->entityManager->expects(never())->method('getConnection');

        $this->expectException(\RuntimeException::class);
        $this->requestTransactionListener->commitTransaction(
            new KernelEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testThrowsOnCommitForUnexpectedTransactionNestingLevel() {
        $this->entityManager->expects(exactly(3))->method('getConnection');
        $this->connection
            ->method('getTransactionNestingLevel')
            ->willReturnOnConsecutiveCalls(1, 2)
        ;

        $this->requestTransactionListener->startTransaction(
            new KernelEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );

        $this->expectException(\RuntimeException::class);
        $this->requestTransactionListener->commitTransaction(
            new KernelEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );
    }

    /**
     * @throws Exception
     */
    #[DataProvider('methodsWhichDontChangeState')]
    public function testIgnoresExceptionsForRequestsWhichDontChangeState(string $method) {
        $this->request->expects(once())->method('getMethod')->willReturn($method);
        $this->entityManager->expects(never())->method('getConnection');
        $this->entityManager->expects(never())->method('clear');

        $this->requestTransactionListener->rollbackTransaction(
            new ExceptionEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST,
                new \RuntimeException()
            )
        );
    }

    /** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
    public static function methodsWhichDontChangeState(): \Iterator {
        yield 'GET' => ['GET'];

        yield 'OPTIONS' => ['OPTIONS'];
    }

    /**
     * @throws Exception
     */
    #[DataProvider('methodsWhichChangeState')]
    public function testRollsBackTransactionForExceptionsForOtherMethods(string $method) {
        $this->request->expects(once())->method('getMethod')->willReturn($method);
        $this->entityManager->expects(exactly(4))->method('getConnection');
        $this->entityManager->expects(once())->method('clear');
        $this->requestTransactionListener->startTransaction(
            new KernelEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );

        $this->requestTransactionListener->rollbackTransaction(
            new ExceptionEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST,
                new \RuntimeException()
            )
        );
    }

    /** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
    public static function methodsWhichChangeState(): \Iterator {
        yield 'PUT' => ['PUT'];

        yield 'POST' => ['POST'];

        yield 'DELETE' => ['DELETE'];
    }

    /**
     * @throws Exception
     */
    public function testThrowsOnRollbackIfNoTransactionStartedButStillTriesToRollBack() {
        $this->entityManager->expects(once())->method('getConnection');

        $this->expectException(\RuntimeException::class);
        $this->requestTransactionListener->rollbackTransaction(
            new ExceptionEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST,
                new \RuntimeException()
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testThrowsOnRollbackForUnexpectedTransactionNestingLevelButStillTriesToRollBack() {
        $this->entityManager->expects(exactly(4))->method('getConnection');
        $this->connection
            ->method('getTransactionNestingLevel')
            ->willReturnOnConsecutiveCalls(1, 2)
        ;

        $this->requestTransactionListener->startTransaction(
            new KernelEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );

        $this->expectException(\RuntimeException::class);
        $this->requestTransactionListener->rollbackTransaction(
            new ExceptionEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST,
                new \RuntimeException()
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testStillClearsEntityManagerWhenRollbackThrowsException() {
        $this->request->expects(once())->method('getMethod')->willReturn('DELETE');
        $this->connection
            ->expects(once())
            ->method('rollback')
            ->willThrowException(new \RuntimeException())
        ;
        $this->entityManager->expects(exactly(4))->method('getConnection');
        $this->entityManager->expects(once())->method('clear');

        $this->requestTransactionListener->startTransaction(
            new KernelEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );

        $this->expectException(\RuntimeException::class);
        $this->requestTransactionListener->rollbackTransaction(
            new ExceptionEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST,
                new \RuntimeException()
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testLogsReasonForRollbackWhenRollbackThrowsException() {
        $this->request->expects(once())->method('getMethod')->willReturn('DELETE');
        $this->connection
            ->expects(once())
            ->method('rollback')
            ->willThrowException(new \RuntimeException())
        ;
        $this->entityManager->expects(exactly(4))->method('getConnection');
        $this->entityManager->expects(once())->method('clear');

        $this->requestTransactionListener->startTransaction(
            new KernelEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );

        $this->expectException(\RuntimeException::class);
        $this->logger->expects(once())->method('error');

        $this->requestTransactionListener->rollbackTransaction(
            new ExceptionEvent(
                $this->kernel,
                $this->request,
                HttpKernelInterface::MAIN_REQUEST,
                new \RuntimeException()
            )
        );
    }

    public function testResetDoesNothingWithoutPendingTransaction() {
        $this->entityManager->expects(never())->method('getConnection');

        $this->requestTransactionListener->reset();
    }

    /**
     * @throws Exception
     */
    public function testResetRollsBackATransactionThatWasNeverCommitted() {
        $this->connection->method('getTransactionNestingLevel')->willReturn(1);
        $this->connection->method('isTransactionActive')->willReturnOnConsecutiveCalls(true, false);
        $this->connection->expects(once())->method('rollBack');

        $mainRequest = new KernelEvent($this->kernel, $this->request, HttpKernelInterface::MAIN_REQUEST);
        $this->requestTransactionListener->startTransaction($mainRequest);

        $this->requestTransactionListener->reset();

        // the next request on the same (reused) listener can start a transaction again
        $this->requestTransactionListener->startTransaction($mainRequest);
    }
}
