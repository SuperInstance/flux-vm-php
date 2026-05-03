# FLUX ISA v3.0 Virtual Machine

A pure PHP implementation of the FLUX (Fleet Language for Unified eXecution) ISA v3.0 virtual machine. Register-based bytecode VM designed for multi-agent fleet coordination.

## Installation

```bash
composer require superinstance/flux-vm
```

Or clone and use directly:

```bash
git clone https://github.com/SuperInstance/flux-vm-php.git
cd flux-vm-php
composer install
```

## Architecture Overview

### Register Model

FLUX provides three separate register files plus system registers:

**General Purpose Registers (GPR)** — 16 registers, 32-bit signed integers:

| Register | Alias | Purpose |
|----------|-------|---------|
| R0–R7 | — | General-purpose, caller-saved |
| R8 | RV | Return value |
| R9 | A0 | First function argument |
| R10 | A1 | Second function argument |
| R11 | SP | Stack pointer (descends) |
| R12 | FP | Frame pointer |
| R13 | FL | Flags register |
| R14 | TP | Temporary / reserved |
| R15 | LR | Link register (CALL stores return address) |

**Floating Point Registers (FPR)** — 16 registers, 32-bit IEEE 754:

| Register | Alias | Purpose |
|----------|-------|---------|
| F0–F7 | — | General-purpose, caller-saved |
| F8 | FV | Return value (float) |
| F9 | FA0 | First float argument |
| F10 | FA1 | Second float argument |
| F11–F15 | — | General-purpose |

**Vector Registers** — 16 registers, 256 bytes each (SIMD-style).

### Memory Model

- Flat memory with configurable size (default 64KB)
- Stack grows downward (SP starts at top of memory)
- Little-endian for all multi-byte values
- Memory operations use base register + 16-bit unsigned offset

### Flags Register (FL / R13)

| Bit | Name | Description |
|-----|------|-------------|
| Z | Zero | Result is zero |
| S | Sign | Result is negative |
| C | Carry | Unsigned overflow |
| V | Overflow | Signed overflow |

## Instruction Formats

### Format A — Nullary (1 byte)

```
[ opcode (1 byte) ]
```

Opcodes: `Halt`, `Nop`, `Ret`, `Panic`, `Unreachable`, `Yield`

### Format B — Two Registers (3 bytes)

```
[ opcode (1 byte) ][ reg_dst (1 byte) ][ reg_src (1 byte) ]
```

Opcodes: `Push`, `Pop`, `Dup`, `Swap`, `IMov`, `FMov`

### Format C — Three Registers (4 bytes)

```
[ opcode (1 byte) ][ reg_dst (1 byte) ][ reg_a (1 byte) ][ reg_b (1 byte) ]
```

Opcodes: All arithmetic, comparisons, conversions, bitwise, vector operations.

### Format D — Register + 16-bit Immediate (4 bytes)

```
[ opcode (1 byte) ][ reg (1 byte) ][ imm_lo (1 byte) ][ imm_hi (1 byte) ]
```

Opcodes: `IInc`, `IDec`, `StackAlloc`

### Format E — Two Registers + 16-bit Offset (5 bytes)

```
[ opcode (1 byte) ][ reg_dst (1 byte) ][ reg_base (1 byte) ][ off_lo (1 byte) ][ off_hi (1 byte) ]
```

Opcodes: `Load8`, `Load16`, `Load32`, `Load64`, `Store8`, `Store16`, `Store32`, `Store64`, `LoadAddr`, `VLoad`, `VStore`

### Format G — Variable-Length (2+N bytes)

```
[ opcode (1 byte) ][ length (1 byte) ][ payload (N bytes) ]
```

Opcodes: `Jump`, `JumpIf`, `JumpIfNot`, `Call`, `CallIndirect`, A2A operations.

## Opcode Coverage Table

### Control Flow (0x00–0x0F)

