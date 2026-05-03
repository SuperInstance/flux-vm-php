<?php

declare(strict_types=1);

namespace SuperInstance\FluxVM;

use SuperInstance\FluxVM\FluxVMException;

final class Assembler
{
    // Opcode map (name => opcode byte)
    private const OPCODES = [
        // Format A (1 byte)
        'Halt' => 0x00,
        'Nop' => 0x01,
        'Ret' => 0x02,
        'Yield' => 0x08,
        'Panic' => 0x09,
        'Unreachable' => 0x0A,

        // Format B (3 bytes)
        'Push' => 0x10,
        'Pop' => 0x11,
        'Dup' => 0x12,
        'Swap' => 0x13,
        'IMov' => 0x20,
        'FMov' => 0x40,

        // Format C (4 bytes)
        'IAdd' => 0x21,
        'ISub' => 0x22,
        'IMul' => 0x23,
        'IDiv' => 0x24,
        'IMod' => 0x25,
        'INeg' => 0x26,
        'IAbs' => 0x27,
        'IMin' => 0x2A,
        'IMax' => 0x2B,
        'IAnd' => 0x2C,
        'IOr' => 0x2D,
        'IXor' => 0x2E,
        'IShl' => 0x2F,
        'IShr' => 0x30,
        'INot' => 0x31,
        'ICmpEq' => 0x32,
        'ICmpNe' => 0x33,
        'ICmpLt' => 0x34,
        'ICmpLe' => 0x35,
        'ICmpGt' => 0x36,
        'ICmpGe' => 0x37,

        // Float arithmetic
        'FAdd' => 0x41,
        'FSub' => 0x42,
        'FMul' => 0x43,
        'FDiv' => 0x44,
        'FMod' => 0x45,
        'FNeg' => 0x46,
        'FAbs' => 0x47,
        'FSqrt' => 0x48,
        'FFloor' => 0x49,
        'FCeil' => 0x4A,
        'FRound' => 0x4B,
        'FMin' => 0x4C,
        'FMax' => 0x4D,
        'FSin' => 0x4E,
        'FCos' => 0x4F,
        'FExp' => 0x50,
        'FLog' => 0x51,
        'FClamp' => 0x52,
        'FLerp' => 0x53,
        'FCmpEq' => 0x54,
        'FCmpNe' => 0x55,
        'FCmpLt' => 0x56,
        'FCmpLe' => 0x57,
        'FCmpGt' => 0x58,
        'FCmpGe' => 0x59,

        // Conversions
        'IToF' => 0x60,
        'FToI' => 0x61,
        'BToI' => 0x62,
        'IToB' => 0x63,

        // Memory (Format E)
        'Load8' => 0x70,
        'Load16' => 0x71,
        'Load32' => 0x72,
        'Load64' => 0x73,
        'Store8' => 0x74,
        'Store16' => 0x75,
        'Store32' => 0x76,
        'Store64' => 0x77,
        'LoadAddr' => 0x78,

        // Format D
        'IInc' => 0x28,
        'IDec' => 0x29,
        'StackAlloc' => 0x79,

        // Vector
        'VLoad' => 0xB0,
        'VStore' => 0xB1,
        'VAdd' => 0xB2,
        'VMul' => 0xB3,
        'VDot' => 0xB4,

        // Type operations
        'Cast' => 0x90,
        'SizeOf' => 0x91,
        'TypeOf' => 0x92,

        // Bitwise
        'BAnd' => 0xA0,
        'BOr' => 0xA1,
        'BXor' => 0xA2,
        'BShl' => 0xA3,
        'BShr' => 0xA4,
        'BNot' => 0xA5,

        // Format G (control flow)
        'Jump' => 0x03,
        'JumpIf' => 0x04,
        'JumpIfNot' => 0x05,
        'Call' => 0x06,
        'CallIndirect' => 0x07,
        'ASend' => 0x80,
        'ARecv' => 0x81,
        'AAsk' => 0x82,
        'ATell' => 0x83,
        'ADelegate' => 0x84,
        'ABroadcast' => 0x85,
        'ASubscribe' => 0x86,
        'AWait' => 0x87,
        'ATrust' => 0x88,
        'AVerify' => 0x89,
    ];

