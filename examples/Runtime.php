<?php

namespace FluxVM\Examples;

class Runtime
{
    private array $registers = [];
    private array $memory = [];
    private int $pc = 0;
    private bool $running = false;

    public function __construct(int $registerCount = 8, int $memorySize = 256)
    {
        $this->registers = array_fill(0, $registerCount, 0);
        $this->memory = array_fill(0, $memorySize, 0);
        $this->pc = 0;
        $this->running = false;
    }

    public function loadProgram(array $bytes): void
    {
        $this->memory = array_fill(0, count($this->memory), 0);
        foreach ($bytes as $i => $byte) {
            if ($i < count($this->memory)) {
                $this->memory[$i] = $byte;
            }
        }
        $this->pc = 0;
        $this->running = true;
    }

    public function step(): int
    {
        if (!$this->running || $this->pc >= count($this->memory)) {
            $this->running = false;
            return -1;
        }

        $opcode = $this->memory[$this->pc++] ?? 0;
        
        switch ($opcode) {
            case 0x00: // NOP
                break;
            case 0x01: // HALT
                $this->running = false;
                break;
            case 0x02: // LOAD
                if ($this->pc + 1 < count($this->memory)) {
                    $reg = $this->memory[$this->pc++] ?? 0;
                    $addr = $this->memory[$this->pc++] ?? 0;
                    $this->registers[$reg] = $this->memory[$addr] ?? 0;
                }
                break;
            case 0x03: // STORE
                if ($this->pc + 1 < count($this->memory)) {
                    $addr = $this->memory[$this->pc++] ?? 0;
                    $reg = $this->memory[$this->pc++] ?? 0;
                    $this->memory[$addr] = $this->registers[$reg] ?? 0;
                }
                break;
            default:
                $this->running = false;
        }

        return $opcode;
    }

    public function run(): void
    {
        while ($this->running) {
            $this->step();
        }
    }

    public function getRegisters(): array
    {
        return $this->registers;
    }

    public function getMemory(): array
    {
        return $this->memory;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function getPc(): int
    {
        return $this->pc;
    }
}