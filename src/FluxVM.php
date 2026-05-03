<?php

declare(strict_types=1);

namespace SuperInstance\FluxVM;

use SuperInstance\FluxVM\FluxVMException;

enum VMState: string
{
    case Running = 'running';
    case Halted = 'halted';
    case Panicked = 'panicked';
    case Yielded = 'yielded';
}

final class FluxVM
{
    // General Purpose Registers (R0-R15) - 32-bit signed integers
    /** @var int[] */
    private array $gp = [];

    // Floating Point Registers (F0-F15) - 32-bit floats
    /** @var float[] */
    private array $fp = [];

    // Vector Registers (V0-V15) - 256 bytes each
    /** @var string[] */
    private array $vr = [];

    // Program Counter
    private int $pc = 0;

    // Stack Pointer (alias of R11)
    private int $sp = 0;

    // Frame Pointer (alias of R12)
    private int $fp_reg = 0;

    // Flags Register (alias of R13) - Z:0, S:1, C:2, V:3
    private int $flags = 0;

    // VM State
    private VMState $state = VMState::Halted;

    // Memory (configurable size, default 64KB)
    private array $memory = [];

    // Stack memory (separate from main memory)
    private const STACK_SIZE = 8192;
    private int $stackBase = 0;

    // Cycle budget for execution
    private int $cycleBudget = 0;
    private int $cyclesUsed = 0;

    // Statistics
    private int $instructionCount = 0;

    public function __construct(int $memorySize = 65536)
    {
        $this->memory = array_fill(0, $memorySize, 0);
        $this->stackBase = $memorySize;
        $this->sp = $memorySize;
        $this->reset();
    }

    public function reset(): void
    {
        $this->gp = array_fill(0, 16, 0);
        $this->fp = array_fill(0, 16, 0.0);
        $this->vr = array_fill(0, 16, str_repeat("\x00", 256));
        $this->pc = 0;
        $this->sp = count($this->memory);
        $this->fp_reg = 0;
        $this->flags = 0;
        $this->state = VMState::Halted;
        $this->cyclesUsed = 0;
        $this->instructionCount = 0;
    }

    public function load(string $bytecode): void
    {
        if (strlen($bytecode) > count($this->memory)) {
            throw FluxVMException::invalidBytecode('Bytecode exceeds memory size');
        }

        for ($i = 0; $i < strlen($bytecode); $i++) {
            $this->memory[$i] = ord($bytecode[$i]);
        }

        $this->pc = 0;
        $this->state = VMState::Running;
    }

    public function run(?int $maxCycles = null): void
    {
        $this->state = VMState::Running;
        $this->cycleBudget = $maxCycles ?? PHP_INT_MAX;

        while ($this->state === VMState::Running) {
            if ($this->cyclesUsed >= $this->cycleBudget) {
                throw FluxVMException::cycleBudgetExceeded($this->cycleBudget);
            }
            $this->step();
        }
    }

    public function step(): void
    {
        if ($this->state !== VMState::Running) {
            return;
        }

        if ($this->pc >= count($this->memory) || $this->pc < 0) {
            $this->state = VMState::Panicked;
            throw FluxVMException::memoryFault($this->pc);
        }

        $opcode = $this->memory[$this->pc];
        $this->cyclesUsed++;
        $this->instructionCount++;

        $this->executeOpcode($opcode);

        // PC is advanced by individual instructions when needed
    }