    // Register aliases
    private const REGISTER_ALIASES = [
        'RV' => 8, 'A0' => 9, 'A1' => 10, 'SP' => 11, 'FP' => 12,
        'FL' => 13, 'TP' => 14, 'LR' => 15,
        'FV' => 8, 'FA0' => 9, 'FA1' => 10,
    ];

    private array $labels = [];
    private array $output = [];
    private int $pc = 0;

    public function assemble(string $source): string
    {
        $this->labels = [];
        $this->output = [];
        $this->pc = 0;

        $lines = explode("\n", $source);
        $cleanLines = [];

        // First pass:预处理（去除注释，收集标签）
        foreach ($lines as $lineNo => $line) {
            // Remove comments (#)
            $commentPos = strpos($line, '#');
            if ($commentPos !== false) {
                $line = substr($line, 0, $commentPos);
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Check for label (ends with :)
            if (preg_match('/^(\w+):$/', $line, $matches)) {
                $labelName = $matches[1];
                if (isset($this->labels[$labelName])) {
                    throw new \Exception("Duplicate label: $labelName");
                }
                $this->labels[$labelName] = $this->pc;
                continue;
            }

            $cleanLines[] = ['line' => $line, 'num' => $lineNo + 1];
        }

        // Second pass: 生成字节码
        foreach ($cleanLines as $item) {
            $this->assembleLine($item['line'], $item['num']);
        }

        return implode('', $this->output);
    }

    private function assembleLine(string $line, int $lineNum): void
    {
        // Parse instruction
        $parts = preg_split('/\s+/', $line);
        $opcode = strtoupper($parts[0]);
        $args = array_slice($parts, 1);

        if (!isset(self::OPCODES[$opcode])) {
            throw new \Exception("Unknown opcode at line $lineNum: $opcode");
        }

        $op = self::OPCODES[$opcode];

        // Determine format and encode
        match ($op) {
            // Format A: 1 byte (no operands)
            0x00, // Halt
            0x01, // Nop
            0x02, // Ret
            0x08, // Yield
            0x09, // Panic
            0x0A => // Unreachable
                $this->output[] = chr($op),

            // Format B: opcode(1) + Rd(1) + Rs(1) = 3 bytes
            0x10, // Push
            0x11, // Pop
            0x12, // Dup
            0x13, // Swap
            0x20, // IMov
            0x40 => // FMov
                $this->encodeB($op, $args, $lineNum),

            // Format C: opcode(1) + Rd(1) + Ra(1) + Rb(1) = 4 bytes
            0x21, 0x22, 0x23, 0x24, 0x25, 0x26, 0x27, // Integer arithmetic
            0x2A, 0x2B, 0x2C, 0x2D, 0x2E, 0x2F, 0x30, 0x31, // More integer
            0x32, 0x33, 0x34, 0x35, 0x36, 0x37, // Comparisons
            0x41, 0x42, 0x43, 0x44, 0x45, 0x46, 0x47, // Float ops
            0x48, 0x49, 0x4A, 0x4B, 0x4C, 0x4D, // More float
            0x4E, 0x4F, 0x50, 0x51, 0x52, 0x53, // Trig/exp
            0x54, 0x55, 0x56, 0x57, 0x58, 0x59, // Float comparisons
            0x60, 0x61, 0x62, 0x63, // Conversions
            0x90, 0x91, 0x92, // Type operations
            0xA0, 0xA1, 0xA2, 0xA3, 0xA4, 0xA5, // Bitwise
            0xB2, 0xB3, 0xB4 => // Vector (not VLoad/VStore)
                $this->encodeC($op, $args, $lineNum),

            // Format D: opcode(1) + Rd(1) + imm16(2) = 4 bytes
            0x28, // IInc
            0x29, // IDec
            0x79 => // StackAlloc
                $this->encodeD($op, $args, $lineNum),

            // Format E: opcode(1) + Rd(1) + Rb(1) + off16(2) = 5 bytes
            0x70, 0x71, 0x72, 0x73, // Load
            0x74, 0x75, 0x76, 0x77, // Store
            0x78, // LoadAddr
            0xB0, 0xB1 => // VLoad, VStore
                $this->encodeE($op, $args, $lineNum),

            // Format G: variable
            0x03, // Jump
            0x04, // JumpIf
            0x05 => // JumpIfNot
                $this->encodeJumpG($op, $args, $lineNum),

            0x06, // Call
            0x84 => // ADelegate
                $this->encodeCallG($op, $args, $lineNum),

            0x07 => // CallIndirect
                $this->encodeCallIndirectG($op, $args, $lineNum),

            0x80, // ASend
            0x81, // ARecv
            0x82, // AAsk
            0x83, // ATell
            0x88, // ATrust
            0x89 => // AVerify
                $this->encodeA2AG($op, $args, $lineNum),

            0x85, // ABroadcast
            0x86, // ASubscribe
            0x87 => // AWait
                $this->encodeA2ASimpleG($op, $args, $lineNum),

            default => throw new \Exception("Unhandled opcode at line $lineNum: $opcode (0x" . dechex($op) . ")"),
        };

        $this->advancePC($op);
    }

    private function parseRegister(string $reg): int
    {
        $reg = strtoupper(trim($reg));

        // Check alias
        if (isset(self::REGISTER_ALIASES[$reg])) {
            return self::REGISTER_ALIASES[$reg];
        }

        // Check R0-R15 format
        if (preg_match('/^R(\d+)$/', $reg, $matches)) {
            $num = (int) $matches[1];
            if ($num > 15) {
                throw new \Exception("Invalid register: $reg (must be R0-R15)");
            }
            return $num;
        }

        // Check F0-F15 format for float registers
        if (preg_match('/^F(\d+)$/', $reg, $matches)) {
            $num = (int) $matches[1];
            if ($num > 15) {
                throw new \Exception("Invalid float register: $reg (must be F0-F15)");
            }
            return $num;
        }

        // Check V0-V15 format for vector registers
        if (preg_match('/^V(\d+)$/', $reg, $matches)) {
            $num = (int) $matches[1];
            if ($num > 15) {
                throw new \Exception("Invalid vector register: $reg (must be V0-V15)");
            }
            return $num;
        }

        throw new \Exception("Invalid register: $reg");
    }

    private function parseImmediate(string $imm): int
    {
        $imm = trim($imm);

        // Hex
        if (str_starts_with(strtolower($imm), '0x')) {
            return intval($imm, 16);
        }

        // Decimal
        return intval($imm, 10);
    }

    private function encodeB(int $op, array $args, int $lineNum): void
    {
        if (count($args) < 2) {
            throw new \Exception("Format B requires 2 register arguments at line $lineNum");
        }

        $Rd = $this->parseRegister($args[0]);
        $Rs = $this->parseRegister($args[1]);

        $this->output[] = chr($op);
        $this->output[] = chr($Rd);
        $this->output[] = chr($Rs);
    }

    private function encodeC(int $op, array $args, int $lineNum): void
    {
        if (count($args) < 3) {
            throw new \Exception("Format C requires 3 arguments at line $lineNum");
        }

        $Rd = $this->parseRegister($args[0]);
        $Ra = $this->parseRegister($args[1]);
        $Rb = $this->parseRegister($args[2]);

        $this->output[] = chr($op);
        $this->output[] = chr($Rd);
        $this->output[] = chr($Ra);
        $this->output[] = chr($Rb);
    }

    private function encodeD(int $op, array $args, int $lineNum): void
    {
        if (count($args) < 2) {
            throw new \Exception("Format D requires register and immediate at line $lineNum");
        }

        $Rd = $this->parseRegister($args[0]);
        $imm = $this->parseImmediate($args[1]);

        // Sign-extend if needed
        $immLo = $imm & 0xFF;
        $immHi = ($imm >> 8) & 0xFF;

        $this->output[] = chr($op);
        $this->output[] = chr($Rd);
        $this->output[] = chr($immLo);
        $this->output[] = chr($immHi);
    }

    private function encodeE(int $op, array $args, int $lineNum): void
    {
        if (count($args) < 3) {
            throw new \Exception("Format E requires Rd, Rb, offset at line $lineNum");
        }

        $Rd = $this->parseRegister($args[0]);
        $Rb = $this->parseRegister($args[1]);
        $offset = $this->parseImmediate($args[2]);

        $offLo = $offset & 0xFF;
        $offHi = ($offset >> 8) & 0xFF;

        $this->output[] = chr($op);
        $this->output[] = chr($Rd);
        $this->output[] = chr($Rb);
        $this->output[] = chr($offLo);
        $this->output[] = chr($offHi);
    }

    private function encodeJumpG(int $op, array $args, int $lineNum): void
    {
        if (count($args) < 1) {
            throw new \Exception("Jump requires register or label at line $lineNum");
        }

        $target = $args[0];

        // Check if it's a label
        if (isset($this->labels[$target])) {
            $targetAddr = $this->labels[$target];
            $offset = $targetAddr - $this->pc - 4; // Relative offset
        } else {
            // Direct address or expression
            $offset = $this->parseImmediate($target);
        }

        // Sign-extend 16-bit
        $offLo = $offset & 0xFF;
        $offHi = ($offset >> 8) & 0xFF;

        $this->output[] = chr($op);
        $this->output[] = chr(2); // length
        $this->output[] = chr($offLo);
        $this->output[] = chr($offHi);
    }

    private function encodeCallG(int $op, array $args, int $lineNum): void
    {
        if (count($args) < 1) {
            throw new \Exception("Call requires function index at line $lineNum");
        }

        $func = $this->parseImmediate($args[0]);

        $funcLo = $func & 0xFF;
        $funcHi = ($func >> 8) & 0xFF;

        $this->output[] = chr($op);
        $this->output[] = chr(2); // length
        $this->output[] = chr($funcLo);
        $this->output[] = chr($funcHi);
    }

    private function encodeCallIndirectG(int $op, array $args, int $lineNum): void
    {
        if (count($args) < 1) {
            throw new \Exception("CallIndirect requires register at line $lineNum");
        }

        $reg = $this->parseRegister($args[0]);

        $this->output[] = chr($op);
        $this->output[] = chr(1); // length
        $this->output[] = chr($reg);
    }

    private function encodeA2AG(int $op, array $args, int $lineNum): void
    {
        if (count($args) < 2) {
            throw new \Exception("A2A op requires agent_id and register at line $lineNum");
        }

        $agentId = $this->parseImmediate($args[0]);
        $reg = $this->parseRegister($args[1]);

        $this->output[] = chr($op);
        $this->output[] = chr(2); // length
        $this->output[] = chr($agentId & 0xFF);
        $this->output[] = chr($reg);
    }

    private function encodeA2ASimpleG(int $op, array $args, int $lineNum): void
    {
        if (count($args) < 1) {
            throw new \Exception("A2A op requires register or channel at line $lineNum");
        }

        $val = $this->parseRegister($args[0]);

        $this->output[] = chr($op);
        $this->output[] = chr(1); // length
        $this->output[] = chr($val);
    }

    private function advancePC(int $op): void
    {
        $size = match ($op) {
            // Format A
            0x00, 0x01, 0x02, 0x08, 0x09, 0x0A => 1,
            // Format B
            0x10, 0x11, 0x12, 0x13, 0x20, 0x40 => 3,
            // Format C
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
            0xB2, 0xB3, 0xB4 => 4,
            // Format D
            0x28, 0x29, 0x79 => 4,
            // Format E
            0x70, 0x71, 0x72, 0x73,
            0x74, 0x75, 0x76, 0x77, 0x78,
            0xB0, 0xB1 => 5,
            // Format G - varies, but we emit variable
            default => 0, // Will be set correctly in encode functions
        };

        // For Format G, sizes vary - handle separately
        if (in_array($op, [0x03, 0x04, 0x05, 0x06, 0x07, 0x80, 0x81, 0x82, 0x83, 0x84, 0x85, 0x86, 0x87, 0x88, 0x89])) {
            // Already accounted in encode functions
        } else {
            $this->pc += $size;
        }
    }

    public static function assembleString(string $source): string
    {
        $asm = new self();
        return $asm->assemble($source);
    }
}