| Hex | Name | Format | Description |
|-----|------|--------|-------------|
| 0x00 | Halt | A | Stop execution |
| 0x01 | Nop | A | No operation |
| 0x02 | Ret | A | Return from function |
| 0x03 | Jump | G | Unconditional branch |
| 0x04 | JumpIf | G | Branch if register != 0 |
| 0x05 | JumpIfNot | G | Branch if register == 0 |
| 0x06 | Call | G | Call function by index |
| 0x07 | CallIndirect | G | Call function by address |
| 0x08 | Yield | A | Pause execution |
| 0x09 | Panic | A | Abort with error |
| 0x0A | Unreachable | A | Trap (should not execute) |

### Stack Operations (0x10–0x1F)

| Hex | Name | Format | Description |
|-----|------|--------|-------------|
| 0x10 | Push | B | Push GP[Rs] to stack |
| 0x11 | Pop | B | Pop stack to GP[Rd] |
| 0x12 | Dup | B | Copy register |
| 0x13 | Swap | B | Swap two registers |

### Integer Arithmetic (0x20–0x3F)

| Hex | Name | Format | Description |
|-----|------|--------|-------------|
| 0x20 | IMov | B | Copy GP register |
| 0x21 | IAdd | C | Rd = Ra + Rb |
| 0x22 | ISub | C | Rd = Ra - Rb |
| 0x23 | IMul | C | Rd = Ra * Rb |
| 0x24 | IDiv | C | Rd = Ra / Rb |
| 0x25 | IMod | C | Rd = Ra % Rb |
| 0x26 | INeg | C | Rd = -Ra |
| 0x27 | IAbs | C | Rd = abs(Ra) |
| 0x28 | IInc | D | Rd += immediate |
| 0x29 | IDec | D | Rd -= immediate |
| 0x2A | IMin | C | Rd = min(Ra, Rb) |
| 0x2B | IMax | C | Rd = max(Ra, Rb) |
| 0x2C | IAnd | C | Rd = Ra & Rb |
| 0x2D | IOr | C | Rd = Ra \| Rb |
| 0x2E | IXor | C | Rd = Ra ^ Rb |
| 0x2F | IShl | C | Rd = Ra << Rb |
| 0x30 | IShr | C | Rd = Ra >> Rb |
| 0x31 | INot | C | Rd = ~Ra |
| 0x32 | ICmpEq | C | Rd = (Ra == Rb) |
| 0x33 | ICmpNe | C | Rd = (Ra != Rb) |
| 0x34 | ICmpLt | C | Rd = (Ra < Rb) |
| 0x35 | ICmpLe | C | Rd = (Ra <= Rb) |
| 0x36 | ICmpGt | C | Rd = (Ra > Rb) |
| 0x37 | ICmpGe | C | Rd = (Ra >= Rb) |

### Float Arithmetic (0x40–0x5F)

| Hex | Name | Format | Description |
|-----|------|--------|-------------|
| 0x40 | FMov | B | Copy FP register |
| 0x41 | FAdd | C | Rd = Ra + Rb |
| 0x42 | FSub | C | Rd = Ra - Rb |
| 0x43 | FMul | C | Rd = Ra * Rb |
| 0x44 | FDiv | C | Rd = Ra / Rb |
| 0x45 | FMod | C | Rd = fmod(Ra, Rb) |
| 0x46 | FNeg | C | Rd = -Ra |
| 0x47 | FAbs | C | Rd = abs(Ra) |
| 0x48 | FSqrt | C | Rd = sqrt(Ra) |
| 0x49 | FFloor | C | Rd = floor(Ra) |
| 0x4A | FCeil | C | Rd = ceil(Ra) |
| 0x4B | FRound | C | Rd = round(Ra) |
| 0x4C | FMin | C | Rd = min(Ra, Rb) |
| 0x4D | FMax | C | Rd = max(Ra, Rb) |
| 0x4E | FSin | C | Rd = sin(Ra) |
| 0x4F | FCos | C | Rd = cos(Ra) |
| 0x50 | FExp | C | Rd = exp(Ra) |
| 0x51 | FLog | C | Rd = log(Ra) |
| 0x52 | FClamp | C | Rd = clamp(Ra, Rb) |
| 0x53 | FLerp | C | Rd = lerp(Ra, Rb) |
| 0x54 | FCmpEq | C | Rd = (Ra == Rb) |
| 0x55 | FCmpNe | C | Rd = (Ra != Rb) |
| 0x56 | FCmpLt | C | Rd = (Ra < Rb) |
| 0x57 | FCmpLe | C | Rd = (Ra <= Rb) |
| 0x58 | FCmpGt | C | Rd = (Ra > Rb) |
| 0x59 | FCmpGe | C | Rd = (Ra >= Rb) |