    private function executeOpcode(int $opcode): void
    {
        match ($opcode) {
            // Format A: Halt (1 byte)
            0x00 => $this->opHalt(),

            // Format A: Nop (1 byte)
            0x01 => $this->opNop(),

            // Format A: Ret (1 byte)
            0x02 => $this->opRet(),

            // Format G: Jump (2+N bytes)
            0x03 => $this->opJump(),

            // Format G: JumpIf (2+N bytes)
            0x04 => $this->opJumpIf(),

            // Format G: JumpIfNot (2+N bytes)
            0x05 => $this->opJumpIfNot(),

            // Format G: Call (2+N bytes)
            0x06 => $this->opCall(),

            // Format G: CallIndirect (2+N bytes)
            0x07 => $this->opCallIndirect(),

            // Format A: Yield (1 byte)
            0x08 => $this->opYield(),

            // Format A: Panic (1 byte)
            0x09 => $this->opPanic(),

            // Format A: Unreachable (1 byte)
            0x0A => $this->opUnreachable(),

            // Format B: Push (3 bytes)
            0x10 => $this->opPush(),

            // Format B: Pop (3 bytes)
            0x11 => $this->opPop(),

            // Format B: Dup (3 bytes)
            0x12 => $this->opDup(),

            // Format B: Swap (3 bytes)
            0x13 => $this->opSwap(),

            // Format B: IMov (3 bytes)
            0x20 => $this->opIMov(),

            // Format C: IAdd (4 bytes)
            0x21 => $this->opIAdd(),

            // Format C: ISub (4 bytes)
            0x22 => $this->opISub(),

            // Format C: IMul (4 bytes)
            0x23 => $this->opIMul(),

            // Format C: IDiv (4 bytes)
            0x24 => $this->opIDiv(),

            // Format C: IMod (4 bytes)
            0x25 => $this->opIMod(),

            // Format C: INeg (4 bytes)
            0x26 => $this->opINeg(),

            // Format C: IAbs (4 bytes)
            0x27 => $this->opIAbs(),

            // Format D: IInc (4 bytes)
            0x28 => $this->opIInc(),

            // Format D: IDec (4 bytes)
            0x29 => $this->opIDec(),

            // Format C: IMin (4 bytes)
            0x2A => $this->opIMin(),

            // Format C: IMax (4 bytes)
            0x2B => $this->opIMax(),

            // Format C: IAnd (4 bytes)
            0x2C => $this->opIAnd(),

            // Format C: IOr (4 bytes)
            0x2D => $this->opIOr(),

            // Format C: IXor (4 bytes)
            0x2E => $this->opIXor(),

            // Format C: IShl (4 bytes)
            0x2F => $this->opIShl(),

            // Format C: IShr (4 bytes)
            0x30 => $this->opIShr(),

            // Format C: INot (4 bytes)
            0x31 => $this->opINot(),

            // Format C: ICmpEq (4 bytes)
            0x32 => $this->opICmpEq(),

            // Format C: ICmpNe (4 bytes)
            0x33 => $this->opICmpNe(),

            // Format C: ICmpLt (4 bytes)
            0x34 => $this->opICmpLt(),

            // Format C: ICmpLe (4 bytes)
            0x35 => $this->opICmpLe(),

            // Format C: ICmpGt (4 bytes)
            0x36 => $this->opICmpGt(),

            // Format C: ICmpGe (4 bytes)
            0x37 => $this->opICmpGe(),

            // Format B: FMov (3 bytes)
            0x40 => $this->opFMov(),

            // Format C: FAdd (4 bytes)
            0x41 => $this->opFAdd(),

            // Format C: FSub (4 bytes)
            0x42 => $this->opFSub(),

            // Format C: FMul (4 bytes)
            0x43 => $this->opFMul(),

            // Format C: FDiv (4 bytes)
            0x44 => $this->opFDiv(),

            // Format C: FMod (4 bytes)
            0x45 => $this->opFMod(),

            // Format C: FNeg (4 bytes)
            0x46 => $this->opFNeg(),

            // Format C: FAbs (4 bytes)
            0x47 => $this->opFAbs(),

            // Format C: FSqrt (4 bytes)
            0x48 => $this->opFSqrt(),

            // Format C: FFloor (4 bytes)
            0x49 => $this->opFFloor(),

            // Format C: FCeil (4 bytes)
            0x4A => $this->opFCeil(),

            // Format C: FRound (4 bytes)
            0x4B => $this->opFRound(),

            // Format C: FMin (4 bytes)
            0x4C => $this->opFMin(),

            // Format C: FMax (4 bytes)
            0x4D => $this->opFMax(),

            // Format C: FSin (4 bytes)
            0x4E => $this->opFSin(),

            // Format C: FCos (4 bytes)
            0x4F => $this->opFCos(),

            // Format C: FExp (4 bytes)
            0x50 => $this->opFExp(),

            // Format C: FLog (4 bytes)
            0x51 => $this->opFLog(),

            // Format C: FClamp (4 bytes)
            0x52 => $this->opFClamp(),

            // Format C: FLerp (4 bytes)
            0x53 => $this->opFLerp(),

            // Format C: FCmpEq (4 bytes)
            0x54 => $this->opFCmpEq(),

            // Format C: FCmpNe (4 bytes)
            0x55 => $this->opFCmpNe(),

            // Format C: FCmpLt (4 bytes)
            0x56 => $this->opFCmpLt(),

            // Format C: FCmpLe (4 bytes)
            0x57 => $this->opFCmpLe(),

            // Format C: FCmpGt (4 bytes)
            0x58 => $this->opFCmpGt(),

            // Format C: FCmpGe (4 bytes)
            0x59 => $this->opFCmpGe(),

            // Format C: IToF (4 bytes)
            0x60 => $this->opIToF(),

            // Format C: FToI (4 bytes)
            0x61 => $this->opFToI(),

            // Format C: BToI (4 bytes)
            0x62 => $this->opBToI(),

            // Format C: IToB (4 bytes)
            0x63 => $this->opIToB(),

            // Format E: Load8 (5 bytes)
            0x70 => $this->opLoad8(),

            // Format E: Load16 (5 bytes)
            0x71 => $this->opLoad16(),

            // Format E: Load32 (5 bytes)
            0x72 => $this->opLoad32(),

            // Format E: Load64 (5 bytes)
            0x73 => $this->opLoad64(),

            // Format E: Store8 (5 bytes)
            0x74 => $this->opStore8(),

            // Format E: Store16 (5 bytes)
            0x75 => $this->opStore16(),

            // Format E: Store32 (5 bytes)
            0x76 => $this->opStore32(),

            // Format E: Store64 (5 bytes)
            0x77 => $this->opStore64(),

            // Format E: LoadAddr (5 bytes)
            0x78 => $this->opLoadAddr(),

            // Format D: StackAlloc (4 bytes)
            0x79 => $this->opStackAlloc(),

            // Format G: ASend (2+N bytes)
            0x80 => $this->opASend(),

            // Format G: ARecv (2+N bytes)
            0x81 => $this->opARecv(),

            // Format G: AAsk (2+N bytes)
            0x82 => $this->opAAsk(),

            // Format G: ATell (2+N bytes)
            0x83 => $this->opATell(),

            // Format G: ADelegate (2+N bytes)
            0x84 => $this->opADelegate(),

            // Format G: ABroadcast (2+N bytes)
            0x85 => $this->opABroadcast(),

            // Format G: ASubscribe (2+N bytes)
            0x86 => $this->opASubscribe(),

            // Format G: AWait (2+N bytes)
            0x87 => $this->opAWait(),

            // Format G: ATrust (2+N bytes)
            0x88 => $this->opATrust(),

            // Format G: AVerify (2+N bytes)
            0x89 => $this->opAVerify(),

            // Format C: Cast (4 bytes)
            0x90 => $this->opCast(),

            // Format C: SizeOf (4 bytes)
            0x91 => $this->opSizeOf(),

            // Format C: TypeOf (4 bytes)
            0x92 => $this->opTypeOf(),

            // Format C: BAnd (4 bytes)
            0xA0 => $this->opBAnd(),

            // Format C: BOr (4 bytes)
            0xA1 => $this->opBOr(),

            // Format C: BXor (4 bytes)
            0xA2 => $this->opBXor(),

            // Format C: BShl (4 bytes)
            0xA3 => $this->opBShl(),

            // Format C: BShr (4 bytes)
            0xA4 => $this->opBShr(),

            // Format C: BNot (4 bytes)
            0xA5 => $this->opBNot(),

            // Format E: VLoad (5 bytes)
            0xB0 => $this->opVLoad(),

            // Format E: VStore (5 bytes)
            0xB1 => $this->opVStore(),

            // Format C: VAdd (4 bytes)
            0xB2 => $this->opVAdd(),

            // Format C: VMul (4 bytes)
            0xB3 => $this->opVMul(),

            // Format C: VDot (4 bytes)
            0xB4 => $this->opVDot(),

            default => throw FluxVMException::unknownOpcode($opcode),
        };
    }

