<?php

declare(strict_types=1);

namespace SuperInstance\FluxVM;

use Exception;

final class FluxVMException extends Exception
{
    public const UNKNOWN_OPCODE = 0x01;
    public const INVALID_FORMAT = 0x02;
    public const DIVISION_BY_ZERO = 0x03;
    public const STACK_OVERFLOW = 0x04;
    public const STACK_UNDERFLOW = 0x05;
    public const MEMORY_FAULT = 0x06;
    public const PANIC = 0x07;
    public const INVALID_REGISTER = 0x08;
    public const INVALID_IMMEDIATE = 0x09;
    public const INVALID_BYTECODE = 0x0A;
    public const CYCLE_BUDGET_EXCEEDED = 0x0B;

    private int $errorCode;

    public function __construct(int $errorCode, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public static function unknownOpcode(int $opcode): self
    {
        return new self(self::UNKNOWN_OPCODE, "Unknown opcode: 0x" . dechex($opcode));
    }

    public static function invalidFormat(int $opcode): self
    {
        return new self(self::INVALID_FORMAT, "Invalid instruction format for opcode: 0x" . dechex($opcode));
    }

    public static function divisionByZero(): self
    {
        return new self(self::DIVISION_BY_ZERO, "Division by zero");
    }

    public static function stackOverflow(): self
    {
        return new self(self::STACK_OVERFLOW, "Stack overflow");
    }

    public static function stackUnderflow(): self
    {
        return new self(self::STACK_UNDERFLOW, "Stack underflow");
    }

    public static function memoryFault(int $address): self
    {
        return new self(self::MEMORY_FAULT, "Memory fault at address: 0x" . dechex($address));
    }

    public static function panic(string $message = ''): self
    {
        return new self(self::PANIC, "Panic" . ($message ? ": $message" : ''));
    }

    public static function invalidRegister(int $reg): self
    {
        return new self(self::INVALID_REGISTER, "Invalid register: R$reg");
    }

    public static function invalidImmediate(int $imm): self
    {
        return new self(self::INVALID_IMMEDIATE, "Invalid immediate value: $imm");
    }

    public static function invalidBytecode(string $reason): self
    {
        return new self(self::INVALID_BYTECODE, "Invalid bytecode: $reason");
    }

    public static function cycleBudgetExceeded(int $budget): self
    {
        return new self(self::CYCLE_BUDGET_EXCEEDED, "Cycle budget exceeded: $budget");
    }
}