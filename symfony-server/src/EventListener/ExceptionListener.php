<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExceptionListener {
	public function __construct(private LoggerInterface $logger) {}

	public function __invoke(Exceptionevent $event): void {
		$exception = $event->getThrowable();

		if ($exception instanceof NotFoundHttpException) {
			$response = new Response();
			$response->setStatusCode(Response::HTTP_NOT_FOUND);
			$response->setContent("Not found");
			$event->setResponse($response);
		} else {
			$this->logger->error("Caught exception: " . $exception->getMessage() . "(code: " . $exception->getCode() . "). Stack trace: " . $exception->getTraceAsString());
		}
	}
}