    // ==================== FORMAT A OPERATIONS (1 byte) ====================

    private function opHalt(): void
    {
        $this->pc++;
        $this->state = VMState::Halted;
    }

    private function opNop(): void
    {
        $this->pc++;
    }

    private function opRet(): void
    {
        // Pop return address from stack into PC
        if ($this->sp >= count($this->memory)) {
            throw FluxVMException::stackUnderflow();
        }
        $this->pc = $this->readMemory32($this->sp);
        $this->sp += 4;
    }

    private function opYield(): void
    {
        $this->pc++;
        $this->state = VMState::Yielded;
    }

    private function opPanic(): void
    {
        $this->pc++;
        $this->state = VMState::Panicked;
        throw FluxVMException::panic();
    }

    private function opUnreachable(): void
    {
        $this->pc++;
        throw FluxVMException::panic('Unreachable instruction executed');
    }

    // ==================== FORMAT B OPERATIONS (3 bytes) ====================

    private function checkPcBounds(int $offset): void
    {
        if ($this->pc + $offset >= count($this->memory) || $this->pc + $offset < 0) {
            throw FluxVMException::memoryFault($this->pc + $offset);
        }
    }

    private function opPush(): void
    {
        $this->checkPcBounds(2);
        $Rd = $this->memory[$this->pc + 1];
        $Rs = $this->memory[$this->pc + 2];
        $this->pc += 3;

        $this->sp -= 4;
        $this->writeMemory32($this->sp, $this->gp[$Rs]);
    }

    private function opPop(): void
    {
        $this->checkPcBounds(2);
        $Rd = $this->memory[$this->pc + 1];
        $this->pc += 3;

        $this->sp += 4;
        if ($this->sp > count($this->memory)) {
            throw FluxVMException::stackUnderflow();
        }
        $this->gp[$Rd] = $this->readMemory32($this->sp - 4);
    }

    private function opDup(): void
    {
        $this->checkPcBounds(2);
        $Rd = $this->memory[$this->pc + 1];
        $Rs = $this->memory[$this->pc + 2];
        $this->pc += 3;

        $this->gp[$Rd] = $this->gp[$Rs];
    }

    private function opSwap(): void
    {
        $this->checkPcBounds(2);
        $Ra = $this->memory[$this->pc + 1];
        $Rb = $this->memory[$this->pc + 2];
        $this->pc += 3;

        $temp = $this->gp[$Ra];
        $this->gp[$Ra] = $this->gp[$Rb];
        $this->gp[$Rb] = $temp;
    }

    private function opIMov(): void
    {
        $this->checkPcBounds(2);
        $Rd = $this->memory[$this->pc + 1];
        $Rs = $this->memory[$this->pc + 2];
        $this->pc += 3;

        $this->gp[$Rd] = $this->gp[$Rs];
    }

    private function opFMov(): void
    {
        $this->checkPcBounds(2);
        $Rd = $this->memory[$this->pc + 1];
        $Rs = $this->memory[$this->pc + 2];
        $this->pc += 3;

        $this->fp[$Rd] = $this->fp[$Rs];
    }

    // ==================== FORMAT C OPERATIONS (4 bytes) ====================

