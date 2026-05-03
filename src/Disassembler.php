<?php

declare(strict_types=1);

namespace SuperInstance\FluxVM;

final class Disassembler
{
    private const OPCODE_NAMES = [
        // Format A
        0x00 => 'Halt',
        0x01 => 'Nop',
        0x02 => 'Ret',
        0x08 => 'Yield',
        0x09 => 'Panic',
        0x0A => 'Unreachable',

        // Format B
        0x10 => 'Push',
        0x11 => 'Pop',
        0x12 => 'Dup',
        0x13 => 'Swap',
        0x20 => 'IMov',
        0x40 => 'FMov',

        // Integer arithmetic
        0x21 => 'IAdd',
        0x22 => 'ISub',
        0x23 => 'IMul',
        0x24 => 'IDiv',
        0x25 => 'IMod',
        0x26 => 'INeg',
        0x27 => 'IAbs',
        0x2A => 'IMin',
        0x2B => 'IMax',
        0x2C => 'IAnd',
        0x2D => 'IOr',
        0x2E => 'IXor',
        0x2F => 'IShl',
        0x30 => 'IShr',
        0x31 => 'INot',

        // Comparisons
        0x32 => 'ICmpEq',
        0x33 => 'ICmpNe',
        0x34 => 'ICmpLt',
        0x35 => 'ICmpLe',
        0x36 => 'ICmpGt',
        0x37 => 'ICmpGe',

        // Float arithmetic
        0x41 => 'FAdd',
        0x42 => 'FSub',
        0x43 => 'FMul',
        0x44 => 'FDiv',
        0x45 => 'FMod',
        0x46 => 'FNeg',
        0x47 => 'FAbs',
        0x48 => 'FSqrt',
        0x49 => 'FFloor',
        0x4A => 'FCeil',
        0x4B => 'FRound',
        0x4C => 'FMin',
        0x4D => 'FMax',
        0x4E => 'FSin',
        0x4F => 'FCos',
        0x50 => 'FExp',
        0x51 => 'FLog',
        0x52 => 'FClamp',
        0x53 => 'FLerp',

        // Float comparisons
        0x54 => 'FCmpEq',
        0x55 => 'FCmpNe',
        0x56 => 'FCmpLt',
        0x57 => 'FCmpLe',
        0x58 => 'FCmpGt',
        0x59 => 'FCmpGe',

        // Conversions
        0x60 => 'IToF',
        0x61 => 'FToI',
        0x62 => 'BToI',
        0x63 => 'IToB',

        // Memory
        0x70 => 'Load8',
        0x71 => 'Load16',
        0x72 => 'Load32',
        0x73 => 'Load64',
        0x74 => 'Store8',
        0x75 => 'Store16',
        0x76 => 'Store32',
        0x77 => 'Store64',
        0x78 => 'LoadAddr',

        // Format D
        0x28 => 'IInc',
        0x29 => 'IDec',
        0x79 => 'StackAlloc',

        // Vector
        0xB0 => 'VLoad',
        0xB1 => 'VStore',
        0xB2 => 'VAdd',
        0xB3 => 'VMul',
        0xB4 => 'VDot',

        // Type operations
        0x90 => 'Cast',
        0x91 => 'SizeOf',
        0x92 => 'TypeOf',

        // Bitwise
        0xA0 => 'BAnd',
        0xA1 => 'BOr',
        0xA2 => 'BXor',
        0xA3 => 'BShl',
        0xA4 => 'BShr',
        0xA5 => 'BNot',

        // Format G - Control flow
        0x03 => 'Jump',
        0x04 => 'JumpIf',
        0x05 => 'JumpIfNot',
        0x06 => 'Call',
        0x07 => 'CallIndirect',

        // A2A
        0x80 => 'ASend',
        0x81 => 'ARecv',
        0x82 => 'AAsk',
        0x83 => 'ATell',
        0x84 => 'ADelegate',
        0x85 => 'ABroadcast',
        0x86 => 'ASubscribe',
        0x87 => 'AWait',
        0x88 => 'ATrust',
        0x89 => 'AVerify',
    ];

