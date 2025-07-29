<?php
// backend/lib/FileHandlerException.php

namespace Lib;

class FileHandlerException extends \RuntimeException 
{
    private array $context;

    public function __construct(string $message, array $context = []) 
    {
        parent::__construct($message);
        $this->context = $context;
    }

    public function getContext(): array 
    {
        return $this->context;
    }
}