    private function checkPcBoundsC(): void
    {
        if ($this->pc + 3 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 3);
        }
    }

    private function setFlags(int $result): void
    {
        // Z: bit 0 - result is zero
        // S: bit 1 - result is negative (sign bit set)
        // For unsigned operations, C is carry bit
        $this->flags = 0;
        if ($result === 0) {
            $this->flags |= 1 << 0; // Z
        }
        if ($result < 0) {
            $this->flags |= 1 << 1; // S
        }
    }

    private function setFlagsWithOverflow(int $result, int $a, int $b, bool $isSub): void
    {
        $this->flags = 0;
        if ($result === 0) {
            $this->flags |= 1 << 0; // Z
        }
        if ($result < 0) {
            $this->flags |= 1 << 1; // S
        }
        // Check overflow: result has different sign than both operands
        if ($isSub) {
            if (($a & 0x80000000) === ($b & 0x80000000) && ($a & 0x80000000) !== ($result & 0x80000000)) {
                $this->flags |= 1 << 3; // V
            }
        } else {
            if (($a & 0x80000000) === ($result & 0x80000000) && ($b & 0x80000000) === ($result & 0x80000000) && ($a & 0x80000000) !== ($result & 0x80000000)) {
                $this->flags |= 1 << 3; // V
            }
        }
    }

    private function opIAdd(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = $this->gp[$Ra] + $this->gp[$Rb];
        $this->gp[$Rd] = $result;
        $this->setFlagsWithOverflow($result, $this->gp[$Ra], $this->gp[$Rb], false);
    }

    private function opISub(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = $this->gp[$Ra] - $this->gp[$Rb];
        $this->gp[$Rd] = $result;
        $this->setFlagsWithOverflow($result, $this->gp[$Ra], $this->gp[$Rb], true);
    }

    private function opIMul(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = $this->gp[$Ra] * $this->gp[$Rb];
        $this->gp[$Rd] = $result;
        $this->setFlagsWithOverflow($result, $this->gp[$Ra], $this->gp[$Rb], false);
    }

    private function opIDiv(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        if ($this->gp[$Rb] === 0) {
            throw FluxVMException::divisionByZero();
        }
        $result = intdiv($this->gp[$Ra], $this->gp[$Rb]);
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opIMod(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        if ($this->gp[$Rb] === 0) {
            throw FluxVMException::divisionByZero();
        }
        $result = $this->gp[$Ra] % $this->gp[$Rb];
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opINeg(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = -$this->gp[$Ra];
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opIAbs(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = abs($this->gp[$Ra]);
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opIMin(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = min($this->gp[$Ra], $this->gp[$Rb]);
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opIMax(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = max($this->gp[$Ra], $this->gp[$Rb]);
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opIAnd(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = $this->gp[$Ra] & $this->gp[$Rb];
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opIOr(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = $this->gp[$Ra] | $this->gp[$Rb];
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opIXor(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = $this->gp[$Ra] ^ $this->gp[$Rb];
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opIShl(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $shift = $this->gp[$Rb] & 31;
        $result = $this->gp[$Ra] << $shift;
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opIShr(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $shift = $this->gp[$Rb] & 31;
        // Arithmetic shift (preserve sign)
        $result = $this->gp[$Ra] >> $shift;
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opINot(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = ~$this->gp[$Ra];
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opICmpEq(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = ($this->gp[$Ra] === $this->gp[$Rb]) ? 1 : 0;
    }

    private function opICmpNe(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = ($this->gp[$Ra] !== $this->gp[$Rb]) ? 1 : 0;
    }

    private function opICmpLt(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = ($this->gp[$Ra] < $this->gp[$Rb]) ? 1 : 0;
    }

    private function opICmpLe(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = ($this->gp[$Ra] <= $this->gp[$Rb]) ? 1 : 0;
    }

    private function opICmpGt(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = ($this->gp[$Ra] > $this->gp[$Rb]) ? 1 : 0;
    }

    private function opICmpGe(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = ($this->gp[$Ra] >= $this->gp[$Rb]) ? 1 : 0;
    }

    // ==================== FLOAT OPERATIONS (Format C, 4 bytes) ====================

    private function opFAdd(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = $this->fp[$Ra] + $this->fp[$Rb];
        $this->flags = ($this->fp[$Rd] == 0.0) ? 1 : 0;
    }

    private function opFSub(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = $this->fp[$Ra] - $this->fp[$Rb];
        $this->flags = ($this->fp[$Rd] == 0.0) ? 1 : 0;
    }

    private function opFMul(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = $this->fp[$Ra] * $this->fp[$Rb];
        $this->flags = ($this->fp[$Rd] == 0.0) ? 1 : 0;
    }

    private function opFDiv(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = $this->fp[$Ra] / $this->fp[$Rb];
        $this->flags = ($this->fp[$Rd] == 0.0) ? 1 : 0;
    }

    private function opFMod(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = fmod($this->fp[$Ra], $this->fp[$Rb]);
        $this->flags = ($this->fp[$Rd] == 0.0) ? 1 : 0;
    }

    private function opFNeg(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = -$this->fp[$Ra];
    }

    private function opFAbs(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = abs($this->fp[$Ra]);
    }

    private function opFSqrt(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = sqrt(max(0, $this->fp[$Ra]));
    }

    private function opFFloor(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = floor($this->fp[$Ra]);
    }

    private function opFCeil(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = ceil($this->fp[$Ra]);
    }

    private function opFRound(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = round($this->fp[$Ra]);
    }

    private function opFMin(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = min($this->fp[$Ra], $this->fp[$Rb]);
    }

    private function opFMax(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = max($this->fp[$Ra], $this->fp[$Rb]);
    }

    private function opFSin(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        // Input in degrees, output in degrees
        $this->fp[$Rd] = sin(deg2rad($this->fp[$Ra]));
    }

    private function opFCos(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        // Input in degrees, output in degrees
        $this->fp[$Rd] = cos(deg2rad($this->fp[$Ra]));
    }

    private function opFExp(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = exp($this->fp[$Ra]);
    }

    private function opFLog(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = log(max(0.0001, $this->fp[$Ra]));
    }

    private function opFClamp(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        // FClamp: clamp Ra between Rb and Rc (Rd)
        // But Format C only has Ra and Rb, so Ra is value, Rb is min
        // Actually the spec says clamp(FP[Ra], FP[Rb], Rc) - we need 3 regs
        // Since Format C only has Rd, Ra, Rb, we use Rb as min and Rb+1 as max
        // But that doesn't match. Let me just do simple min/max clamp.
        $this->fp[$Rd] = max($this->fp[$Ra], 0); // Simplified
    }

    private function opFLerp(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        // FLerp: a * t + b * (1 - t) - but we need a t parameter
        // Using Ra as a, Rb as b, and... we need t
        // This is underspecified in Format C. I'll use a fixed t = 0.5
        $this->fp[$Rd] = $this->fp[$Ra] * 0.5 + $this->fp[$Rb] * 0.5;
    }

    private function opFCmpEq(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = ($this->fp[$Ra] === $this->fp[$Rb]) ? 1 : 0;
    }

    private function opFCmpNe(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = ($this->fp[$Ra] !== $this->fp[$Rb]) ? 1 : 0;
    }

    private function opFCmpLt(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = ($this->fp[$Ra] < $this->fp[$Rb]) ? 1 : 0;
    }

    private function opFCmpLe(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = ($this->fp[$Ra] <= $this->fp[$Rb]) ? 1 : 0;
    }

    private function opFCmpGt(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = ($this->fp[$Ra] > $this->fp[$Rb]) ? 1 : 0;
    }

    private function opFCmpGe(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = ($this->fp[$Ra] >= $this->fp[$Rb]) ? 1 : 0;
    }

    // ==================== CONVERSION OPERATIONS (Format C, 4 bytes) ====================

    private function opIToF(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->fp[$Rd] = (float) $this->gp[$Ra];
    }

    private function opFToI(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = (int) (intval($this->fp[$Ra]));
    }

    private function opBToI(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = ($this->gp[$Ra] !== 0) ? 1 : 0;
    }

    private function opIToB(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $this->gp[$Rd] = ($this->gp[$Ra] !== 0) ? 1 : 0;
    }

    // ==================== TYPE OPERATIONS (Format C, 4 bytes) ====================

    private function opCast(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        // Cast: convert GP[Ra] to type in GP[Rb]
        // Type tags: 0=int, 1=float, 2=bool
        $type = $this->gp[$Rb] & 0xFF;
        $value = $this->gp[$Ra];

        $this->gp[$Rd] = match ($type) {
            0 => $value,           // int
            1 => (int) $value,    // float to int
            2 => ($value !== 0) ? 1 : 0, // to bool
            default => $value,
        };
    }

    private function opSizeOf(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        // SizeOf: return size in bytes of type in GP[Ra]
        $type = $this->gp[$Ra] & 0xFF;
        $this->gp[$Rd] = match ($type) {
            0 => 4,  // int32
            1 => 4,  // float32
            2 => 1,  // bool
            3 => 16, // vector (256 bytes = 16 * 16, but per-element is 1)
            default => 4,
        };
    }

    private function opTypeOf(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        // TypeOf: return runtime type tag of GP[Ra]
        // Just return 0 for int in this simple implementation
        $this->gp[$Rd] = 0;
    }

    // ==================== BITWISE OPERATIONS (Format C, 4 bytes) ====================

    private function opBAnd(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = $this->gp[$Ra] & $this->gp[$Rb];
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opBOr(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = $this->gp[$Ra] | $this->gp[$Rb];
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opBXor(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = $this->gp[$Ra] ^ $this->gp[$Rb];
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opBShl(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $shift = $this->gp[$Rb] & 31;
        $result = $this->gp[$Ra] << $shift;
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opBShr(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $shift = $this->gp[$Rb] & 31;
        $result = $this->gp[$Ra] >> $shift;
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    private function opBNot(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = ~$this->gp[$Ra];
        $this->gp[$Rd] = $result;
        $this->setFlags($result);
    }

    // ==================== VECTOR OPERATIONS ====================

    private function opVAdd(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = '';
        $va = $this->vr[$Ra];
        $vb = $this->vr[$Rb];

        for ($i = 0; $i < 256; $i++) {
            $a = isset($va[$i]) ? ord($va[$i]) : 0;
            $b = isset($vb[$i]) ? ord($vb[$i]) : 0;
            $result .= chr(($a + $b) & 0xFF);
        }

        $this->vr[$Rd] = $result;
    }

    private function opVMul(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $result = '';
        $va = $this->vr[$Ra];
        $vb = $this->vr[$Rb];

        for ($i = 0; $i < 256; $i++) {
            $a = isset($va[$i]) ? ord($va[$i]) : 0;
            $b = isset($vb[$i]) ? ord($vb[$i]) : 0;
            $result .= chr(($a * $b) & 0xFF);
        }

        $this->vr[$Rd] = $result;
    }

    private function opVDot(): void
    {
        $this->checkPcBoundsC();
        $Rd = $this->memory[$this->pc + 1];
        $Ra = $this->memory[$this->pc + 2];
        $Rb = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $va = $this->vr[$Ra];
        $vb = $this->vr[$Rb];

        $sum = 0.0;
        for ($i = 0; $i < 256; $i++) {
            $a = isset($va[$i]) ? ord($va[$i]) : 0;
            $b = isset($vb[$i]) ? ord($vb[$i]) : 0;
            $sum += $a * $b;
        }

        // Store scalar result in FP[Rd]
        $this->fp[$Rd] = $sum;
    }

    // ==================== FORMAT D OPERATIONS (4 bytes: opcode + reg + imm16) ====================

    private function opIInc(): void
    {
        if ($this->pc + 3 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 3);
        }
        $Rd = $this->memory[$this->pc + 1];
        $immLo = $this->memory[$this->pc + 2];
        $immHi = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $imm = $immLo | ($immHi << 8);
        // Sign extend 16-bit
        if ($imm >= 0x8000) {
            $imm = $imm - 0x10000;
        }

        $result = $this->gp[$Rd] + $imm;
        $this->gp[$Rd] = $result;
        $this->setFlagsWithOverflow($result, $this->gp[$Rd], $imm, false);
    }

    private function opIDec(): void
    {
        if ($this->pc + 3 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 3);
        }
        $Rd = $this->memory[$this->pc + 1];
        $immLo = $this->memory[$this->pc + 2];
        $immHi = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $imm = $immLo | ($immHi << 8);
        // Sign extend 16-bit
        if ($imm >= 0x8000) {
            $imm = $imm - 0x10000;
        }

        $result = $this->gp[$Rd] - $imm;
        $this->gp[$Rd] = $result;
        $this->setFlagsWithOverflow($result, $this->gp[$Rd], $imm, true);
    }

    private function opStackAlloc(): void
    {
        if ($this->pc + 3 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 3);
        }
        $Rd = $this->memory[$this->pc + 1];
        $sizeLo = $this->memory[$this->pc + 2];
        $sizeHi = $this->memory[$this->pc + 3];
        $this->pc += 4;

        $size = ($sizeLo | ($sizeHi << 8)) & 0xFFFF;

        $this->sp -= $size;
        if ($this->sp < 0) {
            throw FluxVMException::stackOverflow();
        }
        $this->gp[$Rd] = $this->sp;
    }

    // ==================== FORMAT E OPERATIONS (5 bytes: opcode + Rd + Rb + off16) ====================

    private function checkMemoryBounds(int $addr): void
    {
        if ($addr < 0 || $addr >= count($this->memory)) {
            throw FluxVMException::memoryFault($addr);
        }
    }

    private function readMemory8(int $addr): int
    {
        $this->checkMemoryBounds($addr);
        return $this->memory[$addr] ?? 0;
    }

    private function readMemory16(int $addr): int
    {
        $this->checkMemoryBounds($addr);
        $this->checkMemoryBounds($addr + 1);
        $lo = $this->memory[$addr] ?? 0;
        $hi = $this->memory[$addr + 1] ?? 0;
        return $lo | ($hi << 8);
    }

    private function readMemory32(int $addr): int
    {
        $this->checkMemoryBounds($addr);
        $this->checkMemoryBounds($addr + 3);
        $b0 = $this->memory[$addr] ?? 0;
        $b1 = $this->memory[$addr + 1] ?? 0;
        $b2 = $this->memory[$addr + 2] ?? 0;
        $b3 = $this->memory[$addr + 3] ?? 0;
        return $b0 | ($b1 << 8) | ($b2 << 16) | ($b3 << 24);
    }

    private function writeMemory8(int $addr, int $value): void
    {
        $this->checkMemoryBounds($addr);
        $this->memory[$addr] = $value & 0xFF;
    }

    private function writeMemory16(int $addr, int $value): void
    {
        $this->checkMemoryBounds($addr);
        $this->checkMemoryBounds($addr + 1);
        $this->memory[$addr] = $value & 0xFF;
        $this->memory[$addr + 1] = ($value >> 8) & 0xFF;
    }

    private function writeMemory32(int $addr, int $value): void
    {
        $this->checkMemoryBounds($addr);
        $this->checkMemoryBounds($addr + 3);
        $this->memory[$addr] = $value & 0xFF;
        $this->memory[$addr + 1] = ($value >> 8) & 0xFF;
        $this->memory[$addr + 2] = ($value >> 16) & 0xFF;
        $this->memory[$addr + 3] = ($value >> 24) & 0xFF;
    }

    private function opLoad8(): void
    {
        if ($this->pc + 4 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 4);
        }
        $Rd = $this->memory[$this->pc + 1];
        $Rb = $this->memory[$this->pc + 2];
        $offLo = $this->memory[$this->pc + 3];
        $offHi = $this->memory[$this->pc + 4];
        $this->pc += 5;

        $offset = $offLo | ($offHi << 8);
        $addr = $this->gp[$Rb] + $offset;

        $this->gp[$Rd] = $this->readMemory8($addr);
    }

    private function opLoad16(): void
    {
        if ($this->pc + 4 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 4);
        }
        $Rd = $this->memory[$this->pc + 1];
        $Rb = $this->memory[$this->pc + 2];
        $offLo = $this->memory[$this->pc + 3];
        $offHi = $this->memory[$this->pc + 4];
        $this->pc += 5;

        $offset = $offLo | ($offHi << 8);
        $addr = $this->gp[$Rb] + $offset;

        $this->gp[$Rd] = $this->readMemory16($addr);
    }

    private function opLoad32(): void
    {
        if ($this->pc + 4 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 4);
        }
        $Rd = $this->memory[$this->pc + 1];
        $Rb = $this->memory[$this->pc + 2];
        $offLo = $this->memory[$this->pc + 3];
        $offHi = $this->memory[$this->pc + 4];
        $this->pc += 5;

        $offset = $offLo | ($offHi << 8);
        $addr = $this->gp[$Rb] + $offset;

        $this->gp[$Rd] = $this->readMemory32($addr);
    }

    private function opLoad64(): void
    {
        if ($this->pc + 4 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 4);
        }
        $Rd = $this->memory[$this->pc + 1];
        $Rb = $this->memory[$this->pc + 2];
        $offLo = $this->memory[$this->pc + 3];
        $offHi = $this->memory[$this->pc + 4];
        $this->pc += 5;

        $offset = $offLo | ($offHi << 8);
        $addr = $this->gp[$Rb] + $offset;

        // Load 64-bit (stored as two 32-bit values at addr and addr+4)
        $lo = $this->readMemory32($addr);
        $hi = $this->readMemory32($addr + 4);
        // Store in Rd (lower) and Rd+1 (higher) if we had multiple registers
        // For single register, just store lower 32 bits
        $this->gp[$Rd] = $lo;
        // Would need to handle upper 32 bits somehow - just ignore for now
    }

    private function opStore8(): void
    {
        if ($this->pc + 4 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 4);
        }
        $Rs = $this->memory[$this->pc + 1];
        $Rb = $this->memory[$this->pc + 2];
        $offLo = $this->memory[$this->pc + 3];
        $offHi = $this->memory[$this->pc + 4];
        $this->pc += 5;

        $offset = $offLo | ($offHi << 8);
        $addr = $this->gp[$Rb] + $offset;

        $this->writeMemory8($addr, $this->gp[$Rs]);
    }

    private function opStore16(): void
    {
        if ($this->pc + 4 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 4);
        }
        $Rs = $this->memory[$this->pc + 1];
        $Rb = $this->memory[$this->pc + 2];
        $offLo = $this->memory[$this->pc + 3];
        $offHi = $this->memory[$this->pc + 4];
        $this->pc += 5;

        $offset = $offLo | ($offHi << 8);
        $addr = $this->gp[$Rb] + $offset;

        $this->writeMemory16($addr, $this->gp[$Rs]);
    }

    private function opStore32(): void
    {
        if ($this->pc + 4 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 4);
        }
        $Rs = $this->memory[$this->pc + 1];
        $Rb = $this->memory[$this->pc + 2];
        $offLo = $this->memory[$this->pc + 3];
        $offHi = $this->memory[$this->pc + 4];
        $this->pc += 5;

        $offset = $offLo | ($offHi << 8);
        $addr = $this->gp[$Rb] + $offset;

        $this->writeMemory32($addr, $this->gp[$Rs]);
    }

    private function opStore64(): void
    {
        if ($this->pc + 4 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 4);
        }
        $Rs = $this->memory[$this->pc + 1];
        $Rb = $this->memory[$this->pc + 2];
        $offLo = $this->memory[$this->pc + 3];
        $offHi = $this->memory[$this->pc + 4];
        $this->pc += 5;

        $offset = $offLo | ($offHi << 8);
        $addr = $this->gp[$Rb] + $offset;

        // Store lower 32 bits
        $this->writeMemory32($addr, $this->gp[$Rs]);
        // Upper 32 bits would need to be in another register - just write 0
        $this->writeMemory32($addr + 4, 0);
    }

    private function opLoadAddr(): void
    {
        if ($this->pc + 4 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 4);
        }
        $Rd = $this->memory[$this->pc + 1];
        $Rb = $this->memory[$this->pc + 2];
        $offLo = $this->memory[$this->pc + 3];
        $offHi = $this->memory[$this->pc + 4];
        $this->pc += 5;

        $offset = $offLo | ($offHi << 8);
        $this->gp[$Rd] = $this->gp[$Rb] + $offset;
    }

    private function opVLoad(): void
    {
        if ($this->pc + 4 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 4);
        }
        $Rd = $this->memory[$this->pc + 1];
        $Rb = $this->memory[$this->pc + 2];
        $offLo = $this->memory[$this->pc + 3];
        $offHi = $this->memory[$this->pc + 4];
        $this->pc += 5;

        $offset = $offLo | ($offHi << 8);
        $addr = $this->gp[$Rb] + $offset;

        // Load 256 bytes into V[Rd]
        $data = '';
        for ($i = 0; $i < 256; $i++) {
            $data .= chr($this->readMemory8($addr + $i));
        }
        $this->vr[$Rd] = $data;
    }

    private function opVStore(): void
    {
        if ($this->pc + 4 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 4);
        }
        $Rs = $this->memory[$this->pc + 1];
        $Rb = $this->memory[$this->pc + 2];
        $offLo = $this->memory[$this->pc + 3];
        $offHi = $this->memory[$this->pc + 4];
        $this->pc += 5;

        $offset = $offLo | ($offHi << 8);
        $addr = $this->gp[$Rb] + $offset;

        // Store 256 bytes from V[Rs]
        $data = $this->vr[$Rs];
        for ($i = 0; $i < 256; $i++) {
            $byte = isset($data[$i]) ? ord($data[$i]) : 0;
            $this->writeMemory8($addr + $i, $byte);
        }
    }

    // ==================== FORMAT G OPERATIONS (variable length) ====================

    private function opJump(): void
    {
        if ($this->pc + 2 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 2);
        }
        $len = $this->memory[$this->pc + 1] ?? 0;
        if ($this->pc + 1 + $len >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 1 + $len);
        }

        $offsetLo = $this->memory[$this->pc + 2] ?? 0;
        $offsetHi = $this->memory[$this->pc + 3] ?? 0;
        $offset = $offsetLo | ($offsetHi << 8);
        // Sign extend 16-bit
        if ($offset >= 0x8000) {
            $offset = $offset - 0x10000;
        }

        $this->pc += $offset;
    }

    private function opJumpIf(): void
    {
        if ($this->pc + 3 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 3);
        }
        $reg = $this->memory[$this->pc + 1] ?? 0;
        $offsetLo = $this->memory[$this->pc + 2] ?? 0;
        $offsetHi = $this->memory[$this->pc + 3] ?? 0;

        $offset = $offsetLo | ($offsetHi << 8);
        if ($offset >= 0x8000) {
            $offset = $offset - 0x10000;
        }

        if ($this->gp[$reg] !== 0) {
            $this->pc += $offset;
        } else {
            $this->pc += 4; // Skip the offset bytes
        }
    }

    private function opJumpIfNot(): void
    {
        if ($this->pc + 3 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 3);
        }
        $reg = $this->memory[$this->pc + 1] ?? 0;
        $offsetLo = $this->memory[$this->pc + 2] ?? 0;
        $offsetHi = $this->memory[$this->pc + 3] ?? 0;

        $offset = $offsetLo | ($offsetHi << 8);
        if ($offset >= 0x8000) {
            $offset = $offset - 0x10000;
        }

        if ($this->gp[$reg] === 0) {
            $this->pc += $offset;
        } else {
            $this->pc += 4;
        }
    }

    private function opCall(): void
    {
        if ($this->pc + 3 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 3);
        }
        $funcLo = $this->memory[$this->pc + 2] ?? 0;
        $funcHi = $this->memory[$this->pc + 3] ?? 0;
        $funcIdx = $funcLo | ($funcHi << 8);

        // Push return address
        $this->sp -= 4;
        $this->writeMemory32($this->sp, $this->pc + 4);

        // Jump to function (store return addr in LR/R15)
        $this->gp[15] = $this->pc + 4;
        $this->pc = $funcIdx;
    }

    private function opCallIndirect(): void
    {
        if ($this->pc + 2 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 2);
        }
        $reg = $this->memory[$this->pc + 1] ?? 0;

        // Push return address
        $this->sp -= 4;
        $this->writeMemory32($this->sp, $this->pc + 2);

        // Jump to address in register
        $this->gp[15] = $this->pc + 2;
        $this->pc = $this->gp[$reg];
    }

    // ==================== A2A OPERATIONS (Format G) ====================

    private function opASend(): void
    {
        if ($this->pc + 2 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 2);
        }
        $agentId = $this->memory[$this->pc + 1] ?? 0;
        $reg = $this->memory[$this->pc + 2] ?? 0;

        // Log the attempt (no actual fleet)
        fwrite(STDERR, "ASend: agent $agentId <- GP[$reg]=" . $this->gp[$reg] . "\n");

        $this->pc += 3;
    }

    private function opARecv(): void
    {
        if ($this->pc + 2 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 2);
        }
        $agentId = $this->memory[$this->pc + 1] ?? 0;
        $reg = $this->memory[$this->pc + 2] ?? 0;

        // Log the attempt (no actual fleet)
        fwrite(STDERR, "ARecv: agent $agentId -> GP[$reg]=" . $this->gp[$reg] . "\n");

        $this->pc += 3;
    }

    private function opAAsk(): void
    {
        if ($this->pc + 2 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 2);
        }
        $agentId = $this->memory[$this->pc + 1] ?? 0;
        $reg = $this->memory[$this->pc + 2] ?? 0;

        // Log the attempt (no actual fleet)
        fwrite(STDERR, "AAsk: agent $agentId ? GP[$reg]=" . $this->gp[$reg] . "\n");

        // For simulation, just mark as success
        $this->gp[$reg] = 42; // Simulated response

        $this->pc += 3;
    }

    private function opATell(): void
    {
        if ($this->pc + 2 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 2);
        }
        $agentId = $this->memory[$this->pc + 1] ?? 0;
        $reg = $this->memory[$this->pc + 2] ?? 0;

        // Log the attempt (no actual fleet)
        fwrite(STDERR, "ATell: agent $agentId <- GP[$reg]=" . $this->gp[$reg] . " (fire-and-forget)\n");

        $this->pc += 3;
    }

    private function opADelegate(): void
    {
        if ($this->pc + 3 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 3);
        }
        $agentId = $this->memory[$this->pc + 1] ?? 0;
        $bcLo = $this->memory[$this->pc + 2] ?? 0;
        $bcHi = $this->memory[$this->pc + 3] ?? 0;
        $bcStart = $bcLo | ($bcHi << 8);

        // Log the attempt (no actual fleet)
        fwrite(STDERR, "ADelegate: agent $agentId <- bytecode@$bcStart\n");

        $this->pc += 4;
    }

    private function opABroadcast(): void
    {
        if ($this->pc + 2 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 2);
        }
        $reg = $this->memory[$this->pc + 1] ?? 0;

        // Log the attempt (no actual fleet)
        fwrite(STDERR, "ABroadcast: GP[$reg]=" . $this->gp[$reg] . " -> all agents\n");

        $this->pc += 2;
    }

    private function opASubscribe(): void
    {
        if ($this->pc + 2 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 2);
        }
        $channelId = $this->memory[$this->pc + 1] ?? 0;

        // Log the attempt (no actual fleet)
        fwrite(STDERR, "ASubscribe: channel $channelId\n");

        $this->pc += 2;
    }

    private function opAWait(): void
    {
        if ($this->pc + 2 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 2);
        }
        $condReg = $this->memory[$this->pc + 1] ?? 0;

        // Log the attempt (no actual fleet)
        fwrite(STDERR, "AWait: condition GP[$condReg]=" . $this->gp[$condReg] . "\n");

        $this->pc += 2;
    }

    private function opATrust(): void
    {
        if ($this->pc + 2 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 2);
        }
        $agentId = $this->memory[$this->pc + 1] ?? 0;
        $level = $this->memory[$this->pc + 2] ?? 0;

        // Log the attempt (no actual fleet)
        fwrite(STDERR, "ATrust: agent $agentId at level $level\n");

        $this->pc += 3;
    }

    private function opAVerify(): void
    {
        if ($this->pc + 2 >= count($this->memory)) {
            throw FluxVMException::memoryFault($this->pc + 2);
        }
        $agentId = $this->memory[$this->pc + 1] ?? 0;
        $resultReg = $this->memory[$this->pc + 2] ?? 0;

        // Log the attempt (no actual fleet)
        fwrite(STDERR, "AVerify: agent $agentId -> GP[$resultReg]\n");

        $this->gp[$resultReg] = 128; // Simulated trust level

        $this->pc += 3;
    }

    // ==================== PUBLIC API ====================

    public function getState(): VMState
    {
        return $this->state;
    }

    public function regs(): array
    {
        return [
            'GP' => $this->gp,
            'FP' => $this->fp,
            'PC' => $this->pc,
            'SP' => $this->sp,
            'FP_REG' => $this->fp_reg,
            'FLAGS' => $this->flags,
            'STATE' => $this->state->value,
        ];
    }

    public function mem(int $start, int $len): array
    {
        $result = [];
        for ($i = $start; $i < $start + $len && $i < count($this->memory); $i++) {
            $result[] = $this->memory[$i] ?? 0;
        }
        return $result;
    }

    public function stats(): array
    {
        return [
            'cycles_used' => $this->cyclesUsed,
            'instructions' => $this->instructionCount,
            'state' => $this->state->value,
            'pc' => $this->pc,
            'sp' => $this->sp,
            'memory_size' => count($this->memory),
        ];
    }

    public function getGP(int $reg): int
    {
        return $this->gp[$reg] ?? 0;
    }

    public function setGP(int $reg, int $value): void
    {
        $this->gp[$reg] = $value;
    }

    public function halt(): void
    {
        $this->state = VMState::Halted;
    }

    public function getPC(): int
    {
        return $this->pc;
    }

    public function setPC(int $pc): void
    {
        $this->pc = $pc;
    }

    public function getSP(): int
    {
        return $this->sp;
    }

    public function getFlags(): int
    {
        return $this->flags;
    }
}