    private const REGISTER_NAMES = [
        0 => 'R0', 1 => 'R1', 2 => 'R2', 3 => 'R3',
        4 => 'R4', 5 => 'R5', 6 => 'R6', 7 => 'R7',
        8 => 'RV', 9 => 'A0', 10 => 'A1', 11 => 'SP',
        12 => 'FP', 13 => 'FL', 14 => 'TP', 15 => 'LR',
    ];

    private string $bytecode = '';
    private int $pc = 0;
    private int $length = 0;

    public function __construct(string $bytecode = '')
    {
        if ($bytecode !== '') {
            $this->load($bytecode);
        }
    }

    public function load(string $bytecode): void
    {
        $this->bytecode = $bytecode;
        $this->pc = 0;
        $this->length = strlen($bytecode);
    }

    public function disassemble(): string
    {
        $output = [];
        $this->pc = 0;

        while ($this->pc < $this->length) {
            $startPc = $this->pc;
            $instr = $this->decodeInstruction();
            $hexBytes = $this->getHexBytes($startPc, $this->pc - $startPc);
            $output[] = sprintf('%04X  %-20s  %s', $startPc, $hexBytes, $instr);
        }

        return implode("\n", $output);
    }

    public function disassembleOne(): ?string
    {
        if ($this->pc >= $this->length) {
            return null;
        }

        $startPc = $this->pc;
        $instr = $this->decodeInstruction();
        $hexBytes = $this->getHexBytes($startPc, $this->pc - $startPc);
        return sprintf('%04X  %-20s  %s', $startPc, $hexBytes, $instr);
    }

    private function decodeInstruction(): string
    {
        if ($this->pc >= $this->length) {
            return '<EOF>';
        }

        $opcode = $this->getU8();

        // Format A: 1 byte
        if (in_array($opcode, [0x00, 0x01, 0x02, 0x08, 0x09, 0x0A])) {
            return $this->getOpcodeName($opcode);
        }

        // Format B: 3 bytes (opcode + Rd + Rs)
        if (in_array($opcode, [0x10, 0x11, 0x12, 0x13, 0x20, 0x40])) {
            $Rd = $this->getU8();
            $Rs = $this->getU8();
            return sprintf('%s %s, %s', $this->getOpcodeName($opcode),
                $this->getRegisterName($Rd), $this->getRegisterName($Rs));
        }

        // Format C: 4 bytes (opcode + Rd + Ra + Rb)
        if ($this->isFormatC($opcode)) {
            $Rd = $this->getU8();
            $Ra = $this->getU8();
            $Rb = $this->getU8();
            return sprintf('%s %s, %s, %s', $this->getOpcodeName($opcode),
                $this->getRegisterName($Rd), $this->getRegisterName($Ra), $this->getRegisterName($Rb));
        }

        // Format D: 4 bytes (opcode + Rd + imm16)
        if (in_array($opcode, [0x28, 0x29, 0x79])) {
            $Rd = $this->getU8();
            $imm = $this->getI16();
            return sprintf('%s %s, %d', $this->getOpcodeName($opcode),
                $this->getRegisterName($Rd), $imm);
        }

        // Format E: 5 bytes (opcode + Rd + Rb + off16)
        if (in_array($opcode, [0x70, 0x71, 0x72, 0x73, 0x74, 0x75, 0x76, 0x77, 0x78, 0xB0, 0xB1])) {
            $Rd = $this->getU8();
            $Rb = $this->getU8();
            $off = $this->getU16();
            return sprintf('%s %s, [%s + %d]', $this->getOpcodeName($opcode),
                $this->getRegisterName($Rd), $this->getRegisterName($Rb), $off);
        }

        // Format G: variable
        if (in_array($opcode, [0x03, 0x04, 0x05])) {
            // Jump, JumpIf, JumpIfNot: reg + offset16
            $reg = $this->getU8();
            $len = $this->getU8();
            $off = $this->getI16();
            $name = $this->getOpcodeName($opcode);
            if ($opcode === 0x03) {
                return sprintf('%s %d', $name, $off);
            }
            return sprintf('%s %s, %d', $name, $this->getRegisterName($reg), $off);
        }

        if ($opcode === 0x06) {
            // Call: func_idx16
            $len = $this->getU8();
            $func = $this->getU16();
            return sprintf('Call %d', $func);
        }

        if ($opcode === 0x07) {
            // CallIndirect: reg
            $len = $this->getU8();
            $reg = $this->getU8();
            return sprintf('CallIndirect %s', $this->getRegisterName($reg));
        }

        // A2A ops
        if (in_array($opcode, [0x80, 0x81, 0x82, 0x83, 0x88, 0x89])) {
            $len = $this->getU8();
            $a = $this->getU8();
            $b = $this->getU8();
            return sprintf('%s %d, %s', $this->getOpcodeName($opcode), $a, $this->getRegisterName($b));
        }

        if (in_array($opcode, [0x85, 0x86, 0x87])) {
            $len = $this->getU8();
            $reg = $this->getU8();
            return sprintf('%s %s', $this->getOpcodeName($opcode), $this->getRegisterName($reg));
        }

        if ($opcode === 0x84) {
            // ADelegate: agent_id + bc_start
            $len = $this->getU8();
            $agent = $this->getU8();
            $bc = $this->getU16();
            return sprintf('ADelegate %d, %d', $agent, $bc);
        }

        return sprintf('DB 0x%02X', $opcode);
    }

