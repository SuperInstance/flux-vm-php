<?php

namespace FluxVM\Examples;

class OpcodeValidator
{
    private const OPCODE_MAP = [
        'NOP' => 0x00, 'HALT' => 0x01, 'LOAD' => 0x02, 'STORE' => 0x03,
        'ADD' => 0x04, 'SUB' => 0x05, 'MUL' => 0x06, 'DIV' => 0x07,
        'AND' => 0x10, 'OR' => 0x11, 'XOR' => 0x12, 'NOT' => 0x13,
        'JUMP' => 0x20, 'JZ' => 0x21, 'JNZ' => 0x22, 'CALL' => 0x23,
    ];

    public function validateOpcode(string $opcode): bool
    {
        return in_array(strtoupper($opcode), self::OPCODE_MAP);
    }

    public function validateOperand(int $operand, int $max = 255): bool
    {
        return $operand >= 0 && $operand <= $max;
    }

    public function validateProgram(array $program): array
    {
        $errors = [];
        foreach ($program as $i => $instr) {
            if (is_string($instr)) {
                if (!$this->validateOpcode($instr)) {
                    $errors[] = "Invalid opcode at line $i: $instr";
                }
            }
        }
        return $errors;
    }
}