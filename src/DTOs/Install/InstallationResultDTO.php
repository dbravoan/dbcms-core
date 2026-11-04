<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Install;

final readonly class InstallationResultDTO
{
    /** @param list<string> $logs */
    public function __construct(public bool $success, public string $message, public array $logs = [], public bool $created = false) {}

    /** @return array{success:bool,message:string,created:bool} */
    public function connectionResponse(): array
    {
        return ['success' => $this->success, 'message' => $this->message, 'created' => $this->created];
    }

    /** @return array{success:bool,message:string,logs:list<string>} */
    public function provisioningResponse(): array
    {
        return ['success' => $this->success, 'message' => $this->message, 'logs' => $this->logs];
    }
}