### Conversions (0x60–0x6F)

| Hex | Name | Format | Description |
|-----|------|--------|-------------|
| 0x60 | IToF | C | FP[Rd] = (float)GP[Ra] |
| 0x61 | FToI | C | GP[Rd] = (int)FP[Ra] |
| 0x62 | BToI | C | GP[Rd] = (Ra != 0) |
| 0x63 | IToB | C | GP[Rd] = (Ra != 0) |

### Memory Operations (0x70–0x7F)

| Hex | Name | Format | Description |
|-----|------|--------|-------------|
| 0x70 | Load8 | E | GP[Rd] = mem[Rb+off] (u8) |
| 0x71 | Load16 | E | GP[Rd] = mem[Rb+off] (u16) |
| 0x72 | Load32 | E | GP[Rd] = mem[Rb+off] (u32) |
| 0x73 | Load64 | E | GP[Rd] = mem[Rb+off] (u64) |
| 0x74 | Store8 | E | mem[Rb+off] = GP[Rs] |
| 0x75 | Store16 | E | mem[Rb+off] = GP[Rs] |
| 0x76 | Store32 | E | mem[Rb+off] = GP[Rs] |
| 0x77 | Store64 | E | mem[Rb+off] = GP[Rs] |
| 0x78 | LoadAddr | E | GP[Rd] = Rb + off |
| 0x79 | StackAlloc | D | Allocate stack frame |

### Agent-to-Agent Communication (0x80–0x8F)

| Hex | Name | Format | Description |
|-----|------|--------|-------------|
| 0x80 | ASend | G | Send message (non-blocking) |
| 0x81 | ARecv | G | Receive message (blocking) |
| 0x82 | AAsk | G | Send request, wait response |
| 0x83 | ATell | G | Fire-and-forget message |
| 0x84 | ADelegate | G | Delegate bytecode execution |
| 0x85 | ABroadcast | G | Broadcast to all agents |
| 0x86 | ASubscribe | G | Subscribe to channel |
| 0x87 | AWait | G | Block until condition |
| 0x88 | ATrust | G | Establish trust |
| 0x89 | AVerify | G | Verify trust level |

### Type/Meta Operations (0x90–0x9F)

| Hex | Name | Format | Description |
|-----|------|--------|-------------|
| 0x90 | Cast | C | Type-cast value |
| 0x91 | SizeOf | C | Size of type |
| 0x92 | TypeOf | C | Runtime type tag |

### Bitwise Operations (0xA0–0xAF)

| Hex | Name | Format | Description |
|-----|------|--------|-------------|
| 0xA0 | BAnd | C | Rd = Ra & Rb |
| 0xA1 | BOr | C | Rd = Ra \| Rb |
| 0xA2 | BXor | C | Rd = Ra ^ Rb |
| 0xA3 | BShl | C | Rd = Ra << Rb |
| 0xA4 | BShr | C | Rd = Ra >> Rb |
| 0xA5 | BNot | C | Rd = ~Ra |

### Vector/SIMD Operations (0xB0–0xBF)

