# SuperInstance FLUX VM — Pure PHP FLUX ISA v3.0 Virtual Machine

A register-based bytecode virtual machine implementing the FLUX ISA v3.0 specification. Pure PHP 8.0+, no extensions required. Run FLUX bytecode in any PHP environment — CLI, web server, or embedded in a PHP agent.

```
composer require superinstance/flux-vm
```

## What is FLUX?

FLUX (Fleet Language with Unified eXecution) is a register-based bytecode ISA designed for multi-agent fleet coordination. Agents communicate by exchanging FLUX bytecode — not messages, not function calls, but *executable programs* that one agent can delegate to another.

See the [FLUX ISA v3.0 specification](https://github.com/SuperInstance/flux-research/tree/main/specs) for the full architecture.

## Architecture

### Register Model

| Register | Alias | Type | Purpose |
|----------|-------|------|---------|
| R0–R7 | — | int32 | General purpose (caller-saved) |
| R8 | RV | int32 | Return value |
| R9 | A0 | int32 | First argument |
| R10 | A1 | int32 | Second argument |
| R11 | SP | int32 | Stack pointer |
| R12 | FP | int32 | Frame pointer |
| R13 | FL | int32 | Flags (Z/S/C/V) |
| R14 | TP | int32 | Thread/tile pointer |
| R15 | LR | int32 | Link register (return address) |
| F0–F15 | — | float32 | Floating point registers |
| V0–V15 | — | 256 bytes each | Vector/SIMD registers |

### Flags Register (R13/FL)

Four condition flags set by arithmetic and comparison operations:

| Bit | Name | Set when result is... |
|-----|------|----------------------|
| 0 | Z (Zero) | ...zero |
| 1 | S (Sign) | ...negative |
| 2 | C (Carry) | ...unsigned overflow |
| 3 | V (Overflow) | ...signed overflow |

### Memory Model

- Flat memory, configurable size (default 64 KB)
- Stack grows **downward** from top of memory
- SP starts at memory size, decreases with pushes
- Memory is a flat byte array; load/store ops use base register + 16-bit unsigned offset

### Instruction Formats

| Format | Size | Use |
|--------|------|-----|
| **A** | 1 byte | Nullary: Halt, Nop, Ret, Yield, Panic, Unreachable |
| **B** | 3 bytes | Two registers: IMov, FMov, Push, Pop, Dup, Swap |
| **C** | 4 bytes | Three registers: arithmetic, comparisons, conversions |
| **D** | 4 bytes | Register + 16-bit immediate: IInc, IDec, StackAlloc |
| **E** | 5 bytes | Two registers + 16-bit offset: memory load/store |
| **G** | 2+N bytes | Variable: jumps, calls, A2A ops |

## Installation

```bash
composer require superinstance/flux-vm
```

Or clone and use directly:

```bash
git clone https://github.com/SuperInstance/flux-vm-php
cd flux-vm-php
php src/CLI.php --help
```

## Opcode Reference

### Control Flow (0x00–0x0F)

| Hex | Name | Format | Description |
|-----|------|--------|-------------|
| 0x00 | Halt | A | Stop execution |
| 0x01 | Nop | A | No operation |
| 0x02 | Ret | A | Return from function (PC = LR) |
| 0x03 | Jump | G | Unconditional branch (PC += offset16) |
| 0x04 | JumpIf | G | Branch if register ≠ 0 |
| 0x05 | JumpIfNot | G | Branch if register = 0 |
| 0x06 | Call | G | Call function at index |
| 0x07 | CallIndirect | G | Call via address in register |
| 0x08 | Yield | A | Pause execution, return to scheduler |
| 0x09 | Panic | A | Abort with error |
| 0x0A | Unreachable | A | Should never execute — traps |

### Stack Operations (0x10–0x1F)

| Hex | Name | Format | Description |
|-----|------|--------|-------------|
| 0x10 | Push | B | stack[SP++] = GP[Rs] |
| 0x11 | Pop | B | GP[Rd] = stack[--SP] |
| 0x12 | Dup | B | GP[Rd] = GP[Rs] (copy) |
| 0x13 | Swap | B | swap(GP[Ra], GP[Rb]) |

### Integer Arithmetic (0x20–0x3F)

| Hex | Name | Format | Description |
|-----|------|--------|-------------|
| 0x20 | IMov | B | GP[Rd] = GP[Rs] |
| 0x21 | IAdd | C | GP[Rd] = GP[Ra] + GP[Rb] |
| 0x22 | ISub | C | GP[Rd] = GP[Ra] - GP[Rb] |
| 0x23 | IMul | C | GP[Rd] = GP[Ra] * GP[Rb] |
| 0x24 | IDiv | C | GP[Rd] = GP[Ra] / GP[Rb] |
| 0x25 | IMod | C | GP[Rd] = GP[Ra] % GP[Rb] |
| 0x26 | INeg | C | GP[Rd] = -GP[Ra] |
| 0x27 | IAbs | C | GP[Rd] = abs(GP[Ra]) |
| 0x28 | IInc | D | GP[Rd] += imm16 |
| 0x29 | IDec | D | GP[Rd] -= imm16 |
| 0x2A | IMin | C | GP[Rd] = min(GP[Ra], GP[Rb]) |
| 0x2B | IMax | C | GP[Rd] = max(GP[Ra], GP[Rb]) |
| 0x2C | IAnd | C | GP[Rd] = GP[Ra] & GP[Rb] |
| 0x2D | IOr | C | GP[Rd] = GP[Ra] \| GP[Rb] |
| 0x2E | IXor | C | GP[Rd] = GP[Ra] ^ GP[Rb] |
| 0x2F | IShl | C | GP[Rd] = GP[Ra] << (GP[Rb] & 31) |
| 0x30 | IShr | C | GP[Rd] = GP[Ra] >> (GP[Rb] & 31) |
| 0x31 | INot | C | GP[Rd] = ~GP[Ra] |
| 0x32–0x37 | ICmp* | C | Comparisons (eq, ne, lt, le, gt, ge) |

### Float Arithmetic (0x40–0x5F)

| Hex | Name | Format | Description |
|-----|------|--------|-------------|
| 0x40 | FMov | B | FP[Rd] = FP[Rs] |
| 0x41 | FAdd | C | FP[Rd] = FP[Ra] + FP[Rb] |
| 0x42 | FSub | C | FP[Rd] = FP[Ra] - FP[Rb] |
| 0x43 | FMul | C | FP[Rd] = FP[Ra] * FP[Rb] |
| 0x44 | FDiv | C | FP[Rd] = FP[Ra] / FP[Rb] |
| 0x48 | FSqrt | C | FP[Rd] = sqrt(FP[Ra]) |
| 0x49 | FFloor | C | FP[Rd] = floor(FP[Ra]) |
| 0x4A | FCeil | C | FP[Rd] = ceil(FP[Ra]) |
| 0x4E | FSin | C | FP[Rd] = sin(FP[Ra]) |
| 0x4F | FCos | C | FP[Rd] = cos(FP[Ra]) |
| 0x50 | FExp | C | FP[Rd] = exp(FP[Ra]) |
| 0x51 | FLog | C | FP[Rd] = log(FP[Ra]) |
| 0x54–0x59 | FCmp* | C | Float comparisons |
| 0x53 | FLerp | C | FP[Rd] = FP[Ra] * t + FP[Rb] * (1-t) |

### Conversions (0x60–0x6F)

| Hex | Name | Description |
|-----|------|-------------|
| 0x60 | IToF | int32 → float32 |
| 0x61 | FToI | float32 → int32 (truncates) |
| 0x62 | BToI | bool → int32 (0 or 1) |
| 0x63 | IToB | int32 → bool (0 or 1) |

### Memory Operations (0x70–0x7F)

| Hex | Name | Description |
|-----|------|-------------|
| 0x70–0x73 | Load8/16/32/64 | Load from memory (zero-extend for <64) |
| 0x74–0x77 | Store8/16/32/64 | Store to memory |
| 0x78 | LoadAddr | GP[Rd] = Rbase + offset (address computation) |
| 0x79 | StackAlloc | SP -= size; GP[Rd] = new SP |

### Agent-to-Agent Communication (0x80–0x8F)

| Hex | Name | Description |
|-----|------|-------------|
| 0x80 | ASend | Send message to agent |
| 0x81 | ARecv | Receive from agent |
| 0x82 | AAsk | Send request, wait for response |
| 0x83 | ATell | Fire-and-forget message |
| 0x84 | ADelegate | Delegate bytecode execution |
| 0x85 | ABroadcast | Send to all known agents |
| 0x86 | ASubscribe | Subscribe to channel |
| 0x87 | AWait | Block until condition |
| 0x88 | ATrust | Establish trust relationship |
| 0x89 | AVerify | Verify trust level |

*Note: A2A opcodes are stubs in this PHP implementation — they log attempts and return success. Full A2A requires a running fleet message bus.*

### Vector/SIMD (0xB0–0xBF)

| Hex | Name | Description |
|-----|------|-------------|
| 0xB0 | VLoad | Load vector component from memory |
| 0xB1 | VStore | Store vector component to memory |
| 0xB2 | VAdd | Element-wise addition |
| 0xB3 | VMul | Element-wise multiplication |
| 0xB4 | VDot | Dot product (scalar result) |

## Usage

### Programmatic

```php
<?php
require_once 'vendor/autoload.php';

use SuperInstance\FluxVM\FluxVM;
use SuperInstance\FluxVM\Loader;

$vm = new FluxVM();

// Build bytecode: R9=2, R10=3, R8=R9+R10, Halt
$bytecode = pack('C4', 0x28, 0x09, 0x02, 0x00)  // IInc R9, 2
         . pack('C4', 0x28, 0x0A, 0x03, 0x00)  // IInc R10, 3
         . pack('C4', 0x21, 0x08, 0x09, 0x0A)  // IAdd R8, R9, R10
         . pack('C', 0x00);                    // Halt

$vm->load($bytecode);
$vm->run();

$regs = $vm->regs();
echo "R8 = " . $regs['GP'][8] . "\n";  // Output: 5
echo "State: " . $regs['STATE'] . "\n"; // Output: halted
echo "Cycles: " . $vm->stats()['cycles_used'] . "\n";
```

### Using the Assembler

```php
<?php
use SuperInstance\FluxVM\Assembler;

$source = '
    # Compute factorial of 5
    IInc R9, 5        # R9 = 5
    IInc R10, 1       # R10 = 1 (accumulator)
    IInc R11, 0       # R11 = 0 (counter)
loop:
    ICmpEq R12, R11, R9   # R12 = (R11 == R9)
    JumpIfNot R12, end    # if R11 != R9, jump to end
    IMul R10, R10, R11    # R10 = R10 * R11
    IInc R11, 1           # R11++
    Jump loop             # jump to loop
end:
    Halt
';

$bytecode = Assembler::assembleString($source);
file_put_contents('factorial.flux', $bytecode);
```

### CLI

```bash
# Assemble FLUX assembly to bytecode
php src/CLI.php asm program.asm -o program.flux

# Run bytecode
php src/CLI.php run program.flux

# Disassemble bytecode to assembly
php src/CLI.php dis program.flux

# Run in step mode
php src/CLI.php run program.flux --step

# Interactive REPL
php src/CLI.php repl
```

### Loading FLUX Binary Files

FLUX binary files have a 4-byte magic header:

```
[ 'F' (0x46) ][ 'L' (0x4C) ][ 'U' (0x55) ][ 'X' (0x58) ]
[ version (1 byte) ]
[ code_size (4 bytes, LE) ]
[ code (N bytes) ]
```

The `Loader` class handles header stripping automatically:

```php
<?php
use SuperInstance\FluxVM\FluxVM;
use SuperInstance\FluxVM\Loader;

$bytecode = Loader::loadFile('program.flux');
$vm = new FluxVM();
$vm->load($bytecode);
$vm->run();
```

## Sample Program: Factorial

```flux
# factorial.asm — compute 5!
# R9 = input, R10 = result, R11 = counter

    IInc R9, 5        # R9 = 5
    IInc R10, 1       # R10 = 1 (result)
    IInc R11, 1       # R11 = 1 (start at 1)

loop:
    IMul R10, R10, R11    # R10 = R10 * R11
    IInc R11, 1           # R11++
    ICmpLe R12, R11, R9   # R12 = (R11 <= R9)
    JumpIf R12, loop      # if R11 <= R9, loop
    Halt                 # R10 = 5! = 120
```

Assemble and run:

```bash
php src/CLI.php asm factorial.asm -o factorial.flux
php src/CLI.php run factorial.flux
# Output: R8 = 120 (return value)
```

## Memory Layout

```
Higher addresses
+------------------+
| Global data       |
+------------------+ ← GP[0]–GP[7]
| Argument area     |
+------------------+ ← R9, R10
+------------------+
| Return address    | ← pushed by CALL
+------------------+ ← FP
| Local variables   |
+------------------+ ← SP (starts high, grows down)
| Stack space      |
+------------------+ ← 0
Lower addresses
```

## Calling Convention

- First two integer args in **R9, R10**
- First two float args in **F9, F10**
- Additional args spilled to stack
- Integer return in **R8**
- Float return in **F8**
- Callee-saved: R11 (SP), R12 (FP), R13 (FL), R14, R15 (LR)

## Error Handling

```php
use SuperInstance\FluxVM\FluxVM;
use SuperInstance\FluxVM\FluxVMException;

try {
    $vm = new FluxVM();
    $vm->load($bytecode);
    $vm->run();
} catch (FluxVMException $e) {
    match ($e->getCode()) {
        FluxVMException::DIVISION_BY_ZERO => echo "Divide by zero at PC {$e->getMetadata()['pc']}\n",
        FluxVMException::STACK_OVERFLOW => echo "Stack overflow\n",
        FluxVMException::UNKNOWN_OPCODE => echo "Unknown opcode: " . $e->getMetadata()['opcode'] . "\n",
        default => echo "VM error: " . $e->getMessage() . "\n",
    };
}
```

## Architecture Notes

This VM is designed to be embedded in PHP agents. A PHP agent that speaks FLUX can:
1. **Receive bytecode** from another agent (via A2A opcodes or external transport)
2. **Execute it** in this VM
3. **Return results** or delegate further work
4. **Extend it** by subclassing FluxVM and adding custom opcodes

The VM is single-threaded and non-preemptive. For PHP agents that need concurrent execution, run multiple VM instances or use Swoole/Gearman for parallelism.

## License

MIT
