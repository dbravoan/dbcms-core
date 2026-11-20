<?php

declare(strict_types=1);

namespace DbCms\Core\Exceptions;

class DomainServiceException extends BusinessException
{
    public function __construct(
        string $message = 'A domain operation failure occurred.',
        int $statusCode = 422,
        string $errorCode = 'DOMAIN_OPERATION_FAILED'
    ) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
    }
}