| Hex | Name | Format | Description |
|-----|------|--------|-------------|
| 0xB0 | VLoad | E | Load vector from memory |
| 0xB1 | VStore | E | Store vector to memory |
| 0xB2 | VAdd | C | Component-wise addition |
| 0xB3 | VMul | C | Component-wise multiply |
| 0xB4 | VDot | C | Dot product (scalar result) |

## Usage Examples

### CLI

```bash
# Run bytecode
php src/CLI.php run program.flux

# Assemble assembly to bytecode
php src/CLI.php asm program.asm

# Disassemble bytecode
php src/CLI.php dis program.flux

# Start REPL
php src/CLI.php repl
```

### Programmatic Usage

```php
<?php
use SuperInstance\FluxVM\FluxVM;
use SuperInstance\FluxVM\Assembler;
use SuperInstance\FluxVM\Loader;

$vm = new FluxVM();

// Load bytecode from file
$bytecode = Loader::load('program.flux');
$vm->load($bytecode);
$vm->run();

// Or assemble from source
$source = '
    # Compute factorial of 5
    IMov R0, 5      # counter
    IMov R1, 1      # accumulator
loop:
    IMul R1, R1, R0 # R1 = R1 * R0
    IDec R0, 1      # R0--
    JumpIfNot R0, loop  # if R0 != 0, jump to loop
    Halt            # done
';
$bytecode = Assembler::assemble($source);
$vm->load($bytecode);
$vm->run();

echo "Result: " . $vm->getGP(1) . "\n"; // 120 (5!)
```

### REPL

```bash
php src/CLI.php repl
flux> asm IMov R8, 5; IMov R9, 3; IAdd R7, R8, R9; Halt
flux> regs
flux> quit
```

## Sample Program: Factorial

FLUX assembly to compute factorial(7):

```flax
# factorial.asm - Compute factorial(7)
# Result will be in R1 after Halt

    IMov R0, 7        # Counter = 7
    IMov R1, 1        # Accumulator = 1

loop:                 # Loop label
    IMul R1, R1, R0   # R1 = R1 * R0
    IDec R0, 1        # R0 = R0 - 1
    JumpIfNot R0, loop # If R0 != 0, jump back to loop
    Halt              # Stop execution
```

### Assembly Listing

```
0000  20 00 07 00     IMov R0, 7
0004  20 01 01 00     IMov R1, 1
0008  23 01 01 00     IMul R1, R1, R0
000C  29 00 01 00     IDec R0, 1
0010  05 00 F8 FF     JumpIfNot R0, -8
0014  00              Halt
```

### Bytecode (hex)

```
20 00 07 00 20 01 01 00 23 01 01 00 29 00 01 00 05 00 F8 FF 00
```

### Expected Output

Running this program produces `5040` in R1 (7! = 5040).

## Bytecode File Format

FLUX bytecode files have a simple header:

```
[4 bytes] Magic: 'F' 'L' 'U' 'X'
[1 byte]  Version: 0x03
[4 bytes] Code size (little-endian)
[N bytes] Code
```

## Error Handling

The VM throws `FluxVMException` on errors:

| Code | Constant | Description |
|------|----------|-------------|
| 0x01 | UNKNOWN_OPCODE | Unknown opcode encountered |
| 0x02 | INVALID_FORMAT | Invalid instruction format |
| 0x03 | DIVISION_BY_ZERO | Division by zero |
| 0x04 | STACK_OVERFLOW | Stack overflow |
| 0x05 | STACK_UNDERFLOW | Stack underflow |
| 0x06 | MEMORY_FAULT | Invalid memory access |
| 0x07 | PANIC | Panic instruction executed |
| 0x08 | INVALID_REGISTER | Invalid register number |
| 0x09 | INVALID_IMMEDIATE | Invalid immediate value |
| 0x0A | INVALID_BYTECODE | Invalid bytecode file |
| 0x0B | CYCLE_BUDGET_EXCEEDED | Execution budget exceeded |

## Requirements

- PHP 8.0+

## License

MIT