    private function isFormatC(int $opcode): bool
    {
        $fmtC = [
            0x21, 0x22, 0x23, 0x24, 0x25, 0x26, 0x27,
            0x2A, 0x2B, 0x2C, 0x2D, 0x2E, 0x2F, 0x30, 0x31,
            0x32, 0x33, 0x34, 0x35, 0x36, 0x37,
            0x41, 0x42, 0x43, 0x44, 0x45, 0x46, 0x47,
            0x48, 0x49, 0x4A, 0x4B, 0x4C, 0x4D,
            0x4E, 0x4F, 0x50, 0x51, 0x52, 0x53,
            0x54, 0x55, 0x56, 0x57, 0x58, 0x59,
            0x60, 0x61, 0x62, 0x63,
            0x90, 0x91, 0x92,
            0xA0, 0xA1, 0xA2, 0xA3, 0xA4, 0xA5,
            0xB2, 0xB3, 0xB4,
        ];
        return in_array($opcode, $fmtC);
    }

    private function getU8(): int
    {
        if ($this->pc >= $this->length) {
            return 0;
        }
        return ord($this->bytecode[$this->pc++]);
    }

    private function getU16(): int
    {
        $lo = $this->getU8();
        $hi = $this->getU8();
        return $lo | ($hi << 8);
    }

    private function getI16(): int
    {
        $val = $this->getU16();
        if ($val >= 0x8000) {
            $val = $val - 0x10000;
        }
        return $val;
    }

    private function getHexBytes(int $start, int $len): string
    {
        $hex = [];
        for ($i = 0; $i < $len && ($start + $i) < $this->length; $i++) {
            $hex[] = sprintf('%02X', ord($this->bytecode[$start + $i]));
        }
        return implode(' ', $hex);
    }

    private function getOpcodeName(int $opcode): string
    {
        return self::OPCODE_NAMES[$opcode] ?? sprintf('DB_0x%02X', $opcode);
    }

    private function getRegisterName(int $reg): string
    {
        return self::REGISTER_NAMES[$reg & 0x0F] ?? "R{$reg}";
    }

    public static function disassembleString(string $bytecode): string
    {
        $d = new self($bytecode);
        return $d->disassemble();
    }
}